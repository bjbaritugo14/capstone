<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\AffectedFamily;
use App\Models\DamageReport;
use App\Models\IncidentLocation;
use App\Models\ReportImage;
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
                    'firstName' => 'Juan',
                    'lastName' => 'Dela Cruz',
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
                    'firstName' => 'Juan',
                    'lastName' => 'Dela Cruz',
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
            ->assertJsonPath('families.0.firstName', 'Juan')
            ->assertJsonPath('families.0.lastName', 'Dela Cruz')
            ->assertJsonPath('families.0.familyHeadName', 'Juan Dela Cruz')
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

    public function test_mobile_report_photo_paths_are_public_urls_but_updates_keep_storage_paths(): void
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
            'description' => 'Flood response needed.',
            'damage_severity' => 'moderate',
            'affected_families' => 1,
            'affected_structures' => 1,
            'incident_datetime' => '2026-07-14 00:00:00',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $family = AffectedFamily::create([
            'report_id' => $report->report_id,
            'family_head_name' => 'Juan Dela Cruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'household_members' => 4,
            'latitude' => '6.688199',
            'longitude' => '125.166707',
        ]);

        ReportImage::create([
            'report_id' => $report->report_id,
            'family_id' => $family->family_id,
            'image_path' => 'report-photos/family.jpg',
        ]);

        Sanctum::actingAs($user);

        $photoUrl = $this->getJson('/api/reports')
            ->assertOk()
            ->assertJsonPath('0.families.0.photos.0', url('storage/report-photos/family.jpg'))
            ->json('0.families.0.photos.0');

        $this->putJson("/api/reports/{$report->report_id}", [
            'barangay' => 'Asbang',
            'purok' => 'Purok 1',
            'description' => 'Updated after photo review.',
            'disasterType' => 'Flood',
            'severity' => 'moderate',
            'affectedStructures' => 1,
            'reportDate' => '2026-07-15',
            'latitude' => '6.688199',
            'longitude' => '125.166707',
            'families' => [
                [
                    'firstName' => 'Juan',
                    'lastName' => 'Dela Cruz',
                    'householdMembers' => 4,
                    'description' => 'Updated family details.',
                    'severity' => 'moderate',
                    'latitude' => '6.688199',
                    'longitude' => '125.166707',
                ],
            ],
            'existing_family_photos_0' => [$photoUrl],
        ])->assertOk()
            ->assertJsonPath('families.0.photos.0', url('storage/report-photos/family.jpg'));

        $this->assertDatabaseHas('report_images', [
            'report_id' => $report->report_id,
            'image_path' => 'report-photos/family.jpg',
        ]);

        $this->assertDatabaseMissing('report_images', [
            'image_path' => $photoUrl,
        ]);
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
