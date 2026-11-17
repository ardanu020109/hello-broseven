<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Support\SidebarMenu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if (!$this->app->isProduction()) {
            Model::preventLazyLoading();
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                logger()->warning('Lazy loading terdeteksi (kemungkinan N+1): '.$model::class.'::'.$relation);
            });
        }

        // Menu sidebar dihitung per pengguna (role + fitur aktif).
        View::composer('layouts.partials.sidebar', function ($view) {
            $user = auth()->user();
            $view->with('menu', $user ? SidebarMenu::for($user) : []);
        });

        // Notifikasi navbar: data asli dari tabel notifications.
        View::composer('layouts.partials.navbar', function ($view) {
            $user = auth()->user();

            $view->with([
                'unreadCount' => $user
                    ? AppNotification::where('user_id', $user->id)->whereNull('read_at')->count()
                    : 0,
                'recentNotifications' => $user
                    ? AppNotification::where('user_id', $user->id)->latest()->limit(5)->get()
                    : collect(),
            ]);
        });
    }
}
