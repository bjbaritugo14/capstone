<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\IncidentLocation;
use App\Models\Role;
use App\Models\User;
use App\Models\VehicularAccident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileVehicularAccidentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_accident_submission_requires_a_valid_gps_coordinate(): void
    {
        $user = $this->createFieldUser();
        Barangay::create([
            'barangay_name' => 'Asbang',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/vehicular-accidents', [
            'barangay' => 'Asbang',
            'purok' => 'Purok 1',
            'roadSegment' => 'National Highway',
            'accidentType' => 'Collision',
            'vehicleType' => 'Motorcycle',
            'personName' => 'Juan Dela Cruz',
            'description' => 'Two motorcycles collided.',
            'vehiclesInvolved' => 2,
            'injuredCount' => 1,
            'fatalityCount' => 0,
            'incidentDate' => '2026-07-14',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_updating_a_returned_mobile_accident_places_it_back_in_recorded_status(): void
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
            'road_segment' => 'National Highway',
            'sitio_purok' => 'Purok 1',
        ]);

        $accident = VehicularAccident::create([
            'user_id' => $user->user_id,
            'location_id' => $location->location_id,
            'accident_type' => 'Collision',
            'vehicle_type' => 'Motorcycle',
            'involved_person_name' => 'Juan Dela Cruz',
            'description' => 'Initial accident description.',
            'vehicles_involved' => 2,
            'injured_count' => 1,
            'fatality_count' => 0,
            'incident_datetime' => '2026-07-14 00:00:00',
            'status' => 'returned',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/vehicular-accidents/{$accident->accident_id}", [
            'barangay' => 'Asbang',
            'purok' => 'Purok 2',
            'roadSegment' => 'National Highway',
            'accidentType' => 'Collision',
            'vehicleType' => 'Tricycle',
            'personName' => 'Juan Dela Cruz',
            'description' => 'Updated after validator remarks.',
            'vehiclesInvolved' => 2,
            'injuredCount' => 2,
            'fatalityCount' => 0,
            'incidentDate' => '2026-07-15',
            'latitude' => '6.688199',
            'longitude' => '125.166707',
            'involvedPersons' => [
                [
                    'personName' => 'Juan Dela Cruz',
                    'role' => 'driver',
                    'contactNumber' => '09123456789',
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'recorded')
            ->assertJsonPath('validationRemarks', '');

        $this->assertDatabaseHas('vehicular_accidents', [
            'accident_id' => $accident->accident_id,
            'status' => 'recorded',
            'description' => 'Updated after validator remarks.',
            'vehicle_type' => 'Tricycle',
            'injured_count' => 2,
        ]);
    }

    private function createFieldUser(): User
    {
        $role = Role::create(['role_name' => 'field_officer']);

        return User::create([
            'role_id' => $role->role_id,
            'full_name' => 'Field Officer',
            'email' => 'field-accident@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_at' => now(),
        ]);
    }
}
