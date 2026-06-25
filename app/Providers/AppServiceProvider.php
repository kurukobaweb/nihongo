<?php

namespace App\Providers;

use App\Contracts\CommentGeneratorInterface;
use App\Services\Comment\TemplateCommentGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CommentGeneratorInterface::class, TemplateCommentGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
