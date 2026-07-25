<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_code_can_be_requested(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'admin@mmac.edu.ph',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => ' ADMIN@MMAC.EDU.PH ',
        ]);

        $response->assertRedirect(route('password.reset'));
        $response->assertSessionHas('message', 'A verification code has been sent to your email.');

        $cachedCode = Cache::get('password_reset_code_admin@mmac.edu.ph');
        $this->assertNotNull($cachedCode);
        $this->assertEquals(6, strlen($cachedCode));

        Mail::assertSent(PasswordResetMail::class, function ($mail) use ($user) {
            return $mail->hasTo('admin@mmac.edu.ph');
        });
    }

    public function test_requesting_reset_code_fails_for_non_existent_email(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'nonexistent@mmac.edu.ph',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_reset_password_page_redirects_without_session_email(): void
    {
        $response = $this->get('/reset-password');

        $response->assertRedirect(route('password.request'));
    }

    public function test_reset_password_page_can_be_rendered_with_session_email(): void
    {
        $user = User::factory()->create();

        $response = $this->withSession(['password_reset_email' => $user->email])
            ->get('/reset-password');

        $response->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_code_and_formatted_input(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
            'failed_login_attempts' => 5,
            'lockout_level' => 2,
        ]);

        Cache::put('password_reset_code_' . $user->email, '123456', now()->addMinutes(10));

        $response = $this->withSession(['password_reset_email' => $user->email])
            ->post('/reset-password', [
                'code' => '123 456', // Padded with space like when user copies from email
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('message', 'Your password has been changed successfully. You can now log in.');

        $user->refresh();
        $this->assertTrue(Hash::check('newsecret123', $user->password));
        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertEquals(0, $user->lockout_level);
        $this->assertNull(Cache::get('password_reset_code_' . $user->email));
    }

    public function test_password_reset_fails_with_invalid_code(): void
    {
        $user = User::factory()->create();

        Cache::put('password_reset_code_' . $user->email, '123456', now()->addMinutes(10));

        $response = $this->withSession(['password_reset_email' => $user->email])
            ->post('/reset-password', [
                'code' => '654321',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ]);

        $response->assertSessionHasErrors(['code']);
    }
}
