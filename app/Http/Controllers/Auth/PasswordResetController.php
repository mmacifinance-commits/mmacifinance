<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class PasswordResetController extends Controller
{
    public function showForgotForm()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'No account found with this email address.',
        ]);

        $email = $request->email;
        $user = User::where('email', $email)->first();

        // Generate 6 digit code
        $code = (string) rand(100000, 999999);

        // Store in Cache for 10 minutes
        Cache::put('password_reset_code_' . $email, $code, now()->addMinutes(10));

        // Store email in session to verify on the next step
        $request->session()->put('password_reset_email', $email);

        // Send Email
        Mail::to($email)->send(new PasswordResetMail($code, $user->name));

        return redirect()->route('password.reset')->with('message', 'A verification code has been sent to your email.');
    }

    public function showResetForm(Request $request)
    {
        if (!$request->session()->has('password_reset_email')) {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->session()->get('password_reset_email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $email = $request->session()->get('password_reset_email');

        if (!$email) {
            return redirect()->route('password.request');
        }

        $request->validate([
            'code' => 'required|numeric|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min' => 'The password must be at least 8 characters.',
        ]);

        $cachedCode = Cache::get('password_reset_code_' . $email);

        if (!$cachedCode || $cachedCode !== $request->code) {
            return back()->withErrors([
                'code' => 'The verification code is invalid or has expired.',
            ]);
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            // Update Password
            $user->password = Hash::make($request->password);
            
            // Reset lockout status
            $user->failed_login_attempts = 0;
            $user->lockout_level = 0;
            $user->locked_until = null;
            
            // Also reset 2FA otp just in case
            $user->otp_code = null;
            $user->otp_expires_at = null;
            
            $user->save();

            // Clear cache and session
            Cache::forget('password_reset_code_' . $email);
            $request->session()->forget('password_reset_email');

            return redirect()->route('login')->with('message', 'Your password has been changed successfully. You can now log in.');
        }

        return redirect()->route('password.request')->withErrors(['email' => 'User not found.']);
    }
}
