<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\IncidentLocation;
use App\Models\ResourceRecommendation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_creation_is_recorded_in_the_audit_trail(): void
    {
        $admin = $this->createUserForRole('admin', 'admin@example.com', 'System Admin');
        $validatorRole = Role::firstOrCreate(['role_name' => 'validator']);

        $response = $this
            ->actingAs($admin)
            ->withSession(['role' => 'admin'])
            ->post(route('admin.users.store'), [
                'full_name' => 'Validation Officer',
                'email' => 'new-validator@example.com',
                'role_id' => $validatorRole->role_id,
                'password' => 'Secure#Pass9',
                'password_confirmation' => 'Secure#Pass9',
                'status' => 'active',
            ]);

        $response
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('status');

        $log = AuditLog::query()->latest('audit_id')->first();

        $this->assertNotNull($log);
        $this->assertSame('User Management', $log->module);
        $this->assertSame('user_created', $log->action);
        $this->assertSame('System Admin', $log->actor_name);
        $this->assertSame('USR-0002', $log->target_label);
        $this->assertSame('new-validator@example.com', $log->details['email']);
        $this->assertSame('validator', $log->details['assigned_role']);
    }

    public function test_report_validation_is_recorded_in_the_audit_trail(): void
    {
        $mdrrmo = $this->createUserForRole('mdrrmo', 'mdrrmo@example.com', 'MDRRMO Officer');
        $validator = $this->createUserForRole('validator', 'validator@example.com', 'Validation Officer');
        $barangay = $this->createBarangay('Asbang');
        $report = $this->createDamageReport($validator, $barangay, 'pending');

        $response = $this
            ->actingAs($mdrrmo)
            ->withSession(['role' => 'mdrrmo'])
            ->post(route('validation.validate-report', $report));

        $response
            ->assertRedirect(route('validation.index'))
            ->assertSessionHas('status');

        $log = AuditLog::query()
            ->where('action', 'report_validated')
            ->latest('audit_id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Validation', $log->module);
        $this->assertSame('REP-0001', $log->target_label);
        $this->assertSame('validated', $log->details['status']);
        $this->assertSame('Validation Officer', $log->details['submitted_by']);
    }

    public function test_recommendation_generation_is_recorded_in_the_audit_trail(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => json_encode([
                    'food_packs' => 12,
                    'medicine_kits' => 4,
                    'cash_assistance' => 7500,
                    'basis' => 'Severe flooding affected multiple households.',
                ]),
            ], 200),
        ]);

        $mdrrmo = $this->createUserForRole('mdrrmo', 'mdrrmo-rec@example.com', 'MDRRMO Officer');
        $barangay = $this->createBarangay('Asbang');
        $report = $this->createDamageReport($mdrrmo, $barangay, 'validated');

        $response = $this
            ->actingAs($mdrrmo)
            ->withSession(['role' => 'mdrrmo'])
            ->post(route('recommendations.generate', $report));

        $response
            ->assertRedirect(route('recommendations.index'))
            ->assertSessionHas('status');

        $log = AuditLog::query()
            ->where('action', 'recommendation_generated')
            ->latest('audit_id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Recommendation', $log->module);
        $this->assertSame('REC-0001', $log->target_label);
        $this->assertSame('REP-0001', $log->details['report_code']);
        $this->assertSame('Decision Tree with Ollama-assisted refinement', $log->details['source']);
        $this->assertStringContainsString('Ollama refinement: Severe flooding affected multiple households.', $log->details['basis']);

        $recommendation = ResourceRecommendation::query()->first();

        $this->assertNotNull($recommendation);
        $this->assertSame('ollama', $recommendation->source);
        $this->assertSame('Flood', $recommendation->input_snapshot['disaster_type']);
        $this->assertSame('Asbang', $recommendation->input_snapshot['barangay']);
        $this->assertSame(2, $recommendation->input_snapshot['affected_structures']);
    }

    public function test_admin_audit_trail_page_filters_logs_by_module_and_search(): void
    {
        $admin = $this->createUserForRole('admin', 'admin-audit@example.com', 'System Admin');

        AuditLog::create([
            'actor_id' => $admin->user_id,
            'actor_name' => $admin->full_name,
            'actor_role' => 'admin',
            'module' => 'Validation',
            'action' => 'report_validated',
            'target_type' => 'DamageReport',
            'target_id' => 1,
            'target_label' => 'REP-0001',
            'description' => 'Validated report REP-0001.',
            'details' => ['status' => 'validated'],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        AuditLog::create([
            'actor_id' => $admin->user_id,
            'actor_name' => $admin->full_name,
            'actor_role' => 'admin',
            'module' => 'User Management',
            'action' => 'user_created',
            'target_type' => 'User',
            'target_id' => 2,
            'target_label' => 'USR-0002',
            'description' => 'Created user account for Validation Officer.',
            'details' => ['assigned_role' => 'validator'],
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->withSession(['role' => 'admin'])
            ->get(route('admin.audit-trail', [
                'module' => 'Validation',
                'search' => 'REP-0001',
            ]));

        $response
            ->assertOk()
            ->assertSee('REP-0001')
            ->assertSee('Validated report REP-0001.')
            ->assertDontSee('USR-0002');
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

    private function createDamageReport(User $user, Barangay $barangay, string $status): DamageReport
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
            'disaster_type' => 'Flood',
            'description' => 'Flood response required.',
            'damage_severity' => 'severe',
            'affected_families' => 4,
            'affected_structures' => 2,
            'incident_datetime' => now(),
            'status' => $status,
            'created_at' => now(),
        ]);
    }
}
