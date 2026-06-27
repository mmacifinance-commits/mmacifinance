<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        $userId = $request->session()->get('2fa_user_id');
        $user = User::find($userId);

        $cooldownSeconds = 0;
        if ($user && $user->otp_sent_at) {
            $elapsed = abs(now()->diffInSeconds($user->otp_sent_at, false));
            if ($elapsed < 180) {
                $cooldownSeconds = 180 - $elapsed;
            }
        }

        return Inertia::render('Auth/Verify2FA', [
            'cooldownSeconds' => $cooldownSeconds,
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'otp' => 'required|numeric|digits:6',
        ]);

        $userId = $request->session()->get('2fa_user_id');

        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (!$user || $user->otp_code !== $request->otp || now()->greaterThan($user->otp_expires_at)) {
            return back()->withErrors(['otp' => 'The provided OTP is invalid or expired.']);
        }

        // Login user
        $remember = $request->session()->get('2fa_remember', false);
        Auth::login($user, $remember);

        // Clear 2FA data from session and DB
        $request->session()->forget(['2fa_user_id', '2fa_remember']);
        $user->otp_code = null;
        $user->otp_expires_at = null;
        
        // Reset lockout levels and attempts
        $user->failed_login_attempts = 0;
        $user->lockout_level = 0;
        $user->locked_until = null;
        
        $user->save();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request)
    {
        $userId = $request->session()->get('2fa_user_id');

        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if ($user) {
            if ($user->otp_sent_at) {
                $elapsed = abs(now()->diffInSeconds($user->otp_sent_at, false));
                if ($elapsed < 180) {
                    $remaining = 180 - $elapsed;
                    $minutes = ceil($remaining / 60);
                    return back()->withErrors([
                        'otp' => "Please wait at least {$minutes} minute(s) before requesting another code."
                    ]);
                }
            }

            // Generate new OTP
            $otp = rand(100000, 999999);

            // Save new OTP, expiration, and update otp_sent_at
            $user->otp_code = $otp;
            $user->otp_expires_at = now()->addMinutes(10);
            $user->otp_sent_at = now();
            $user->save();

            // Send new OTP Email
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\TwoFactorOtpMail($otp, $user->name));
        }

        return back()->with('message', 'Verification code resent successfully.');
    }
}
