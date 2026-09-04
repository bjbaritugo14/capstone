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

class MobileReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_report_submission_requires_a_valid_gps_coordinate(): void
    {
        $user = $this->createFieldUser();
        Barangay::create([
            'barangay_name' => 'Asbang',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/reports', [
            'barangay' => 'Asbang',
            'purok' => 'Purok 1',
            'description' => 'Flood response needed.',
            'disasterType' => 'Flood',
            'severity' => 'moderate',
            'reportDate' => '2026-07-14',
            'families' => [
                [
                    'familyHeadName' => 'Juan Dela Cruz',
                    'householdMembers' => 4,
                    'contactNumber' => '09123456789',
                    'evacuationStatus' => 'Evacuated',
                    'description' => 'Water entered the home.',
                    'severity' => 'moderate',
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_updating_a_returned_mobile_report_places_it_back_in_pending_status(): void
    {
        $user = $this->createFieldUser();
        $barangay = Barangay::create([
            'barangay_name' => 'Asbang',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);

        $location = IncidentLocation::create([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => '6.688099',
            'longitude' => '125.166607',
            'sitio_purok' => 'Purok 1',
        ]);

        $report = DamageReport::create([
            'user_id' => $user->user_id,
            'location_id' => $location->location_id,
            'disaster_type' => 'Flood',
            'description' => 'Initial description.',
            'damage_severity' => 'moderate',
            'affected_families' => 1,
            'affected_structures' => 1,
            'incident_datetime' => '2026-07-14 00:00:00',
            'status' => 'returned',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/reports/{$report->report_id}", [
            'barangay' => 'Asbang',
            'purok' => 'Purok 1',
            'description' => 'Updated after validator remarks.',
            'disasterType' => 'Flood',
            'severity' => 'moderate',
            'affectedStructures' => 7,
            'reportDate' => '2026-07-15',
            'families' => [
                [
                    'familyHeadName' => 'Juan Dela Cruz',
                    'householdMembers' => 4,
                    'contactNumber' => '09123456789',
                    'evacuationStatus' => 'Evacuated',
                    'description' => 'Updated family details.',
                    'severity' => 'moderate',
                    'latitude' => '6.688199',
                    'longitude' => '125.166707',
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('validationRemarks', '');

        $this->assertDatabaseHas('disaster_reports', [
            'report_id' => $report->report_id,
            'status' => 'pending',
            'description' => 'Updated after validator remarks.',
            'affected_structures' => 7,
        ]);
    }

    public function test_mobile_report_rejects_unrealistic_affected_structure_count(): void
    {
        $user = $this->createFieldUser();
        Barangay::create([
            'barangay_name' => 'Asbang',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/reports', [
            'barangay' => 'Asbang',
            'purok' => 'Purok 1',
            'description' => 'Flood response needed.',
            'disasterType' => 'Flood',
            'severity' => 'minor',
            'affectedStructures' => 9668272173,
            'reportDate' => '2026-09-03',
            'latitude' => '6.706258',
            'longitude' => '125.218819',
            'families' => [
                [
                    'familyHeadName' => 'Juan Kalo',
                    'householdMembers' => 2,
                    'contactNumber' => '09668272173',
                    'evacuationStatus' => 'Returned Home',
                    'description' => 'One of the members is injured.',
                    'severity' => 'minor',
                    'latitude' => '6.706258',
                    'longitude' => '125.218819',
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['affectedStructures']);

        $this->assertDatabaseCount('disaster_reports', 0);
    }

    private function createFieldUser(): User
    {
        $role = Role::create(['role_name' => 'field_officer']);

        return User::create([
            'role_id' => $role->role_id,
            'full_name' => 'Field Officer',
            'email' => 'field@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_at' => now(),
        ]);
    }
}
