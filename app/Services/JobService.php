<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobStatusHistory;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JobService
{
    /**
     * Fields compared for the audit log when a job's details are edited (SPEC §8.7). Job no, status, repair
     * cost, received-by and dates are not editable.
     *
     * @var list<string>
     */
    public const EDITABLE_FIELDS = [
        'customer',
        'customer_company',
        'customer_address',
        'customer_city',
        'customer_phone',
        'customer_phone2',
        'customer_email',
        'device_type',
        'device_type_other',
        'brand',
        'model',
        'serial_no',
        'color',
        'parts',
        'fault_description',
        'accessories',
        'accessories_other',
        'physical_condition',
        'special_notes',
        'technician',
        'services',
        'estimated_cost',
        'advance_paid',
        'expected_delivery_date',
    ];

    /**
     * The note on the first history row of every job.
     */
    public const RECEIVED_NOTE = 'Job received';

    public function __construct(private AuditLogger $audit) {}

    /**
     * Take in a job (New Job Note): the number (generated or custom), the customer (picked, or created from the
     * typed details), status `pending` and the first history row "Job received".
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, User $receivedBy): Job
    {
        return DB::transaction(function () use ($data, $receivedBy): Job {
            $jobNo = Numbering::nextJobNo($data['job_no'] ?? null);
            $customer = $this->resolveCustomer($data);

            $job = Job::query()->create([
                'job_no' => $jobNo,
                ...$this->detailColumns($data, $customer, null),
                'received_by_id' => $receivedBy->id,
                'received_by_name' => $receivedBy->name ?: $receivedBy->email,
                'status' => 'pending',
            ]);

            $this->addHistory($job, 'pending', self::RECEIVED_NOTE, null, $receivedBy);

            return $job;
        });
    }

    /**
     * Edit a job's details (`jobs.edit`), audit logging the fields that change in the same transaction.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{field: string, before: mixed, after: mixed}> the changes made
     *
     * @throws ValidationException
     */
    public function update(Job $job, array $data, User $editor): array
    {
        return DB::transaction(function () use ($job, $data, $editor): array {
            $job = Job::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();
            $customer = $this->resolveCustomer($data);
            $columns = $this->detailColumns($data, $customer, $job);

            $changes = $this->audit->diff(
                self::auditValues([
                    ...$job->only(array_keys($columns)),
                    'expected_delivery_date' => $job->expected_delivery_date?->toDateString(),
                ]),
                self::auditValues($columns),
                self::EDITABLE_FIELDS,
            );

            if ($changes === []) {
                return [];
            }

            $job->update($columns);

            $this->audit->write($job->getTable(), $job->id, $job->job_no, $changes, $editor);

            return $changes;
        });
    }

    /**
     * Update Job Status: set the status (Job Done may set the repair cost; Delivered sets the return date) and
     * append a history row.
     *
     * @param  array{status: string, repair_cost?: float|int|string|null, note?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function updateStatus(Job $job, array $data, User $user): JobStatusHistory
    {
        return DB::transaction(function () use ($job, $data, $user): JobStatusHistory {
            $job = Job::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();
            $status = $data['status'];
            $note = trim((string) ($data['note'] ?? ''));
            $repairCost = $status === 'done' && filled($data['repair_cost'] ?? null)
                ? SupplierService::cents($data['repair_cost']) / 100
                : null;

            if ($status === $job->status && $note === '' && $repairCost === null) {
                throw ValidationException::withMessages(['note' => 'Nothing to update — change the status or add a note.']);
            }

            $job->status = $status;

            if ($repairCost !== null) {
                $job->repair_cost = $repairCost;
            }

            if ($status === 'delivered' && $job->isDirty('status')) {
                $job->date_returned = now();
            }

            $job->save();

            return $this->addHistory(
                $job,
                $status,
                $note !== '' ? $note : 'Status changed to '.$job->statusLabel(),
                $repairCost,
                $user,
            );
        });
    }

    /**
     * Step 11 of a POS checkout that billed the job: Delivered, returned now, with a history row naming the
     * invoice. Call it inside the sale's transaction with the job locked.
     */
    public function deliverBilledJob(Job $job, string $invoiceNo, User $cashier): void
    {
        $job->update(['status' => 'delivered', 'date_returned' => now()]);

        $this->addHistory($job, 'delivered', "Delivered & billed via POS — Invoice {$invoiceNo}", null, $cashier);
    }

    /**
     * The lines a finished job adds to a POS bill (SPEC §8.4 jobBillableServices): its services, then
     * "Other repair charges" / "Repair charge" or "Repair cost adjustment" up to the final cost, then
     * "Less: advance paid". Paid lines above zero take the Card / KokoPay surcharge; free and negative lines don't.
     *
     * @return list<array{name: string, price: float, chargeType: string, freeReason: string, surcharge: bool}>
     */
    public static function billableLines(Job $job): array
    {
        $services = $job->services ?? [];
        $servicesTotalCents = self::servicesTotalCents($services);
        $finalCents = self::finalCostCents($job);
        $lines = [];

        foreach ($services as $service) {
            $isFree = ($service['chargeType'] ?? 'paid') === 'free';
            $lines[] = self::billableLine(
                (string) $service['name'],
                $isFree ? 0 : SupplierService::cents($service['price'] ?? 0),
                $isFree ? 'free' : 'paid',
                $isFree ? (string) ($service['freeReason'] ?? '') : '',
            );
        }

        $differenceCents = $finalCents - $servicesTotalCents;

        if ($differenceCents > 0) {
            $lines[] = self::billableLine($services === [] ? 'Repair charge' : 'Other repair charges', $differenceCents);
        } elseif ($differenceCents < 0) {
            $lines[] = self::billableLine('Repair cost adjustment', $differenceCents);
        }

        $advanceCents = min(SupplierService::cents($job->advance_paid), max(0, $finalCents));

        if ($advanceCents > 0) {
            $lines[] = self::billableLine('Less: advance paid', -$advanceCents);
        }

        return $lines;
    }

    /**
     * The job's final cost: the repair cost, else the paid services total, else the estimate.
     */
    public static function finalCostCents(Job $job): int
    {
        if ($job->repair_cost !== null) {
            return SupplierService::cents($job->repair_cost);
        }

        return ($job->services ?? []) !== []
            ? self::servicesTotalCents($job->services)
            : SupplierService::cents($job->estimated_cost);
    }

    /**
     * Total of the paid services.
     *
     * @param  list<array<string, mixed>>  $services
     */
    public static function servicesTotalCents(array $services): int
    {
        return array_sum(array_map(
            fn (array $service): int => ($service['chargeType'] ?? 'paid') === 'free' ? 0 : SupplierService::cents($service['price'] ?? 0),
            $services,
        ));
    }

    /**
     * Active technicians for the "Assign technician" select, sorted by name.
     *
     * @return Collection<int, User>
     */
    public static function technicians(): Collection
    {
        return User::query()
            ->where('role', Permissions::TECHNICIAN)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /**
     * Readable values of a job's editable fields for the audit log (lists become one line of text).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function auditValues(array $values): array
    {
        $parts = collect($values['parts'] ?? [])
            ->map(fn (array $part): string => trim(implode(' ', array_filter([$part['name'] ?? '', $part['spec'] ?? '', filled($part['serialNo'] ?? null) ? "(S/N {$part['serialNo']})" : '']))))
            ->implode('; ');

        $services = collect($values['services'] ?? [])
            ->map(fn (array $service): string => ($service['chargeType'] ?? 'paid') === 'free'
                ? "{$service['name']} Free".(filled($service['freeReason'] ?? null) ? " ({$service['freeReason']})" : '')
                : "{$service['name']} ".Money::format($service['price'] ?? 0))
            ->implode('; ');

        return [
            ...$values,
            'customer' => $values['customer_name'] ?? null,
            'technician' => $values['assigned_technician_name'] ?? null,
            'parts' => $parts,
            'accessories' => implode(', ', $values['accessories'] ?? []),
            'physical_condition' => implode(', ', $values['physical_condition'] ?? []),
            'services' => $services,
        ];
    }

    /**
     * @return array{name: string, price: float, chargeType: string, freeReason: string, surcharge: bool}
     */
    private static function billableLine(string $name, int $priceCents, string $chargeType = 'paid', string $freeReason = ''): array
    {
        return [
            'name' => $name,
            'price' => $priceCents / 100,
            'chargeType' => $chargeType,
            'freeReason' => $freeReason,
            'surcharge' => $chargeType === 'paid' && $priceCents > 0,
        ];
    }

    /**
     * The picked customer, or a new one created from the typed details.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): Customer
    {
        if (filled($data['customer_id'] ?? null)) {
            return Customer::query()->findOrFail($data['customer_id']);
        }

        return Customer::query()->create([
            'name' => trim((string) $data['customer_name']),
            'phone' => trim((string) $data['customer_phone']),
            'phone2' => self::text($data, 'customer_phone2') ?: null,
            'email' => self::text($data, 'customer_email') ?: null,
            'address' => trim(implode(', ', array_filter([self::text($data, 'customer_address'), self::text($data, 'customer_city')]))) ?: null,
        ]);
    }

    /**
     * The job columns the New / Edit form sets. Blank text is stored as '' (the columns are not nullable),
     * empty part rows are dropped and free services are stored at 0.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function detailColumns(array $data, Customer $customer, ?Job $job): array
    {
        $deviceType = (string) $data['device_type'];
        [$technicianId, $technicianName] = $this->resolveTechnician($data['assigned_technician_id'] ?? null, $job);

        return [
            'customer_id' => $customer->id,
            'customer_name' => trim((string) $data['customer_name']),
            'customer_company' => self::text($data, 'customer_company'),
            'customer_address' => self::text($data, 'customer_address'),
            'customer_city' => self::text($data, 'customer_city'),
            'customer_phone' => trim((string) $data['customer_phone']),
            'customer_phone2' => self::text($data, 'customer_phone2'),
            'customer_email' => self::text($data, 'customer_email'),
            'device_type' => $deviceType,
            'device_type_other' => $deviceType === 'Other' ? self::text($data, 'device_type_other') : '',
            'brand' => self::text($data, 'brand'),
            'model' => self::text($data, 'model'),
            'serial_no' => self::text($data, 'serial_no'),
            'color' => self::text($data, 'color'),
            'parts' => $this->parts($data['parts'] ?? []),
            'fault_description' => trim((string) $data['fault_description']),
            'accessories' => array_values(array_intersect(Job::ACCESSORIES, $data['accessories'] ?? [])),
            'accessories_other' => self::text($data, 'accessories_other'),
            'physical_condition' => array_values(array_intersect(Job::CONDITIONS, $data['physical_condition'] ?? [])),
            'special_notes' => self::text($data, 'special_notes'),
            'assigned_technician_id' => $technicianId,
            'assigned_technician_name' => $technicianName,
            'services' => $this->services($data['services'] ?? []),
            'estimated_cost' => SupplierService::cents($data['estimated_cost'] ?? 0) / 100,
            'advance_paid' => SupplierService::cents($data['advance_paid'] ?? 0) / 100,
            'expected_delivery_date' => filled($data['expected_delivery_date'] ?? null) ? $data['expected_delivery_date'] : null,
        ];
    }

    /**
     * An active technician, or the job's current technician kept as is.
     *
     * @return array{0: ?int, 1: string}
     *
     * @throws ValidationException
     */
    private function resolveTechnician(mixed $technicianId, ?Job $job): array
    {
        if (blank($technicianId)) {
            return [null, ''];
        }

        if ($job !== null && (int) $technicianId === $job->assigned_technician_id) {
            return [$job->assigned_technician_id, $job->assigned_technician_name];
        }

        $technician = User::query()
            ->whereKey($technicianId)
            ->where('role', Permissions::TECHNICIAN)
            ->where('status', 'active')
            ->first();

        if ($technician === null) {
            throw ValidationException::withMessages(['assigned_technician_id' => 'Choose an active technician.']);
        }

        return [$technician->id, $technician->name ?: $technician->email];
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @return list<array{id: string, name: string, spec: string, serialNo: string}>
     */
    private function parts(array $parts): array
    {
        return collect($parts)
            ->map(fn (array $part): array => [
                'id' => filled($part['id'] ?? null) ? (string) $part['id'] : Str::random(8),
                'name' => self::text($part, 'name'),
                'spec' => self::text($part, 'spec'),
                'serialNo' => self::text($part, 'serialNo'),
            ])
            ->reject(fn (array $part): bool => $part['name'] === '' && $part['spec'] === '' && $part['serialNo'] === '')
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $services
     * @return list<array{id: string, name: string, price: float, chargeType: string, freeReason: string}>
     */
    private function services(array $services): array
    {
        return array_map(function (array $service): array {
            $isFree = ($service['chargeType'] ?? 'paid') === 'free';

            return [
                'id' => filled($service['id'] ?? null) ? (string) $service['id'] : Str::random(8),
                'name' => self::text($service, 'name'),
                'price' => $isFree ? 0 : SupplierService::cents($service['price'] ?? 0) / 100,
                'chargeType' => $isFree ? 'free' : 'paid',
                'freeReason' => $isFree ? self::text($service, 'freeReason') : '',
            ];
        }, array_values($services));
    }

    private function addHistory(Job $job, string $status, string $note, ?float $repairCost, User $user): JobStatusHistory
    {
        return $job->statusHistory()->create([
            'status' => $status,
            'note' => $note,
            'repair_cost' => $repairCost,
            'updated_by_id' => $user->id,
            'updated_by_name' => $user->name ?: $user->email,
        ]);
    }

    /**
     * A trimmed text value, '' when missing.
     *
     * @param  array<string, mixed>  $data
     */
    private static function text(array $data, string $key): string
    {
        return trim((string) ($data[$key] ?? ''));
    }
}
