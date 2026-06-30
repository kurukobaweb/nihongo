<?php

namespace App\Http\Controllers;

use App\Models\UserLearningSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $settings = $request->user()
            ->learningSetting()
            ->first();

        return Inertia::render('Settings', [
            'settings' => $settings?->toSettingsArray() ?? UserLearningSetting::DEFAULTS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question_format_preference' => [
                'required',
                'string',
                Rule::in(UserLearningSetting::QUESTION_FORMAT_OPTIONS),
            ],
            'speech_duration_seconds' => ['required', 'integer', 'min:30', 'max:180'],
            'timer_display_mode' => [
                'required',
                'string',
                Rule::in(UserLearningSetting::TIMER_DISPLAY_OPTIONS),
            ],
            'force_stop_enabled' => ['required', 'boolean'],
            'transcript_display_enabled' => ['required', 'boolean'],
        ]);

        $request->user()
            ->learningSetting()
            ->updateOrCreate([], $validated);

        return back()->with('status', 'settings-saved');
    }
}
