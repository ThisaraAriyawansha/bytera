<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_is_saved_with_its_custom_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('services.store'), [
                'name' => 'Laptop Screen Replacement',
                'default_price' => 4500,
                'description' => 'Labour only',
                'active' => true,
                'custom_fields' => [
                    ['label' => ' Model Number ', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. X515', 'options' => []],
                    ['label' => 'Screen size', 'type' => 'select', 'required' => false, 'placeholder' => '', 'options' => ['13 inch', ' 14 inch', '', '15.6 inch']],
                    ['label' => 'Tested', 'type' => 'checkbox', 'required' => false, 'placeholder' => '', 'options' => ['ignored']],
                ],
            ])
            ->assertCreated();

        $service = Service::query()->sole();
        $fields = $service->custom_fields;

        $this->assertSame('4500.00', $service->default_price);
        $this->assertTrue($service->active);
        $this->assertCount(3, $fields);
        $this->assertSame(['Model Number', 'Screen size', 'Tested'], array_column($fields, 'label'));
        $this->assertSame(['13 inch', '14 inch', '15.6 inch'], $fields[1]['options']);
        $this->assertSame([], $fields[2]['options']);
        $this->assertTrue($fields[0]['required']);
        $this->assertNotEmpty($fields[0]['id']);
    }

    public function test_editing_keeps_existing_field_ids(): void
    {
        $service = Service::factory()->create([
            'custom_fields' => [['id' => 'abc123', 'label' => 'Model', 'type' => 'text', 'required' => false, 'placeholder' => '', 'options' => []]],
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('services.update', $service), [
                'name' => $service->name,
                'default_price' => 100,
                'active' => false,
                'custom_fields' => [['id' => 'abc123', 'label' => 'Model Number', 'type' => 'text', 'required' => true]],
            ])
            ->assertOk();

        $service->refresh();
        $this->assertFalse($service->active);
        $this->assertSame('abc123', $service->custom_fields[0]['id']);
        $this->assertSame('Model Number', $service->custom_fields[0]['label']);
    }

    public function test_custom_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('services.store'), [
                'name' => 'Data Recovery',
                'active' => true,
                'custom_fields' => [
                    ['label' => '', 'type' => 'text'],
                    ['label' => 'Drive', 'type' => 'select', 'options' => [' ', '']],
                    ['label' => 'drive', 'type' => 'colour'],
                ],
            ])
            ->assertJsonValidationErrors([
                'custom_fields.0.label' => 'Every custom field needs a label.',
                'custom_fields.1.options' => 'Add at least one option for this dropdown.',
                'custom_fields.2.label' => 'Each custom field needs a different label.',
                'custom_fields.2.type',
            ]);

        $this->assertDatabaseCount('services', 0);
    }

    public function test_search_matches_name_description_or_field_label(): void
    {
        Service::factory()->create(['name' => 'OS Installation', 'description' => null]);
        Service::factory()->create(['name' => 'Keyboard Replacement', 'description' => 'Genuine parts']);
        Service::factory()->create(['name' => 'Battery Swap', 'description' => null, 'custom_fields' => [
            ['id' => 'a', 'label' => 'Serial Number', 'type' => 'text', 'required' => false, 'placeholder' => '', 'options' => []],
        ]]);

        $user = User::factory()->create(['role' => 'Staff']);

        $this->actingAs($user)->get(route('services.index', ['search' => 'genuine']))
            ->assertSee('Keyboard Replacement')->assertDontSee('OS Installation')->assertDontSee('Battery Swap');

        $this->actingAs($user)->get(route('services.index', ['search' => 'serial']))
            ->assertSee('Battery Swap')->assertDontSee('Keyboard Replacement');
    }

    public function test_edit_and_delete_need_their_own_permissions(): void
    {
        $service = Service::factory()->create();
        $staff = User::factory()->create(['role' => 'Staff']);

        $this->actingAs($staff)
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee($service->name)
            ->assertDontSee('Add Service');

        $this->actingAs($staff)
            ->postJson(route('services.store'), ['name' => 'X', 'active' => true, 'custom_fields' => []])
            ->assertForbidden();
        $this->actingAs($staff)
            ->putJson(route('services.update', $service), ['name' => 'X', 'active' => true, 'custom_fields' => []])
            ->assertForbidden();
        $this->actingAs($staff)->delete(route('services.destroy', $service))->assertForbidden();

        $this->assertModelExists($service);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('services.destroy', $service))
            ->assertRedirect(route('services.index'));

        $this->assertModelMissing($service);
    }
}
