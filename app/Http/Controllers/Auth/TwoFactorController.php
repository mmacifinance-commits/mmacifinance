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

        return Inertia::render('Auth/Verify2FA');
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
            // Generate new OTP
            $otp = rand(100000, 999999);

            // Save new OTP and expiration
            $user->otp_code = $otp;
            $user->otp_expires_at = now()->addMinutes(10);
            $user->save();

            // Send new OTP Email
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\TwoFactorOtpMail($otp, $user->name));
        }

        return back()->with('message', 'Verification code resent successfully.');
    }
}
