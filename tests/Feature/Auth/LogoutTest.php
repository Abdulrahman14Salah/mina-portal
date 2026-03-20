<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_session_is_invalidated_after_logout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }

    public function test_dashboard_requires_login_after_logout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)->post('/logout');

        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
