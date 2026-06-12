<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->whereNull('deleted_at'),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms_of_service' => ['required', 'accepted'],
            'privacy_policy' => ['required', 'accepted'],
        ]);

        $user = DB::transaction(function () use ($request, $validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            Consent::query()->create([
                'user_id' => $user->id,
                'document_type' => 'terms_of_service',
                'document_version' => config('legal.terms_of_service_version'),
                'agreed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Consent::query()->create([
                'user_id' => $user->id,
                'document_type' => 'privacy_policy',
                'document_version' => config('legal.privacy_policy_version'),
                'agreed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect(route('verification.notice', absolute: false));
    }
}
