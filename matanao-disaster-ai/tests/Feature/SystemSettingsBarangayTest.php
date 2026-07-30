<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemSettingsBarangayTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_settings_barangay_list_requires_click_before_edit_form_appears(): void
    {
        $admin = $this->createAdminUser();

        $firstBarangay = Barangay::create([
            'barangay_name' => 'Asbang',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);

        $secondBarangay = Barangay::create([
            'barangay_name' => 'Saub',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'inactive',
        ]);

        $response = $this
            ->actingAs($admin)
            ->withSession(['role' => 'admin'])
            ->get(route('admin.settings'));

        $response
            ->assertOk()
            ->assertSee($firstBarangay->barangay_name)
            ->assertSee($secondBarangay->barangay_name)
            ->assertSee('Click a barangay to open a separate page before its edit form appears.')
            ->assertSee('Recommendation Configuration')
            ->assertSee('Add Barangay Option')
            ->assertDontSee('Save Barangay')
            ->assertDontSee('Edit Barangay')
            ->assertDontSee('Save System Settings')
            ->assertDontSee('Create Barangay');
    }

    public function test_recommendation_configuration_is_opened_on_a_separate_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this
            ->actingAs($admin)
            ->withSession(['role' => 'admin'])
            ->get(route('admin.settings.recommendations'));

        $response
            ->assertOk()
            ->assertSee('Recommendation Configuration')
            ->assertSee('Save System Settings')
            ->assertSee('Back to System Settings');
    }

    public function test_add_barangay_option_is_opened_on_a_separate_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this
            ->actingAs($admin)
            ->withSession(['role' => 'admin'])
            ->get(route('admin.settings.barangays.create'));

        $response
            ->assertOk()
            ->assertSee('Add Barangay Option')
            ->assertSee('Create Barangay')
            ->assertSee('Back to System Settings');
    }

    public function test_barangay_edit_form_is_shown_on_the_barangay_detail_page(): void
    {
        $admin = $this->createAdminUser();

        $barangay = Barangay::create([
            'barangay_name' => 'Asbang',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($admin)
            ->withSession(['role' => 'admin'])
            ->get(route('admin.settings.barangays.show', $barangay));

        $response
            ->assertOk()
            ->assertSee($barangay->barangay_name)
            ->assertSee('Edit Barangay')
            ->assertSee('Save Barangay')
            ->assertSee('Back to System Settings');
    }

    private function createAdminUser(): User
    {
        $role = Role::create(['role_name' => 'admin']);

        return User::create([
            'role_id' => $role->role_id,
            'full_name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }
}
