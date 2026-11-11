<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Resources\TicketResource;
use App\Models\Ticket;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->spa()
            ->login()
            ->passwordReset()
            ->profile(\App\Filament\Pages\Auth\EditProfile::class, isSimple: false)
            ->defaultThemeMode(ThemeMode::Light)
            ->font('Montserrat')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->maxContentWidth(MaxWidth::SevenExtraLarge)
            ->sidebarCollapsibleOnDesktop()
            // Filament menyimpan status sidebar di localStorage dan defaultnya
            // terbuka. Di layar kecil sidebar harus tertutup agar konten tidak
            // tertutup overlay saat halaman pertama kali dibuka.
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => view('filament.sidebar-mobile')->render(),
            )
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverClusters(in: app_path('Filament/Admin/Clusters'), for: 'App\\Filament\\Admin\\Clusters')
            // Widget dashboard diurutkan lewat static::$sort pada masing-masing class:
            // statistik (1) → tren (2) → status (3) → prioritas (4) → tiket terbaru (5).
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                \Awcodes\Overlook\Widgets\OverlookWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Ticket Management')
                    ->icon('heroicon-m-ticket'),
                NavigationGroup::make()
                    ->label('Master Data')
                    ->icon('heroicon-m-square-3-stack-3d'),
                NavigationGroup::make()
                    ->label('Reports')
                    ->icon('heroicon-m-chart-bar'),
                NavigationGroup::make()
                    ->label('System')
                    ->icon('heroicon-m-cog-6-tooth'),
            ])
            ->navigationItems([
                NavigationItem::make('My Tickets')
                    ->group('Ticket Management')
                    ->icon('heroicon-o-user')
                    ->sort(20)
                    ->url(fn (): string => TicketResource::getUrl('my'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.tickets.my'))
                    ->badge(fn (): ?string => auth()->check()
                        ? (string) Ticket::query()
                            ->visibleTo(auth()->user())
                            ->where('reporter_id', auth()->id())
                            ->count()
                        : null)
                    ->visible(fn (): bool => auth()->check()),
                NavigationItem::make('Assigned Tickets')
                    ->group('Ticket Management')
                    ->icon('heroicon-o-user-group')
                    ->sort(30)
                    ->url(fn (): string => TicketResource::getUrl('assigned'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.tickets.assigned'))
                    ->badge(fn (): ?string => auth()->check()
                        ? (string) Ticket::query()
                            ->visibleTo(auth()->user())
                            ->where('assigned_to', auth()->id())
                            ->count()
                        : null)
                    ->visible(fn (): bool => auth()->user()?->isStaff() ?? false),
            ])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn () => auth()->user()->name)
                    ->url(fn (): string => EditProfilePage::getUrl())
                    ->icon('heroicon-m-user-circle'),
                // 'profile' => \Filament\Navigation\MenuItem::make()
                //     ->label(fn () => auth()->user()->name)
                //     ->icon('heroicon-m-user-circle'),
            ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 2,
                        'lg' => 3,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 2,
                        'lg' => 3,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 2,
                        'lg' => 3,
                    ]),
                \Hasnayeen\Themes\ThemesPlugin::make(),
                \Njxqlus\FilamentProgressbar\FilamentProgressbarPlugin::make()->color('#29b'),
                \DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin::make()
                    ->showEmptyPanelOnMobile(false)
                    ->formPanelPosition('right')
                    ->formPanelWidth('40%')
                    ->emptyPanelBackgroundImageOpacity('70%')
                    ->emptyPanelBackgroundImageUrl('https://picsum.photos/seed/picsum/1260/750.webp/?blur=1'),
                \Awcodes\LightSwitch\LightSwitchPlugin::make()
                    ->position(\Awcodes\LightSwitch\Enums\Alignment::BottomCenter)
                    ->enabledOn([
                        'auth.login',
                        'auth.password',
                    ]),
                \Awcodes\Overlook\OverlookPlugin::make()
                    ->includes([
                        \App\Filament\Admin\Resources\TicketResource::class,
                        \App\Filament\Admin\Resources\TicketCategoryResource::class,
                        \App\Filament\Admin\Resources\UserResource::class,
                    ])
                    // Tampil setelah widget utama; hanya berisi resource yang
                    // boleh dilihat pengguna (praktisnya admin).
                    ->sort(90),
                \Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin::make()
                    ->slug('my-profile')
                    ->setTitle('My Profile')
                    ->shouldRegisterNavigation(false)
                    ->shouldShowDeleteAccountForm(false)
                    ->shouldShowSanctumTokens(false)
                    ->shouldShowBrowserSessionsForm()
                    ->shouldShowAvatarForm(),
            ])
            ->resources([
                config('filament-logger.activity_resource'),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
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
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
