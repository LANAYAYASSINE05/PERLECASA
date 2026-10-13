<?php

namespace App\Providers\Filament;

use App\Filament\AvatarInitiales;
use App\Filament\Pages\Auth\Connexion;
use App\Filament\Pages\TableauDeBord;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('')
            ->login(Connexion::class)
            ->profile()
            ->brandName('Perle Casa Immobilier')
            ->brandLogo(asset('images/logo-clair.png'))
            ->brandLogoHeight('3.25rem')
            ->favicon(asset('images/favicon.png'))
            ->viteTheme('resources/css/filament/app/theme.css')
            ->defaultAvatarProvider(AvatarInitiales::class)
            ->colors([
                'primary' => [
                    50 => '241, 245, 250',
                    100 => '226, 234, 243',
                    200 => '197, 212, 229',
                    300 => '152, 176, 205',
                    400 => '104, 136, 176',
                    500 => '58, 95, 140',
                    600 => '28, 64, 107',
                    700 => '12, 42, 77',
                    800 => '10, 31, 59',
                    900 => '6, 21, 42',
                    950 => '3, 12, 25',
                ],
                'or' => Color::hex('#c4953a'),
                'gray' => Color::Slate,
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'info' => Color::Sky,
            ])
            ->font('Inter')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="preconnect" href="https://fonts.bunny.net">'
                    .'<link rel="stylesheet" href="https://fonts.bunny.net/css?family=fraunces:400,500,600&display=swap">',
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="stylesheet" href="'.asset('css/connexion.css').'?v='.filemtime(public_path('css/connexion.css')).'">'
                    // Masque les éléments animés avant le premier rendu ; filet de sécurité si le script ne se charge pas.
                    .'<script>(function(h){h.classList.remove("dark");new MutationObserver(function(){if(h.classList.contains("dark")){h.classList.remove("dark")}}).observe(h,{attributes:true,attributeFilter:["class"]})})(document.documentElement);if(!matchMedia("(prefers-reduced-motion: reduce)").matches){document.documentElement.classList.add("pc-js");'
                    .'setTimeout(function(){if(!window.pcAnimationsPretes){document.documentElement.classList.remove("pc-js")}},2500)}</script>'
                    .app(Vite::class)(['resources/js/connexion.js'])->toHtml(),
                scopes: Connexion::class,
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => request()->routeIs('filament.app.auth.*') ? '' :
                    '<script>if(!matchMedia("(prefers-reduced-motion: reduce)").matches){document.documentElement.classList.add("pc-anime-app","pc-js-app");'
                    .'setTimeout(function(){if(!window.pcAnimationsPretes){document.documentElement.classList.remove("pc-js-app")}},2500)}</script>'
                    .app(Vite::class)(['resources/js/application.js'])->toHtml(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.partials.chargement')->render(),
            )
            ->renderHook(PanelsRenderHook::TOPBAR_START, fn (): string => view('filament.partials.date-du-jour')->render())
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn (): string => view('filament.partials.pied-barre-laterale')->render())
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('18rem')
            ->maxContentWidth('full')
            ->databaseTransactions()
            ->unsavedChangesAlerts()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->navigationGroups([
                NavigationGroup::make('Encaissements'),
                NavigationGroup::make('Décaissements'),
                NavigationGroup::make('Trésorerie'),
                NavigationGroup::make('Référentiel'),
                NavigationGroup::make('Administration'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([TableauDeBord::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
