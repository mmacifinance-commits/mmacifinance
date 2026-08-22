<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class TutorialController extends Controller
{
    public function state(Request $request)
    {
        $user = $request->user();

        if (!$this->canUseTutorial($user)) {
            return response()->json(['available' => false, 'show' => false]);
        }

        return response()->json([
            'available' => true,
            'status' => $user?->tutorial_status ?? 'pending',
            'version' => $user?->tutorial_version,
            'current_step' => $user?->tutorial_current_step,
            'completed_at' => $user?->tutorial_completed_at?->toISOString(),
            'skipped_at' => $user?->tutorial_skipped_at?->toISOString(),
            'show' => $user && $user->shouldShowTutorial('dashboard'),
        ]);
    }

    public function step(Request $request)
    {
        $data = $request->validate([
            'step' => 'required|string|max:100',
        ]);

        $user = $request->user();
        if (!$this->canUseTutorial($user) || $user->tutorial_status === 'skipped' || $user->tutorial_completed_at) {
            return response()->json(['show' => false], 403);
        }

        $user->forceFill([
            'tutorial_status' => 'active',
            'tutorial_version' => 'v1',
            'tutorial_current_step' => $data['step'],
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function skip(Request $request)
    {
        $user = $request->user();
        if (!$this->canUseTutorial($user)) {
            return response()->json(['show' => false], 403);
        }

        $user->forceFill([
            'tutorial_status' => 'skipped',
            'tutorial_version' => 'v1',
            'tutorial_current_step' => null,
            'tutorial_skipped_at' => now(),
            'tutorial_completed_at' => null,
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function complete(Request $request)
    {
        $user = $request->user();
        if (!$this->canUseTutorial($user)) {
            return response()->json(['show' => false], 403);
        }

        $user->forceFill([
            'tutorial_status' => 'completed',
            'tutorial_version' => 'v1',
            'tutorial_current_step' => null,
            'tutorial_completed_at' => now(),
            'tutorial_skipped_at' => null,
        ])->save();

        return response()->json(['ok' => true]);
    }

    private function canUseTutorial(?User $user): bool
    {
        return $user && $user->role !== User::ROLE_AUDITOR;
    }
}
