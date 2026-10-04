<?php

namespace App\Services;

use App\Models\Counter;
use App\Models\Job;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class Numbering
{
    /**
     * Document prefix for each counter, keyed by counter name.
     *
     * @var array<string, string>
     */
    public const PREFIXES = [
        'invoice' => 'INV',
        'quotation' => 'QUO',
        'job' => 'JOB',
        'grn' => 'GRN',
        'transfer' => 'TRF',
        'stockOut' => 'SO',
        'supplierPayment' => 'PAY',
        'shift' => 'SHIFT',
        'expense' => 'EXP',
        'salary' => 'SAL',
    ];

    /**
     * Increment the named counter and return the next document number, e.g. "INV-00001".
     *
     * Must be called inside DB::transaction() so the row lock is held until the
     * record using the number is committed.
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public static function next(string $counterName, string $prefix): string
    {
        $counter = static::lockCounter($counterName);
        $counter->value++;
        $counter->save();

        return static::format($prefix, $counter->value);
    }

    /**
     * Resolve the number for a new job, honouring an optional custom number typed by staff.
     *
     * A custom number is upper-cased and must be unused. When it matches JOB-{n} with n
     * above the counter, the counter is moved up to n so generated numbers never collide.
     * Must be called inside DB::transaction().
     *
     * @throws LogicException
     * @throws RuntimeException
     * @throws ValidationException
     */
    public static function nextJobNo(?string $customJobNo = null): string
    {
        $customJobNo = strtoupper(trim((string) $customJobNo));

        if ($customJobNo === '') {
            return static::next('job', static::PREFIXES['job']);
        }

        $counter = static::lockCounter('job');

        if (Job::query()->where('job_no', $customJobNo)->exists()) {
            throw ValidationException::withMessages([
                'job_no' => "Job number {$customJobNo} is already in use.",
            ]);
        }

        if (preg_match('/^JOB-(\d+)$/', $customJobNo, $matches) && (int) $matches[1] > $counter->value) {
            $counter->value = (int) $matches[1];
            $counter->save();
        }

        return $customJobNo;
    }

    /**
     * Preview the next generated job number without consuming it (for the New Job form).
     */
    public static function previewJobNo(): string
    {
        $currentValue = (int) Counter::query()->whereKey('job')->value('value');

        return static::format(static::PREFIXES['job'], $currentValue + 1);
    }

    /**
     * Format a counter value as a zero-padded document number.
     */
    public static function format(string $prefix, int $value): string
    {
        return sprintf('%s-%05d', $prefix, $value);
    }

    /**
     * Lock the named counter row for update within the current transaction.
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    protected static function lockCounter(string $counterName): Counter
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Document numbers must be generated inside DB::transaction().');
        }

        $counter = Counter::query()->whereKey($counterName)->lockForUpdate()->first();

        if ($counter === null) {
            throw new RuntimeException("Counter [{$counterName}] does not exist.");
        }

        return $counter;
    }
}
