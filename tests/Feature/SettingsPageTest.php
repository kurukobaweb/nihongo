<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_settings_route_is_auth_only_get_page(): void
    {
        $route = Route::getRoutes()->getByName('settings');

        $this->assertNotNull($route);
        $this->assertSame('settings', $route->getName());
        $this->assertSame('settings', $route->uri());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('GET', $route->methods());
    }

    public function test_settings_vue_contains_ui_only_learning_setting_controls(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Settings.vue'));
        $routes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('出題方式', $source);
        $this->assertStringContainsString('スピーチ時間', $source);
        $this->assertStringContainsString('タイマー表示方式', $source);
        $this->assertStringContainsString('強制終了', $source);
        $this->assertStringContainsString('文字起こし表示', $source);
        $this->assertStringContainsString('未保存の変更があります', $source);
        $this->assertStringContainsString('保存する（画面内デモ）', $source);
        $this->assertStringContainsString('この設定はまだ保存されません', $source);
        $this->assertStringContainsString('画面内の確認用です', $source);
        $this->assertStringContainsString('保存機能は後続タスクで実装予定です', $source);
        $this->assertStringContainsString('保存失敗表示のUI確認です', $source);
        $this->assertStringContainsString("Inertia::render('Settings')", $routes);
        $this->assertStringNotContainsString('localStorage', $source);
        $this->assertStringNotContainsString('sessionStorage', $source);
        $this->assertStringNotContainsString('document.cookie', $source);
        $this->assertStringNotContainsString('fetch(', $source);
        $this->assertStringNotContainsString('axios', $source);
        $this->assertStringNotContainsString('user_learning_settings', $source);
        $this->assertStringNotContainsString('jsonb', strtolower($source));
    }
}
