<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FieldOfficerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_officer_is_redirected_to_the_reporting_dashboard_after_login(): void
    {
        $fieldOfficer = $this->createUserForRole('field_officer', 'field@example.com', 'Field Officer');

        $response = $this->post(route('login.store'), [
            'email' => $fieldOfficer->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('field-officer.dashboard'));
    }

    public function test_validator_is_redirected_to_the_validation_queue_after_login(): void
    {
        $validator = $this->createUserForRole('validator', 'validator@example.com', 'Validation Officer');

        $response = $this->post(route('login.store'), [
            'email' => $validator->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('validation.index'));
    }

    public function test_field_officer_can_submit_a_disaster_report_from_the_web_dashboard(): void
    {
        $barangay = Barangay::create([
            'barangay_name' => 'Asbang',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);
        $fieldOfficer = $this->createUserForRole('field_officer', 'field@example.com', 'Field Officer');

        $response = $this
            ->actingAs($fieldOfficer)
            ->withSession(['role' => 'field_officer'])
            ->post(route('field-officer.reports.store'), [
                'barangay_id' => $barangay->barangay_id,
                'purok' => 'Purok 1',
                'description' => 'Floodwater entered several homes.',
                'disasterType' => 'Flood',
                'severity' => 'moderate',
                'affectedStructures' => 5,
                'reportDate' => '2026-07-08',
                'latitude' => '6.688099',
                'longitude' => '125.166607',
                'families' => [
                    [
                        'familyHeadName' => 'Juan Dela Cruz',
                        'householdMembers' => 4,
                        'contactNumber' => '09123456789',
                        'evacuationStatus' => 'Evacuated',
                        'description' => 'Roof damage and standing water.',
                        'severity' => 'moderate',
                        'latitude' => '6.688199',
                        'longitude' => '125.166707',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('field-officer.dashboard'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('disaster_reports', [
            'user_id' => $fieldOfficer->user_id,
            'disaster_type' => 'Flood',
            'damage_severity' => 'moderate',
            'affected_families' => 1,
            'affected_structures' => 5,
            'status' => 'pending',
        ]);
    }

    public function test_validator_cannot_submit_through_the_field_officer_route(): void
    {
        $validator = $this->createUserForRole('validator', 'validator@example.com', 'Validation Officer');

        $this
            ->actingAs($validator)
            ->withSession(['role' => 'validator'])
            ->post(route('field-officer.reports.store'))
            ->assertForbidden();
    }

    private function createUserForRole(string $roleName, string $email, string $fullName): User
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);

        return User::create([
            'role_id' => $role->role_id,
            'full_name' => $fullName,
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }
}
