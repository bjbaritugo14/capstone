<?php

namespace Tests\Feature;

use App\Models\AffectedFamily;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\IncidentLocation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AffectedFamilyIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_affected_family_index_page_lists_barangays_without_showing_family_records(): void
    {
        $user = $this->createMdrrmoUser();

        [$firstBarangay, $secondBarangay] = $this->seedBarangayReports();

        $response = $this
            ->actingAs($user)
            ->withSession(['role' => 'mdrrmo'])
            ->get(route('affected-families.index'));

        $response
            ->assertOk()
            ->assertSee($firstBarangay->barangay_name)
            ->assertSee($secondBarangay->barangay_name)
            ->assertSee('Each click opens a new page for that barangay.')
            ->assertDontSee('Juan Dela Cruz')
            ->assertDontSee('Maria Santos');
    }

    public function test_affected_family_barangay_page_shows_only_the_selected_barangays_data(): void
    {
        $user = $this->createMdrrmoUser();

        [$firstBarangay, $secondBarangay] = $this->seedBarangayReports();

        $response = $this
            ->actingAs($user)
            ->withSession(['role' => 'mdrrmo'])
            ->get(route('affected-families.show', ['barangay' => $firstBarangay->barangay_id]));

        $response
            ->assertOk()
            ->assertSee($firstBarangay->barangay_name)
            ->assertSee('Juan Dela Cruz')
            ->assertDontSee('Maria Santos')
            ->assertSee('Back to Barangays');
    }

    private function createMdrrmoUser(): User
    {
        $role = Role::create(['role_name' => 'mdrrmo']);

        return User::create([
            'role_id' => $role->role_id,
            'full_name' => 'MDRRMO Officer',
            'email' => 'mdrrmo@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    private function seedBarangayReports(): array
    {
        $firstBarangay = Barangay::create([
            'barangay_name' => 'Asbang',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
        ]);

        $secondBarangay = Barangay::create([
            'barangay_name' => 'Saub',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
        ]);

        $firstLocation = IncidentLocation::create([
            'barangay_id' => $firstBarangay->barangay_id,
            'latitude' => '6.688099',
            'longitude' => '125.166607',
            'road_segment' => 'Main Road',
            'sitio_purok' => 'Purok 1',
        ]);

        $secondLocation = IncidentLocation::create([
            'barangay_id' => $secondBarangay->barangay_id,
            'latitude' => '6.689099',
            'longitude' => '125.167607',
            'road_segment' => 'Hill Road',
            'sitio_purok' => 'Purok 2',
        ]);

        $firstReport = DamageReport::create([
            'user_id' => 1,
            'location_id' => $firstLocation->location_id,
            'disaster_type' => 'Flood',
            'description' => 'Flooded homes near the creek.',
            'damage_severity' => 'moderate',
            'affected_families' => 1,
            'affected_structures' => 1,
            'incident_datetime' => '2026-07-10 08:30:00',
            'status' => 'validated',
        ]);

        $secondReport = DamageReport::create([
            'user_id' => 1,
            'location_id' => $secondLocation->location_id,
            'disaster_type' => 'Landslide',
            'description' => 'Slope collapse near the road.',
            'damage_severity' => 'severe',
            'affected_families' => 1,
            'affected_structures' => 1,
            'incident_datetime' => '2026-07-11 09:45:00',
            'status' => 'validated',
        ]);

        AffectedFamily::create([
            'report_id' => $firstReport->report_id,
            'family_head_name' => 'Juan Dela Cruz',
            'household_members' => 4,
            'contact_number' => '09123456789',
            'evacuation_status' => 'Evacuated',
        ]);

        AffectedFamily::create([
            'report_id' => $secondReport->report_id,
            'family_head_name' => 'Maria Santos',
            'household_members' => 5,
            'contact_number' => '09987654321',
            'evacuation_status' => 'Staying with relatives',
        ]);

        return [$firstBarangay, $secondBarangay];
    }
}
