<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\AccidentImage;
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
            'personFirstName' => 'Juan',
            'personLastName' => 'Dela Cruz',
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
            'personFirstName' => 'Juan',
            'personLastName' => 'Dela Cruz',
            'description' => 'Updated after validator remarks.',
            'vehiclesInvolved' => 2,
            'injuredCount' => 2,
            'fatalityCount' => 0,
            'incidentDate' => '2026-07-15',
            'latitude' => '6.688199',
            'longitude' => '125.166707',
            'involvedPersons' => [
                [
                    'firstName' => 'Juan',
                    'lastName' => 'Dela Cruz',
                    'role' => 'driver',
                    'contactNumber' => '09123456789',
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'recorded')
            ->assertJsonPath('personFirstName', 'Juan')
            ->assertJsonPath('personLastName', 'Dela Cruz')
            ->assertJsonPath('personName', 'Juan Dela Cruz')
            ->assertJsonPath('involvedPersons.0.firstName', 'Juan')
            ->assertJsonPath('involvedPersons.0.lastName', 'Dela Cruz')
            ->assertJsonPath('involvedPersons.0.personName', 'Juan Dela Cruz')
            ->assertJsonPath('validationRemarks', '');

        $this->assertDatabaseHas('vehicular_accidents', [
            'accident_id' => $accident->accident_id,
            'status' => 'recorded',
            'description' => 'Updated after validator remarks.',
            'vehicle_type' => 'Tricycle',
            'involved_person_first_name' => 'Juan',
            'involved_person_last_name' => 'Dela Cruz',
            'injured_count' => 2,
        ]);
        $this->assertDatabaseHas('accident_involved_persons', [
            'accident_id' => $accident->accident_id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'person_name' => 'Juan Dela Cruz',
        ]);
    }

    public function test_mobile_accident_photo_paths_are_public_urls_but_updates_keep_storage_paths(): void
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
            'involved_person_first_name' => 'Juan',
            'involved_person_last_name' => 'Dela Cruz',
            'description' => 'Two motorcycles collided.',
            'vehicles_involved' => 2,
            'injured_count' => 1,
            'fatality_count' => 0,
            'incident_datetime' => '2026-07-14 00:00:00',
            'status' => 'recorded',
            'created_at' => now(),
        ]);

        AccidentImage::create([
            'accident_id' => $accident->accident_id,
            'image_path' => 'accident-photos/scene.jpg',
        ]);

        Sanctum::actingAs($user);

        $photoUrl = $this->getJson('/api/vehicular-accidents')
            ->assertOk()
            ->assertJsonPath('0.photos.0', url('storage/accident-photos/scene.jpg'))
            ->json('0.photos.0');

        $this->putJson("/api/vehicular-accidents/{$accident->accident_id}", [
            'barangay' => 'Asbang',
            'purok' => 'Purok 1',
            'roadSegment' => 'National Highway',
            'accidentType' => 'Collision',
            'vehicleType' => 'Motorcycle',
            'personFirstName' => 'Juan',
            'personLastName' => 'Dela Cruz',
            'description' => 'Updated accident details.',
            'vehiclesInvolved' => 2,
            'injuredCount' => 1,
            'fatalityCount' => 0,
            'incidentDate' => '2026-07-15',
            'latitude' => '6.688099',
            'longitude' => '125.166607',
            'existing_photos' => [$photoUrl],
        ])->assertOk()
            ->assertJsonPath('photos.0', url('storage/accident-photos/scene.jpg'));

        $this->assertDatabaseHas('accident_images', [
            'accident_id' => $accident->accident_id,
            'image_path' => 'accident-photos/scene.jpg',
        ]);

        $this->assertDatabaseMissing('accident_images', [
            'image_path' => $photoUrl,
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
