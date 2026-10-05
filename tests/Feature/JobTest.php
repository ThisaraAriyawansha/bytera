<?php

namespace Tests\Feature;

use App\Mail\JobReceivedMail;
use App\Mail\JobUpdateMail;
use App\Models\AuditLog;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Sale;
use App\Models\User;
use App\Services\JobService;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class JobTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'Cashier', 'name' => 'Kasun Perera']);
    }

    public function test_a_new_job_note_creates_a_pending_job_its_customer_and_the_first_history_row(): void
    {
        $technician = User::factory()->create(['role' => 'Technician', 'name' => 'Ruwan Silva']);

        $this->actingAs($this->cashier)
            ->postJson(route('jobs.store'), [
                ...$this->jobPayload(),
                'assigned_technician_id' => $technician->id,
                'parts' => [
                    ['id' => 'a', 'name' => 'RAM', 'spec' => '8GB DDR4', 'serialNo' => ''],
                    ['id' => 'b', 'name' => '', 'spec' => '', 'serialNo' => ''],
                ],
                'accessories' => ['Charger', 'Bag'],
                'physical_condition' => ['Scratches'],
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'JOB-00001 saved.')
            ->assertJsonPath('nextJobNo', 'JOB-00002');

        $job = Job::query()->sole();
        $this->assertSame(['JOB-00001', 'pending', 'Kasun Perera', 'Ruwan Silva', 'Laptop'], [
            $job->job_no, $job->status, $job->received_by_name, $job->assigned_technician_name, $job->device_type,
        ]);
        $this->assertCount(1, $job->parts);
        $this->assertSame(['Charger', 'Bag'], $job->accessories);
        $this->assertSame('', $job->customer_company);
        $this->assertSame([['name' => 'Replace SSD', 'price' => 4000, 'chargeType' => 'paid', 'freeReason' => ''], ['name' => 'Cleaning', 'price' => 0, 'chargeType' => 'free', 'freeReason' => 'Loyalty']],
            array_map(fn (array $service): array => array_diff_key($service, ['id' => true]), $job->services));

        $customer = Customer::query()->sole();
        $this->assertSame(['Nimal Fernando', '0771234567', 'nimal@example.com'], [$customer->name, $customer->phone, $customer->email]);
        $this->assertSame($customer->id, $job->customer_id);

        $history = $job->statusHistory()->sole();
        $this->assertSame(['pending', 'Job received', $this->cashier->id], [$history->status, $history->note, $history->updated_by_id]);
    }

    public function test_a_picked_customer_is_reused_and_a_custom_job_number_moves_the_counter_up(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->cashier)
            ->postJson(route('jobs.store'), [...$this->jobPayload(), 'customer_id' => $customer->id, 'job_no' => 'job-00120'])
            ->assertCreated()
            ->assertJsonPath('nextJobNo', 'JOB-00121');

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame('JOB-00120', Job::query()->sole()->job_no);
        $this->assertSame(120, Counter::query()->find('job')->value);

        $this->actingAs($this->cashier)
            ->postJson(route('jobs.store'), [...$this->jobPayload(), 'job_no' => 'JOB-00120'])
            ->assertJsonValidationErrors(['job_no' => 'Job number JOB-00120 is already in use.']);
    }

    public function test_a_new_job_needs_the_customer_fault_and_a_named_other_device_type(): void
    {
        $this->actingAs($this->cashier)
            ->postJson(route('jobs.store'), [
                ...$this->jobPayload(),
                'customer_name' => '',
                'customer_phone' => '',
                'device_type' => 'Other',
                'fault_description' => '',
                'services' => [['name' => '', 'chargeType' => 'paid', 'price' => '500']],
            ])
            ->assertJsonValidationErrors(['customer_name', 'customer_phone', 'device_type_other', 'fault_description', 'services.0.name']);

        $this->assertSame(0, Job::query()->count());
    }

    public function test_the_jobs_list_searches_by_job_number_name_or_phone_and_filters_by_status(): void
    {
        Job::factory()->create(['job_no' => 'JOB-00123', 'customer_name' => 'Amal Perera', 'customer_phone' => '0711111111']);
        Job::factory()->create(['job_no' => 'JOB-00124', 'customer_name' => 'Sunil Jayasuriya', 'customer_phone' => '0779999999', 'status' => 'done']);

        $this->actingAs($this->cashier)->get(route('jobs.index', ['search' => '123']))
            ->assertOk()->assertSee('Amal Perera')->assertDontSee('Sunil Jayasuriya');

        $this->actingAs($this->cashier)->get(route('jobs.index', ['search' => 'Sun']))
            ->assertOk()->assertSee('Sunil Jayasuriya')->assertDontSee('Amal Perera');

        $this->actingAs($this->cashier)->get(route('jobs.index', ['search' => '0711']))
            ->assertOk()->assertSee('Amal Perera')->assertDontSee('Sunil Jayasuriya');

        $this->actingAs($this->cashier)->get(route('jobs.index', ['status' => 'done']))
            ->assertOk()->assertSee('Sunil Jayasuriya')->assertDontSee('Amal Perera')->assertSee('Job Done');
    }

    public function test_job_done_records_the_repair_cost_and_delivered_sets_the_return_date(): void
    {
        $job = Job::factory()->create();

        $this->actingAs($this->cashier)
            ->postJson(route('jobs.status', $job), ['status' => 'done', 'repair_cost' => 6500, 'note' => 'Replaced SSD'])
            ->assertOk()
            ->assertJsonPath('job.status', 'done')
            ->assertJsonPath('job.history.0.note', 'Replaced SSD')
            ->assertJsonPath('job.history.0.repair_cost', 6500)
            ->assertJsonPath('job.balance', 6500);

        $this->assertSame(['done', '6500.00'], [$job->fresh()->status, $job->fresh()->repair_cost]);

        $this->actingAs($this->cashier)
            ->postJson(route('jobs.status', $job), ['status' => 'done'])
            ->assertJsonValidationErrors(['note' => 'Nothing to update — change the status or add a note.']);

        $this->actingAs($this->cashier)
            ->postJson(route('jobs.status', $job), ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('job.history.0.note', 'Status changed to Delivered');

        $this->assertNotNull($job->fresh()->date_returned);
        $this->assertSame(2, $job->statusHistory()->count());
    }

    public function test_editing_a_job_needs_jobs_edit_and_is_audit_logged(): void
    {
        $job = Job::factory()->create(['customer_id' => Customer::factory(), 'brand' => 'Dell', 'status' => 'ongoing']);

        $this->actingAs($this->cashier)
            ->putJson(route('jobs.update', $job), $this->jobPayload())
            ->assertForbidden();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson(route('jobs.update', $job), [...$this->jobPayload(), 'customer_id' => $job->customer_id, 'brand' => 'HP'])
            ->assertOk()
            ->assertJsonPath('job.brand', 'HP')
            ->assertJsonPath('job.status', 'ongoing');

        $log = AuditLog::query()->sole();
        $this->assertSame(['jobs', (string) $job->id, $job->job_no], [$log->collection_name, $log->doc_id, $log->label]);
        $this->assertContains(['field' => 'brand', 'before' => 'Dell', 'after' => 'HP'], $log->changes);
        $this->assertContains(['field' => 'services', 'before' => null, 'after' => 'Replace SSD Rs. 4,000; Cleaning Free (Loyalty)'], $log->changes);
        $this->assertSame($job->job_no, $job->fresh()->job_no);
    }

    public function test_job_emails_go_to_the_customer_on_the_job(): void
    {
        Mail::fake();

        $job = Job::factory()->create(['job_no' => 'JOB-00007', 'customer_email' => 'nimal@example.com']);

        $this->actingAs($this->cashier)
            ->postJson(route('jobs.email', $job), ['type' => 'received'])
            ->assertOk()
            ->assertJsonPath('message', 'Email sent to nimal@example.com.');

        Mail::assertSent(JobReceivedMail::class, fn (JobReceivedMail $mail): bool => $mail->hasTo('nimal@example.com')
            && $mail->hasSubject("We've received your job JOB-00007 - M-Fixpro"));

        $this->actingAs($this->cashier)->postJson(route('jobs.status', $job), ['status' => 'done', 'repair_cost' => 3000, 'note' => 'Ready'])->assertOk();
        $this->actingAs($this->cashier)->postJson(route('jobs.email', $job), ['type' => 'update'])->assertOk();

        Mail::assertSent(JobUpdateMail::class, function (JobUpdateMail $mail): bool {
            $mail->assertSeeInText('Job Done');
            $mail->assertSeeInText('Repair cost: Rs. 3,000');

            return $mail->hasSubject('Update on your job JOB-00007 - M-Fixpro');
        });

        $noEmail = Job::factory()->create(['customer_email' => '']);

        $this->actingAs($this->cashier)
            ->postJson(route('jobs.email', $noEmail), ['type' => 'received'])
            ->assertJsonValidationErrors(['email' => 'This job has no customer email address.']);
    }

    public function test_job_email_refuses_a_stored_address_that_lists_several_recipients(): void
    {
        Mail::fake();

        $job = Job::factory()->create(['customer_email' => 'nimal@example.com, boss@example.com']);

        $this->actingAs($this->cashier)
            ->postJson(route('jobs.email', $job), ['type' => 'received'])
            ->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_the_export_lists_the_filtered_jobs_with_their_full_history(): void
    {
        $job = Job::factory()->create(['job_no' => 'JOB-00042']);
        app(JobService::class)->updateStatus($job, ['status' => 'ongoing', 'note' => 'Diagnosing'], $this->cashier);

        $csv = $this->actingAs($this->cashier)->get(route('jobs.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('JOB-00042', $csv);
        $this->assertStringContainsString('Ongoing Job · Diagnosing · by Kasun Perera', $csv);
    }

    public function test_billable_lines_follow_the_repair_cost_services_and_advance_rules(): void
    {
        $withServices = Job::factory()->make([
            'services' => [['name' => 'Replace SSD', 'price' => 4000, 'chargeType' => 'paid'], ['name' => 'Cleaning', 'price' => 0, 'chargeType' => 'free', 'freeReason' => 'Loyalty']],
            'repair_cost' => 6000,
            'advance_paid' => 2000,
        ]);
        $this->assertSame([['Replace SSD', 4000], ['Cleaning', 0], ['Other repair charges', 2000], ['Less: advance paid', -2000]], $this->lineSummary($withServices));

        $cheaper = Job::factory()->make(['services' => [['name' => 'Screen', 'price' => 3000, 'chargeType' => 'paid']], 'repair_cost' => 2500, 'advance_paid' => 0]);
        $this->assertSame([['Screen', 3000], ['Repair cost adjustment', -500]], $this->lineSummary($cheaper));

        $estimateOnly = Job::factory()->make(['services' => [], 'repair_cost' => null, 'estimated_cost' => 1500, 'advance_paid' => 5000]);
        $this->assertSame([['Repair charge', 1500], ['Less: advance paid', -1500]], $this->lineSummary($estimateOnly));
    }

    public function test_a_finished_job_is_billed_at_the_pos_with_the_advance_deducted_and_becomes_delivered(): void
    {
        $shift = app(ShiftService::class)->open($this->cashier, 0, null);

        $showUrl = $this->actingAs($this->cashier)
            ->postJson(route('jobs.store'), [...$this->jobPayload(), 'advance_paid' => 2000])
            ->assertCreated()
            ->json('showUrl');
        $job = Job::query()->sole();

        $this->actingAs($this->cashier)->postJson(route('jobs.status', $job), ['status' => 'done', 'repair_cost' => 6000])->assertOk();

        $this->actingAs($this->cashier)
            ->getJson(route('api.jobs.billable', ['q' => '1']))
            ->assertOk()
            ->assertJsonPath('data.0.job_no', 'JOB-00001')
            ->assertJsonPath('data.0.lines.2.name', 'Other repair charges')
            ->assertJsonPath('data.0.lines.3.price', -2000);

        // 4,000 (SSD) + 0 (free cleaning) + 2,000 other charges − 2,000 advance.
        $response = $this->actingAs($this->cashier)
            ->postJson(route('sales.store'), [
                'job_id' => $job->id,
                'payments' => [['method' => 'cash']],
                'expected_total' => 4000,
            ])
            ->assertCreated();

        $this->assertStringContainsString('Job: JOB-00001', $response->json('billHtml'));
        $this->assertStringContainsString('- Rs. 2,000', $response->json('billHtml'));

        $sale = Sale::query()->sole();
        $this->assertSame([$job->id, 'JOB-00001', 'Nimal Fernando', $job->customer_id, '4000.00'], [
            $sale->job_id, $sale->job_no, $sale->customer_name, $sale->customer_id, $sale->total_amount,
        ]);
        $this->assertSame(['Replace SSD', 'Cleaning', 'Other repair charges', 'Less: advance paid'], array_column($sale->services, 'name'));
        $this->assertSame(['job', 'JOB-00001', 'free', 'Loyalty'], [$sale->services[1]['source'], $sale->services[1]['jobNo'], $sale->services[1]['chargeType'], $sale->services[1]['freeReason']]);

        $job->refresh();
        $this->assertSame('delivered', $job->status);
        $this->assertNotNull($job->date_returned);
        $this->assertSame('Delivered & billed via POS — Invoice INV-00001', $job->statusHistory()->latest('id')->first()->note);
        $this->assertSame('4000.00', $shift->fresh()->cash_sales_total);

        $this->actingAs($this->cashier)->getJson($showUrl)->assertOk()->assertJsonPath('job.status', 'delivered');
        $this->actingAs($this->cashier)->getJson(route('api.jobs.billable'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_card_surcharge_applies_to_paid_job_lines_but_not_free_or_advance_lines(): void
    {
        app(ShiftService::class)->open($this->cashier, 0, null);
        $job = Job::factory()->create([
            'status' => 'done',
            'services' => [['name' => 'Battery', 'price' => 1000, 'chargeType' => 'paid'], ['name' => 'Check-up', 'price' => 0, 'chargeType' => 'free', 'freeReason' => 'Warranty']],
            'advance_paid' => 500,
        ]);

        // 1,000 × 1.10 = 1,100; free 0; advance −500 unchanged → 600.
        $this->actingAs($this->cashier)
            ->postJson(route('sales.store'), [
                'job_id' => $job->id,
                'payments' => [['method' => 'card']],
                'card_charge_percent' => 10,
                'expected_total' => 600,
            ])
            ->assertCreated();

        $sale = Sale::query()->sole();
        $this->assertSame(['600.00', '100.00', $job->customer_name], [$sale->total_amount, $sale->card_charge_amount, $sale->customer_name]);
        $this->assertSame([1100, 0, -500], array_column($sale->services, 'price'));
    }

    public function test_only_jobs_marked_job_done_can_be_billed(): void
    {
        app(ShiftService::class)->open($this->cashier, 0, null);
        $job = Job::factory()->create(['job_no' => 'JOB-00009', 'status' => 'ongoing', 'estimated_cost' => 1000]);

        $this->actingAs($this->cashier)
            ->getJson(route('api.jobs.billable', ['q' => '9']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->cashier)
            ->postJson(route('sales.store'), ['job_id' => $job->id, 'payments' => [['method' => 'cash']], 'expected_total' => 1000])
            ->assertJsonValidationErrors(['job_id' => "JOB-00009 can't be billed — it is Ongoing Job. Only jobs marked Job Done can be billed."]);

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame('ongoing', $job->fresh()->status);
    }

    /**
     * @return list<array{0: string, 1: float|int}>
     */
    private function lineSummary(Job $job): array
    {
        return array_map(fn (array $line): array => [$line['name'], $line['price']], JobService::billableLines($job));
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(): array
    {
        return [
            'customer_name' => 'Nimal Fernando',
            'customer_phone' => '0771234567',
            'customer_email' => 'Nimal@Example.com',
            'device_type' => 'Laptop',
            'brand' => 'Dell',
            'model' => 'Inspiron 15',
            'fault_description' => 'No display',
            'services' => [
                ['name' => 'Replace SSD', 'chargeType' => 'paid', 'price' => 4000],
                ['name' => 'Cleaning', 'chargeType' => 'free', 'price' => '', 'freeReason' => 'Loyalty'],
                ['name' => '', 'chargeType' => 'paid', 'price' => '', 'freeReason' => ''],
            ],
            'estimated_cost' => 5000,
        ];
    }
}
