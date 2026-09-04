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
        $this->assertSame(['minor' => 0, 'moderate' => 0, 'severe' => 1], $result['inputs']['severity_counts']);
        $this->assertSame(1, $result['inputs']['affected_families']);
        $this->assertSame(5, $result['inputs']['household_members']);
        $this->assertSame(1, $result['inputs']['affected_structures']);
        $this->assertTrue($result['inputs']['medical_needs']);
        $this->assertSame('full_assistance', $result['inputs']['output_scope']);
        $this->assertStringContainsString('Flood response for Barangay Asbang', $result['basis']);
        $this->assertStringContainsString('medical needs', $result['basis']);
        $this->assertStringContainsString('major structural damage', $result['basis']);
    }

    public function test_decision_tree_calculates_recommendations_from_affected_family_severity_counts(): void
    {
        $role = Role::create(['role_name' => 'field_officer']);
        $user = User::create([
            'role_id' => $role->role_id,
            'full_name' => 'Field Officer',
            'email' => 'mixed-family@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $barangay = Barangay::create([
            'barangay_name' => 'Poblacion',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);
        $location = IncidentLocation::create([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => '6.688099',
            'longitude' => '125.166607',
            'sitio_purok' => 'Purok 2',
        ]);
        $report = DamageReport::create([
            'user_id' => $user->user_id,
            'location_id' => $location->location_id,
            'disaster_type' => 'Other',
            'description' => 'Mixed family-level assessment.',
            'damage_severity' => 'minor',
            'affected_families' => 4,
            'affected_structures' => 0,
            'incident_datetime' => now(),
            'status' => 'validated',
            'created_at' => now(),
        ]);

        foreach ([
            ['Family Minor', 3, 'minor'],
            ['Family Moderate A', 6, 'moderate'],
            ['Family Moderate B', 8, 'moderate'],
            ['Family Severe', 11, 'severe'],
        ] as [$name, $members, $severity]) {
            AffectedFamily::create([
                'report_id' => $report->report_id,
                'family_head_name' => $name,
                'household_members' => $members,
                'damage_severity' => $severity,
                'created_at' => now(),
            ]);
        }

        $result = app(DecisionTreeRecommendationService::class)->generate($report);

        $this->assertSame(['minor' => 1, 'moderate' => 2, 'severe' => 1], $result['inputs']['severity_counts']);
        $this->assertSame('severe', $result['inputs']['damage_severity']);
        $this->assertSame(4, $result['inputs']['affected_families']);
        $this->assertSame(28, $result['inputs']['household_members']);
        $this->assertSame('high', $result['inputs']['priority']);
        $this->assertSame(6, $result['food_packs']);
        $this->assertSame(0, $result['medicine_kits']);
        $this->assertSame(4500.0, $result['cash_assistance']);
        $this->assertFalse($result['inputs']['medical_needs']);
        $this->assertStringContainsString('1 minor, 2 moderate, 1 severe', $result['basis']);
    }

    public function test_decision_tree_keeps_food_and_cash_for_local_medical_description_terms(): void
    {
        $role = Role::create(['role_name' => 'field_officer']);
        $user = User::create([
            'role_id' => $role->role_id,
            'full_name' => 'Field Officer',
            'email' => 'local-medical@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $barangay = Barangay::create([
            'barangay_name' => 'Buas',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);
        $location = IncidentLocation::create([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => '6.688099',
            'longitude' => '125.166607',
            'sitio_purok' => 'Purok 3',
        ]);
        $report = DamageReport::create([
            'user_id' => $user->user_id,
            'location_id' => $location->location_id,
            'disaster_type' => 'Other',
            'description' => 'Naangol ang bata ug kinahanglan ug tambal sa ospital.',
            'damage_severity' => 'moderate',
            'affected_families' => 2,
            'affected_structures' => 2,
            'incident_datetime' => now(),
            'status' => 'validated',
            'created_at' => now(),
        ]);

        AffectedFamily::create([
            'report_id' => $report->report_id,
            'family_head_name' => 'Local Family',
            'household_members' => 6,
            'damage_severity' => 'moderate',
            'created_at' => now(),
        ]);

        $result = app(DecisionTreeRecommendationService::class)->generate($report);

        $this->assertSame(2, $result['food_packs']);
        $this->assertSame(2, $result['medicine_kits']);
        $this->assertSame(2000.0, $result['cash_assistance']);
        $this->assertTrue($result['inputs']['medical_needs']);
        $this->assertSame('full_assistance', $result['inputs']['output_scope']);
        $this->assertStringContainsString('medical needs', $result['basis']);
    }

    public function test_family_description_medical_terms_enable_medicine_kits(): void
    {
        $role = Role::create(['role_name' => 'field_officer']);
        $user = User::create([
            'role_id' => $role->role_id,
            'full_name' => 'Field Officer',
            'email' => 'family-medical@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $barangay = Barangay::create([
            'barangay_name' => 'Kauswagan',
            'municipality' => 'Matanao',
            'province' => 'Davao del Sur',
            'status' => 'active',
        ]);
        $location = IncidentLocation::create([
            'barangay_id' => $barangay->barangay_id,
            'latitude' => '6.688099',
            'longitude' => '125.166607',
            'sitio_purok' => 'Purok 4',
        ]);
        $report = DamageReport::create([
            'user_id' => $user->user_id,
            'location_id' => $location->location_id,
            'disaster_type' => 'Other',
            'description' => 'Family-level assessment for assistance.',
            'damage_severity' => 'moderate',
            'affected_families' => 1,
            'affected_structures' => 0,
            'incident_datetime' => now(),
            'status' => 'validated',
            'created_at' => now(),
        ]);

        AffectedFamily::create([
            'report_id' => $report->report_id,
            'family_head_name' => 'Medical Family',
            'household_members' => 5,
            'description' => 'One member has a wound and needs medicine.',
            'damage_severity' => 'moderate',
            'created_at' => now(),
        ]);

        $result = app(DecisionTreeRecommendationService::class)->generate($report);

        $this->assertTrue($result['inputs']['medical_needs']);
        $this->assertSame(2, $result['medicine_kits']);
        $this->assertStringContainsString('medical needs', $result['basis']);
    }
}
