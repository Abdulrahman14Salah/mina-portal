<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_registration_page_loads(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_valid_registration_creates_client_user_and_redirects(): void
    {
        $response = $this->post('/register', [
            'name' => 'Client User',
            'email' => 'client@example.com',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
        ]);

        $user = User::where('email', 'client@example.com')->first();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('client'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'user_created',
        ]);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_duplicate_email_fails_validation(): void
    {
        User::factory()->create(['email' => 'client@example.com']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Client User',
            'email' => 'client@example.com',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
    }

    public function test_weak_password_fails_validation(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Client User',
            'email' => 'client@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');
    }

    public function test_empty_form_fails_with_required_errors(): void
    {
        $response = $this->from('/register')->post('/register', []);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertSame(0, DB::table('audit_logs')->count());
    }
}
