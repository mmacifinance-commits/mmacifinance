<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PasswordResetController extends Controller
{
    public function showForgotForm()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetCode(Request $request)
    {
        if ($request->has('email')) {
            $request->merge([
                'email' => strtolower(trim($request->email)),
            ]);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'No account found with this email address.',
        ]);

        $email = $request->email;
        $user = User::where('email', $email)->first();

        // Generate 6 digit code
        $code = (string) rand(100000, 999999);

        // Persist in database so the reset flow survives reloads, tab changes, and browser restarts
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $code,
                'created_at' => now(),
            ]
        );

        // Store email in session to simplify the redirect flow
        $request->session()->put('password_reset_email', $email);

        // Send Email
        try {
            Mail::to($email)->send(new PasswordResetMail($code, $user->name));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Password reset email failed: ' . $e->getMessage());
            return back()->withErrors(['email' => 'Failed to send verification email. Please try again later.']);
        }

        return redirect()->route('password.reset', ['email' => $email])->with('message', 'A verification code has been sent to your email.');
    }

    public function showResetForm(Request $request)
    {
        $email = $request->session()->get('password_reset_email') ?: $request->query('email');
        $verified = $request->query('verified') === '1';

        if (!$email) {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/ResetPassword', [
            'email' => $email,
            'verified' => $verified,
        ]);
    }

    public function verifyResetCode(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'code' => preg_replace('/\D/', '', (string) $request->input('code')),
        ]);

        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|numeric|digits:6',
        ], [
            'email.exists' => 'No account found with this email address.',
            'code.required' => 'Please enter the 6-digit verification code.',
            'code.digits' => 'The verification code must be 6 digits.',
        ]);

        $tokenRow = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$tokenRow || (string) $tokenRow->token !== (string) $request->code) {
            return back()->withErrors([
                'code' => 'The verification code is invalid or has expired.',
            ]);
        }

        if ($tokenRow->created_at && now()->diffInMinutes($tokenRow->created_at) > 10) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return back()->withErrors([
                'code' => 'The verification code is invalid or has expired.',
            ]);
        }

        $request->session()->put('password_reset_email', $request->email);
        $request->session()->put('password_reset_verified_email', $request->email);

        return redirect()->route('password.reset', [
            'email' => $request->email,
            'verified' => 1,
        ])->with('message', 'Code verified. Now create your new password.');
    }

    public function resetPassword(Request $request)
    {
        $email = $request->session()->get('password_reset_verified_email') ?: $request->input('email') ?: $request->session()->get('password_reset_email');

        if (!$email) {
            return redirect()->route('password.request');
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.exists' => 'No account found with this email address.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min' => 'The password must be at least 8 characters.',
        ]);

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

            // Clear database token and session
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            $request->session()->forget('password_reset_email');
            $request->session()->forget('password_reset_verified_email');

            return redirect()->route('login')->with('message', 'Your password has been changed successfully. You can now log in.');
        }

        return redirect()->route('password.request')->withErrors(['email' => 'User not found.']);
    }
}
