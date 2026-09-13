<?php

namespace Tests\Feature;

use App\Models\AffectedFamily;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\IncidentLocation;
use App\Models\ResourceRecommendation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_page_can_be_filtered_per_barangay_with_family_assistance_rows(): void
    {
        $user = $this->createMdrrmoUser();
        $asbang = $this->createBarangay('Asbang');
        $saub = $this->createBarangay('Saub');

        $asbangReport = $this->createReport($user, $asbang, 'Flood');
        $this->createFamily($asbangReport, 'Juan Dela Cruz', 4, 'severe');
        $this->createFamily($asbangReport, 'Ana Reyes', 3, 'minor');
        $this->createRecommendation($user, $asbang, $asbangReport, 10, 5000, 4);

        $saubReport = $this->createReport($user, $saub, 'Landslide');
        $this->createFamily($saubReport, 'Maria Santos', 5);
        $this->createRecommendation($user, $saub, $saubReport, 4, 2500, 2);

        $response = $this
            ->actingAs($user)
            ->withSession(['role' => 'mdrrmo'])
            ->get(route('reports.print', ['barangay_id' => $asbang->barangay_id]));

        $response
            ->assertOk()
            ->assertSee('Asbang Assistance List')
            ->assertSee('Affected Family Name')
            ->assertSee('Severity')
            ->assertSee('Food Packs')
            ->assertSee('Medical Kits')
            ->assertSee('0 medical kits')
            ->assertSee('Money')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Ana Reyes')
            ->assertSee('High')
            ->assertSee('Low')
            ->assertSee('Php 4,000.00')
            ->assertSee('Php 1,000.00')
            ->assertDontSee('4 medical kits')
            ->assertDontSee('Php 2,500.00')
            ->assertDontSee('Maria Santos')
            ->assertDontSee('Saub | Landslide');
    }

    public function test_print_page_allocates_medical_kits_only_to_families_with_medical_descriptions(): void
    {
        $user = $this->createMdrrmoUser();
        $barangay = $this->createBarangay('Sampaguita');
        $report = $this->createReport($user, $barangay, 'Flood', 'Injured one person');

        $this->createFamily($report, 'Bryan barz', 2, 'minor', 'Injured one person', 'Returned Home', '06464683');
        $this->createFamily($report, 'Ryan beng', 4, 'severe', 'House got sweep by the flood', 'In Evacuation Center', '06465683');
        $this->createFamily($report, 'Bryan youan', 5, 'moderate', 'House got a water', 'In Evacuation Center', '0616838');
        $this->createRecommendation($user, $barangay, $report, 6, 5250, 2);

        $response = $this
            ->actingAs($user)
            ->withSession(['role' => 'mdrrmo'])
            ->get(route('reports.print', ['barangay_id' => $barangay->barangay_id]));

        $response
            ->assertOk()
            ->assertSee('2 medical kits')
            ->assertSeeInOrder(['Bryan barz', 'Returned Home', 'Low', '1', '2', 'Php 750.00'])
            ->assertSeeInOrder(['Ryan beng', 'In Evacuation Center', 'High', '3', '0', 'Php 3,000.00'])
            ->assertSeeInOrder(['Bryan youan', 'In Evacuation Center', 'Medium', '2', '0', 'Php 1,500.00']);
    }

    private function createMdrrmoUser(): User
    {
        $role = Role::create(['role_name' => 'mdrrmo']);

        return User::create([
            'role_id' => $role->role_id,
            'full_name' => 'MDRRMO Officer',
            'email' => 'mdrrmo-print@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    private function createBarangay(string $name): Barangay
    {
        return Barangay::create([
            'barangay_name' => $name,
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);
    }

    private function createReport(User $user, Barangay $barangay, string $disasterType, string $description = 'Printable test report.'): DamageReport
    {
        $location = IncidentLocation::create([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => '6.688099',
            'longitude' => '125.166607',
            'road_segment' => 'Main Road',
            'sitio_purok' => 'Purok 1',
        ]);

        return DamageReport::create([
            'user_id' => $user->user_id,
            'location_id' => $location->location_id,
            'disaster_type' => $disasterType,
            'description' => $description,
            'damage_severity' => 'moderate',
            'affected_families' => 2,
            'affected_structures' => 1,
            'incident_datetime' => '2026-07-10 08:30:00',
            'status' => 'validated',
            'created_at' => now(),
        ]);
    }

    private function createFamily(
        DamageReport $report,
        string $name,
        int $members,
        ?string $severity = null,
        ?string $description = null,
        string $evacuationStatus = 'Evacuated',
        string $contactNumber = '09123456789',
    ): void
    {
        AffectedFamily::create([
            'report_id' => $report->report_id,
            'family_head_name' => $name,
            'household_members' => $members,
            'contact_number' => $contactNumber,
            'evacuation_status' => $evacuationStatus,
            'description' => $description,
            'damage_severity' => $severity,
            'created_at' => now(),
        ]);
    }

    private function createRecommendation(User $user, Barangay $barangay, DamageReport $report, int $foodPacks, float $cashAssistance, int $medicineKits): void
    {
        ResourceRecommendation::create([
            'barangay_id' => $barangay->barangay_id,
            'report_id' => $report->report_id,
            'generated_by' => $user->user_id,
            'cash_assistance' => $cashAssistance,
            'food_packs' => $foodPacks,
            'medicine_kits' => $medicineKits,
            'generated_at' => now(),
        ]);
    }
}
