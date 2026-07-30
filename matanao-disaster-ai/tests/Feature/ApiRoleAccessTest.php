<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\IncidentLocation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_field_officers_can_use_mobile_reporting_routes(): void
    {
        $fieldOfficer = $this->createUserForRole('field_officer', 'field@example.com');
        $dswd = $this->createUserForRole('dswd', 'dswd@example.com');

        Sanctum::actingAs($fieldOfficer);
        $this->getJson('/api/reports')->assertOk();

        Sanctum::actingAs($dswd);
        $this->getJson('/api/reports')->assertForbidden();
    }

    public function test_validator_can_validate_but_cannot_use_field_officer_reporting_routes(): void
    {
        $fieldOfficer = $this->createUserForRole('field_officer', 'field@example.com');
        $validator = $this->createUserForRole('validator', 'validator@example.com');
        $report = $this->createReport($fieldOfficer);

        Sanctum::actingAs($validator);

        $this->postJson("/api/assessments/{$report->report_id}/validate", [
            'status' => 'validated',
            'remarks' => 'Verified by the validation officer.',
        ])->assertOk();

        $this->getJson('/api/reports')->assertForbidden();

        $this->assertDatabaseHas('disaster_reports', [
            'report_id' => $report->report_id,
            'status' => 'validated',
        ]);
    }

    public function test_field_officer_cannot_call_validation_or_recommendation_apis(): void
    {
        $fieldOfficer = $this->createUserForRole('field_officer', 'field@example.com');
        $report = $this->createReport($fieldOfficer);

        Sanctum::actingAs($fieldOfficer);

        $this->postJson("/api/assessments/{$report->report_id}/validate", [
            'status' => 'validated',
        ])->assertForbidden();
        $this->getJson('/api/recommendations')->assertForbidden();
    }

    private function createUserForRole(string $roleName, string $email): User
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);

        return User::create([
            'role_id' => $role->role_id,
            'full_name' => ucwords(str_replace('_', ' ', $roleName)),
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    private function createReport(User $user): DamageReport
    {
        $barangay = Barangay::firstOrCreate([
            'barangay_name' => 'Asbang',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
        ], [
            'status' => 'active',
        ]);
        $location = IncidentLocation::create([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => '6.688099',
            'longitude' => '125.166607',
            'sitio_purok' => 'Purok 1',
        ]);

        return DamageReport::create([
            'user_id' => $user->user_id,
            'location_id' => $location->location_id,
            'disaster_type' => 'Flood',
            'description' => 'Floodwater entered homes.',
            'damage_severity' => 'moderate',
            'affected_families' => 2,
            'affected_structures' => 3,
            'incident_datetime' => now(),
            'status' => 'pending',
            'created_at' => now(),
        ]);
    }
}
