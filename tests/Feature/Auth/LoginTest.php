<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_valid_credentials_authenticate_and_redirect_to_role_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_success',
            'user_id' => $user->id,
        ]);
        $this->get('/dashboard')->assertRedirect(route('client.dashboard'));
    }

    public function test_wrong_password_returns_generic_error(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_failed',
            'user_id' => null,
        ]);
    }

    public function test_admin_role_user_redirects_to_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_success',
            'user_id' => $user->id,
        ]);
        $this->get('/dashboard')->assertRedirect(route('admin.dashboard'));
    }
}
