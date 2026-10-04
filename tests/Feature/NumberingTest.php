<?php

namespace Tests\Feature;

use App\Models\Counter;
use App\Models\Job;
use App\Services\Numbering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NumberingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_next_increments_counter_and_zero_pads(): void
    {
        $numbers = DB::transaction(fn () => [
            Numbering::next('invoice', 'INV'),
            Numbering::next('invoice', 'INV'),
        ]);

        $this->assertSame(['INV-00001', 'INV-00002'], $numbers);
        $this->assertSame(2, Counter::find('invoice')->value);
        $this->assertSame(0, Counter::find('quotation')->value);
    }

    public function test_preview_job_no_does_not_consume_the_counter(): void
    {
        $this->assertSame('JOB-00001', Numbering::previewJobNo());
        $this->assertSame('JOB-00001', Numbering::previewJobNo());
        $this->assertSame(0, Counter::find('job')->value);
    }

    public function test_custom_job_no_is_uppercased_and_moves_counter_up(): void
    {
        $jobNo = DB::transaction(fn () => Numbering::nextJobNo(' job-00123 '));

        $this->assertSame('JOB-00123', $jobNo);
        $this->assertSame('JOB-00124', Numbering::previewJobNo());
        $this->assertSame('JOB-00124', DB::transaction(fn () => Numbering::nextJobNo()));
    }

    public function test_custom_job_no_lower_than_counter_leaves_counter_alone(): void
    {
        Counter::whereKey('job')->update(['value' => 50]);

        $this->assertSame('JOB-00010', DB::transaction(fn () => Numbering::nextJobNo('JOB-00010')));
        $this->assertSame(50, Counter::find('job')->value);
    }

    public function test_custom_job_no_already_in_use_is_rejected(): void
    {
        Job::factory()->create(['job_no' => 'JOB-00123']);

        try {
            DB::transaction(fn () => Numbering::nextJobNo('job-00123'));
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Job number JOB-00123 is already in use.'], $exception->errors()['job_no']);
        }
    }
}
