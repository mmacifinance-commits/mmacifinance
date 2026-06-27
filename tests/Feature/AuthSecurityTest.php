<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_successful_credentials_send_2fa_and_set_cooldown()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('2fa.index'));
        
        $user->refresh();
        $this->assertNotNull($user->otp_code);
        $this->assertNotNull($user->otp_expires_at);
        $this->assertNotNull($user->otp_sent_at);
    }

    public function test_2fa_resend_cooldown_restriction()
    {
        $user = User::factory()->create([
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
            'otp_sent_at' => now(),
        ]);

        $this->withSession(['2fa_user_id' => $user->id]);

        // Request resend immediately - should fail
        $response = $this->post(route('2fa.resend'));
        $response->assertSessionHasErrors('otp');
        
        // Simulate time passing (3 minutes and 5 seconds)
        $this->travel(3)->minutes();
        $this->travel(5)->seconds();

        $response = $this->post(route('2fa.resend'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('message', 'Verification code resent successfully.');
    }

    public function test_account_locks_after_6_failed_attempts_with_incremental_durations()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // 1. Perform 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrongpassword',
            ]);
            $response->assertSessionHasErrors('email');
            $user->refresh();
            $this->assertEquals($i + 1, $user->failed_login_attempts);
            $this->assertNull($user->locked_until);
        }

        // 2. 6th failed attempt locks the account for 10 minutes
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);
        $response->assertSessionHasErrors('email');
        
        $user->refresh();
        $this->assertEquals(0, $user->failed_login_attempts); // reset
        $this->assertEquals(1, $user->lockout_level);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
        $this->assertEquals(10, round(now()->diffInMinutes($user->locked_until)));

        // 3. Trying to login while locked returns lockout error
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123', // correct password but locked
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('locked', session('errors')->first('email'));

        // 4. Travel past 10 minutes lock
        $this->travel(11)->minutes();

        // 5. Next 6 failed attempts should lock for 30 minutes (Level 2)
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrongpassword',
            ]);
        }
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);
        $user->refresh();
        $this->assertEquals(2, $user->lockout_level);
        $this->assertEquals(30, round(now()->diffInMinutes($user->locked_until)));

        // 6. Travel past 30 minutes
        $this->travel(31)->minutes();

        // 7. Next 6 failed attempts lock for 1 hour (Level 3)
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrongpassword',
            ]);
        }
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);
        $user->refresh();
        $this->assertEquals(3, $user->lockout_level);
        $this->assertEquals(60, round(now()->diffInMinutes($user->locked_until)));
    }

    public function test_changing_password_resets_lockout_parameters()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'failed_login_attempts' => 0,
            'lockout_level' => 3,
            'locked_until' => now()->addHours(1),
        ]);

        // Start password reset flow
        $response = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);
        $response->assertRedirect(route('password.reset'));

        // Retrieve code from Cache
        $code = Cache::get('password_reset_code_' . $user->email);
        $this->assertNotNull($code);

        // Reset password
        $response = $this->withSession(['password_reset_email' => $user->email])
            ->post('/reset-password', [
                'code' => $code,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('message', 'Your password has been changed successfully. You can now log in.');

        // Verify user is unlocked
        $user->refresh();
        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertEquals(0, $user->lockout_level);
        $this->assertNull($user->locked_until);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }
}
