<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Models\User;
use App\Mail\TwoFactorOtpMail;
use Illuminate\Support\Facades\Mail;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        $isLocalDev = config('app.env') === 'local' || config('app.debug') === true;

        $users = [];
        if ($isLocalDev) {
            $users = User::select('id', 'name', 'email', 'role')->get()->map(function ($u) {
                return [
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'role_label' => match($u->role) {
                        'super_admin' => 'Head of Finance',
                        'cashier' => 'Cashier',
                        'disbursement_officer' => 'Disbursement Officer',
                        'budget_officer' => 'Budget Officer',
                        'auditor' => 'Auditor',
                        default => ucfirst($u->role),
                    },
                ];
            });
        }

        return Inertia::render('Auth/Login', [
            'demoUsers' => $users,
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // Check if user is locked
        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $diffSeconds = now()->diffInSeconds($user->locked_until, false);
            if ($diffSeconds >= 3600) {
                $hours = ceil($diffSeconds / 3600);
                $timeRemaining = "{$hours} hour" . ($hours > 1 ? 's' : '');
            } else {
                $minutes = ceil($diffSeconds / 60);
                $timeRemaining = "{$minutes} minute" . ($minutes > 1 ? 's' : '');
            }
            return back()->withErrors([
                'email' => "Your account is locked due to too many failed login attempts. Please try again in {$timeRemaining} or reset your password.",
            ])->onlyInput('email');
        }

        if (Auth::validate($credentials)) {
            // Generate OTP
            $otp = rand(100000, 999999);

            // Save OTP, expiration (10 minutes), and sent_at time
            $user->otp_code = $otp;
            $user->otp_expires_at = now()->addMinutes(10);
            $user->otp_sent_at = now();
            $user->save();

            \Illuminate\Support\Facades\Log::info("2FA OTP generated for {$user->email}: {$otp}");

            try {
                // Send OTP Email
                Mail::to($user->email)->send(new TwoFactorOtpMail($otp, $user->name));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to send 2FA email to {$user->email}: " . $e->getMessage());
            }

            // Store user id in session to maintain state before full login
            $request->session()->put('2fa_user_id', $user->id);
            $request->session()->put('2fa_remember', $request->boolean('remember'));

            return redirect()->route('2fa.index');
        }

        if ($user) {
            $user->failed_login_attempts += 1;

            if ($user->failed_login_attempts >= 6) {
                $user->lockout_level += 1;

                $durationMinutes = match ($user->lockout_level) {
                    1 => 10,        // 10 minutes
                    2 => 30,        // 30 minutes
                    3 => 60,        // 1 hour (60 minutes)
                    4 => 720,       // 12 hours
                    default => 1440, // 24 hours max
                };

                $user->locked_until = now()->addMinutes($durationMinutes);
                $user->failed_login_attempts = 0; // Reset count for next lockout cycle
                $user->save();

                $durationLabel = match ($user->lockout_level) {
                    1 => '10 minutes',
                    2 => '30 minutes',
                    3 => '1 hour',
                    4 => '12 hours',
                    default => '24 hours',
                };

                return back()->withErrors([
                    'email' => "Too many failed login attempts. Your account has been locked for {$durationLabel}.",
                ])->onlyInput('email');
            }

            $user->save();
            $remaining = 6 - $user->failed_login_attempts;

            return back()->withErrors([
                'email' => "The provided credentials do not match our records. You have {$remaining} attempts remaining before lockout.",
            ])->onlyInput('email');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
