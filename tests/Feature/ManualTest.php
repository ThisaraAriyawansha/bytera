<?php

namespace Tests\Feature;

use App\Http\Controllers\ManualController;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_guests_can_read_the_manual_and_search_engines_are_told_not_to_index_it(): void
    {
        $response = $this->get('/manual')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('M-Fixpro POS')
            ->assertSee('User Manual')
            ->assertSee('Open the system')
            ->assertSee('Staff guide')
            ->assertSee('How to use M-Fixpro POS');

        foreach (ManualController::SECTIONS as $id => $title) {
            $response->assertSee('id="'.$id.'"', false)->assertSee('href="#'.$id.'"', false)->assertSee($title);
        }
    }

    public function test_the_manual_uses_steps_bullets_tips_warnings_flows_and_an_faq(): void
    {
        $this->get('/manual')
            ->assertSee('manual-steps', false)
            ->assertSee('manual-bullets', false)
            ->assertSee('Tip:')
            ->assertSee('Warning:')
            ->assertSeeInOrder(['Job Pending', 'Ongoing Job', 'Job Done', 'Delivered'])
            ->assertSeeInOrder(['GRN', 'Stock Transfer', 'Sale'])
            ->assertSeeInOrder(['Morning', 'During the day', 'Closing'])
            ->assertSee('<details', false);
    }

    public function test_signed_in_staff_can_read_it_too_and_it_shows_the_shop_name(): void
    {
        ShopSetting::query()->firstOrFail()->update(['name' => 'Galle Fixers']);

        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->get(route('manual'))
            ->assertOk()
            ->assertSee('How to use Galle Fixers POS');
    }
}
