<?php

namespace Tests\Feature;

use App\Models\AffectedFamily;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\IncidentLocation;
use App\Models\ReportValidation;
use App\Models\ResourceRecommendation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DswdWorkflowAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dswd_affected_family_pages_show_only_validated_reports(): void
    {
        $dswdUser = $this->createUserForRole('dswd', 'dswd@example.com', 'DSWD Officer');
        $validator = $this->createUserForRole('validator', 'validator@example.com', 'Validation Officer');

        $validatedBarangay = $this->createBarangay('Asbang');
        $pendingOnlyBarangay = $this->createBarangay('Saub');

        $validatedReport = $this->createReport($validator, $validatedBarangay, 'validated', 'Juan Dela Cruz');
        $this->createValidation($validatedReport, $validator);

        $this->createReport($validator, $validatedBarangay, 'pending', 'Maria Santos');
        $this->createReport($validator, $pendingOnlyBarangay, 'pending', 'Pedro Reyes');

        $indexResponse = $this
            ->actingAs($dswdUser)
            ->withSession(['role' => 'mdrrmo'])
            ->get(route('affected-families.index'));

        $indexResponse
            ->assertOk()
            ->assertSessionHas('role', 'dswd')
            ->assertSee('review only MDRRMO-validated family data for DSWD coordination')
            ->assertSee('Asbang')
            ->assertDontSee('Saub');

        $showResponse = $this
            ->actingAs($dswdUser)
            ->withSession(['role' => 'mdrrmo'])
            ->get(route('affected-families.show', ['barangay' => $validatedBarangay->barangay_id]));

        $showResponse
            ->assertOk()
            ->assertSee('Validated affected family data for this barangay.')
            ->assertSee('Juan Dela Cruz')
            ->assertDontSee('Maria Santos')
            ->assertDontSee('Delete');
    }

    public function test_dswd_cannot_delete_affected_family_reports_even_with_a_spoofed_session_role(): void
    {
        $dswdUser = $this->createUserForRole('dswd', 'dswd-delete@example.com', 'DSWD Officer');
        $validator = $this->createUserForRole('validator', 'validator-delete@example.com', 'Validation Officer');
        $barangay = $this->createBarangay('Asbang');
        $report = $this->createReport($validator, $barangay, 'validated', 'Juan Dela Cruz');

        $response = $this
            ->actingAs($dswdUser)
            ->withSession(['role' => 'mdrrmo'])
            ->delete(route('affected-families.destroy', $report->report_id));

        $response->assertForbidden();

        $this->assertDatabaseHas('disaster_reports', [
            'report_id' => $report->report_id,
        ]);
    }

    public function test_dswd_recommendations_show_only_validated_recommendation_records(): void
    {
        $dswdUser = $this->createUserForRole('dswd', 'dswd-recommendations@example.com', 'DSWD Officer');
        $mdrrmoUser = $this->createUserForRole('mdrrmo', 'mdrrmo@example.com', 'MDRRMO Officer');
        $validator = $this->createUserForRole('validator', 'validator-recommendations@example.com', 'Validation Officer');

        $validatedBarangay = $this->createBarangay('Asbang');
        $pendingBarangay = $this->createBarangay('Saub');

        $validatedReport = $this->createReport($mdrrmoUser, $validatedBarangay, 'validated', 'Juan Dela Cruz');
        $this->createValidation($validatedReport, $validator);

        $pendingReport = $this->createReport($mdrrmoUser, $pendingBarangay, 'pending', 'Maria Santos');

        ResourceRecommendation::create([
            'barangay_id' => $validatedBarangay->barangay_id,
            'report_id' => $validatedReport->report_id,
            'generated_by' => $mdrrmoUser->user_id,
            'cash_assistance' => 5000,
            'food_packs' => 10,
            'medicine_kits' => 3,
            'generated_at' => now(),
        ]);

        ResourceRecommendation::create([
            'barangay_id' => $pendingBarangay->barangay_id,
            'report_id' => $pendingReport->report_id,
            'generated_by' => $mdrrmoUser->user_id,
            'cash_assistance' => 2500,
            'food_packs' => 4,
            'medicine_kits' => 1,
            'generated_at' => now(),
        ]);

        $response = $this
            ->actingAs($dswdUser)
            ->withSession(['role' => 'validator'])
            ->get(route('dswd.recommendations'));

        $response
            ->assertOk()
            ->assertSessionHas('role', 'dswd')
            ->assertSee('validated-only list for relief planning')
            ->assertSee('Asbang')
            ->assertSee('Validation Officer')
            ->assertDontSee('Saub')
            ->assertDontSee('Maria Santos');
    }

    public function test_non_validated_reports_cannot_generate_recommendations(): void
    {
        $mdrrmoUser = $this->createUserForRole('mdrrmo', 'mdrrmo-generate@example.com', 'MDRRMO Officer');
        $barangay = $this->createBarangay('Asbang');
        $report = $this->createReport($mdrrmoUser, $barangay, 'pending', 'Juan Dela Cruz');

        $response = $this
            ->actingAs($mdrrmoUser)
            ->withSession(['role' => 'mdrrmo'])
            ->post(route('recommendations.generate', $report));

        $response->assertForbidden();

        $this->assertDatabaseCount('recommendations', 0);
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

    private function createBarangay(string $name): Barangay
    {
        return Barangay::create([
            'barangay_name' => $name,
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
        ]);
    }

    private function createReport(User $user, Barangay $barangay, string $status, string $familyHeadName): DamageReport
    {
        static $sequence = 0;
        $sequence++;

        $location = IncidentLocation::create([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => (string) (6.688099 + ($sequence * 0.001)),
            'longitude' => (string) (125.166607 + ($sequence * 0.001)),
            'road_segment' => 'Main Road '.$sequence,
            'sitio_purok' => 'Purok '.$sequence,
        ]);

        $report = DamageReport::create([
            'user_id' => $user->user_id,
            'location_id' => $location->location_id,
            'disaster_type' => $status === 'validated' ? 'Flood' : 'Landslide',
            'description' => 'Test report for '.$familyHeadName,
            'damage_severity' => $status === 'validated' ? 'moderate' : 'minor',
            'affected_families' => 1,
            'affected_structures' => 1,
            'incident_datetime' => now(),
            'status' => $status,
            'created_at' => now(),
        ]);

        AffectedFamily::create([
            'report_id' => $report->report_id,
            'family_head_name' => $familyHeadName,
            'household_members' => 4,
            'contact_number' => '09123456789',
            'evacuation_status' => 'Evacuated',
            'created_at' => now(),
        ]);

        return $report;
    }

    private function createValidation(DamageReport $report, User $validator): void
    {
        ReportValidation::create([
            'report_id' => $report->report_id,
            'validated_by' => $validator->user_id,
            'validation_status' => 'validated',
            'remarks' => 'Validated for DSWD coordination.',
            'validated_at' => now(),
        ]);
    }
}
