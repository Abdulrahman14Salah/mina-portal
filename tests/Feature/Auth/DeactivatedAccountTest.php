<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeactivatedAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('client');

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_failed',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'login_success',
            'user_id' => $user->id,
        ]);
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_deactivated_logged_in_user_is_kicked_out_on_next_request(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('client');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'forced_logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('client');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_success',
            'user_id' => $user->id,
        ]);
    }
}
