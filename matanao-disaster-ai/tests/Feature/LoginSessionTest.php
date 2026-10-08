<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel normally bypasses CSRF validation during feature tests.
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public static function officerRoles(): array
    {
        return [
            'DSWD' => ['dswd', '/dswd/dashboard'],
            'MDRRMO' => ['mdrrmo', '/dashboard'],
        ];
    }

    #[DataProvider('officerRoles')]
    public function test_officer_can_log_in_with_a_fresh_session(string $roleName, string $destination): void
    {
        $user = $this->createOfficer($roleName);
        $response = $this->get('/login');
        $response->assertOk();
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));

        $token = $this->getJson('/login/csrf-token')->assertOk()->json('token');

        $this->post('/login', [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'test-password',
        ])->assertRedirect($destination)->assertSessionHas('role', $roleName);

        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('officerRoles')]
    public function test_old_login_form_can_refresh_after_another_account_logs_out(string $roleName, string $destination): void
    {
        $previousUser = $this->createOfficer($roleName === 'dswd' ? 'mdrrmo' : 'dswd');
        $nextUser = $this->createOfficer($roleName);
        $oldToken = $this->getJson('/login/csrf-token')->json('token');

        $this->post('/login', [
            '_token' => $oldToken,
            'email' => $previousUser->email,
            'password' => 'test-password',
        ])->assertRedirect();

        $this->post('/logout', [
            '_token' => session()->token(),
        ])->assertRedirect('/login');
        Auth::forgetGuards();

        $credentials = ['email' => $nextUser->email, 'password' => 'test-password'];
        $this->post('/login', ['_token' => $oldToken] + $credentials)->assertStatus(419);
        $this->assertGuest();

        $refresh = $this->getJson('/login/csrf-token')->assertOk();
        $this->assertTrue($refresh->headers->hasCacheControlDirective('no-store'));
        $this->assertNotSame($oldToken, $refresh->json('token'));

        $this->post('/login', ['_token' => $refresh->json('token')] + $credentials)
            ->assertRedirect($destination)
            ->assertSessionHas('role', $roleName);
        $this->assertAuthenticatedAs($nextUser);
    }

    public function test_login_without_a_csrf_token_is_rejected(): void
    {
        $user = $this->createOfficer('dswd');
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'test-password',
        ])->assertStatus(419);
        $this->assertGuest();
    }

    private function createOfficer(string $roleName): User
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);

        return User::create([
            'role_id' => $role->role_id,
            'full_name' => strtoupper($roleName).' Officer',
            'email' => $roleName.'@example.com',
            'password' => Hash::make('test-password'),
            'status' => 'active',
        ]);
    }
}
