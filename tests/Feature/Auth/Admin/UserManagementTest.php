<?php

namespace Tests\Feature\Auth\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_view_users_index(): void
    {
        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    }

    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Reviewer User',
            'email' => 'reviewer@example.com',
            'password' => 'StrongPass1',
            'role' => 'reviewer',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'reviewer@example.com']);
        $this->assertTrue(User::where('email', 'reviewer@example.com')->first()->hasRole('reviewer'));
    }

    public function test_admin_can_deactivate_user(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('client');

        $this->actingAs($this->admin)
            ->delete(route('admin.users.deactivate', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.deactivate', $this->admin))
            ->assertForbidden();
    }

    public function test_admin_can_assign_role(): void
    {
        $target = User::factory()->create();
        $target->assignRole('client');

        $this->actingAs($this->admin)
            ->patch(route('admin.users.assign-role', $target), ['role' => 'reviewer'])
            ->assertRedirect(route('admin.users.edit', $target));

        $this->assertTrue($target->fresh()->hasRole('reviewer'));
    }

    public function test_non_admin_cannot_access_admin_users(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)->get('/admin/users')->assertForbidden();
    }
}
