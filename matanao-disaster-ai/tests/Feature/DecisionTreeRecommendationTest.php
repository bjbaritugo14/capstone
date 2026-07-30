<?php

namespace Tests\Feature;

use App\Models\AffectedFamily;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\IncidentLocation;
use App\Models\Role;
use App\Models\User;
use App\Services\DecisionTreeRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DecisionTreeRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_decision_tree_uses_and_explains_all_declared_inputs(): void
    {
        $role = Role::create(['role_name' => 'field_officer']);
        $user = User::create([
            'role_id' => $role->role_id,
            'full_name' => 'Field Officer',
            'email' => 'field@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
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
            'description' => 'An injured family was evacuated after their home was destroyed.',
            'damage_severity' => 'severe',
            'affected_families' => 1,
            'affected_structures' => 1,
            'incident_datetime' => now(),
            'status' => 'validated',
            'created_at' => now(),
        ]);
        AffectedFamily::create([
            'report_id' => $report->report_id,
            'family_head_name' => 'Juan Dela Cruz',
            'household_members' => 5,
            'evacuation_status' => 'Evacuated',
            'created_at' => now(),
        ]);

        $result = app(DecisionTreeRecommendationService::class)->generate($report);

        $this->assertSame(3, $result['food_packs']);
        $this->assertSame(3, $result['medicine_kits']);
        $this->assertSame(3300.0, $result['cash_assistance']);
        $this->assertSame('Flood', $result['inputs']['disaster_type']);
        $this->assertSame('Asbang', $result['inputs']['barangay']);
        $this->assertSame(1, $result['inputs']['affected_families']);
        $this->assertSame(5, $result['inputs']['household_members']);
        $this->assertSame(1, $result['inputs']['affected_structures']);
        $this->assertStringContainsString('Flood response for Barangay Asbang', $result['basis']);
        $this->assertStringContainsString('medical needs', $result['basis']);
        $this->assertStringContainsString('major structural damage', $result['basis']);
    }
}
