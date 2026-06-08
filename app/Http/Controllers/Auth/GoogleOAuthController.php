<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleOAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Googleログインに失敗しました。もう一度お試しください。']);
        }

        $googleId = (string) $googleUser->getId();
        $email = $googleUser->getEmail();

        if ($googleId === '' || $email === null || $email === '') {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Googleログインに必要なメールアドレスを取得できませんでした。']);
        }

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user !== null) {
            return $this->login($request, $user);
        }

        if (User::query()->where('email', $email)->exists()) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => '既存メールアドレスの扱いは未確定のため、メール/パスワードでログインしてください。',
                ]);
        }

        $user = DB::transaction(function () use ($request, $googleUser, $googleId, $email): User {
            $user = User::query()->create([
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'email_verified_at' => $this->emailVerifiedAt($googleUser),
                'password' => null,
                'google_id' => $googleId,
                'avatar_url' => $googleUser->getAvatar(),
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

        return $this->login($request, $user);
    }

    private function login(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function emailVerifiedAt(SocialiteUser $googleUser): ?Carbon
    {
        $raw = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];

        return ($raw['email_verified'] ?? false) === true ? now() : null;
    }
}
