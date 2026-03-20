<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_loads(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_valid_email_sends_password_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_does_not_reveal_whether_it_exists(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'unknown@example.com']);

        $response->assertSessionHas('status');
        $response->assertSessionDoesntHaveErrors('email');
    }
}
