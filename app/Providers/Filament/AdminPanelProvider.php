<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\ChatResource\Pages\Chat;
use Filament\Forms\Components\FileUpload;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Notifications\Livewire\Notifications;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use JaOcero\FilaChat\FilaChatPlugin;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Rmsramos\Activitylog\ActivitylogPlugin;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use TomatoPHP\FilamentPWA\FilamentPWAPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        Notifications::alignment(Alignment::Center);
        Notifications::verticalAlignment(VerticalAlignment::End);
        return $panel
            ->default()
//            ->spa()->spaUrlExceptions(fn(): array => [
//                '*/admin/contents*',
//            ])
            ->id('admin')
            ->brandLogo(asset('karnama.png'))
            ->brandLogoHeight('4rem')
            ->favicon(asset('karnama2.png'))
            ->sidebarCollapsibleOnDesktop()
            ->path('admin')
            ->login()->userMenuItems([
                MenuItem::make()
                    ->label('حالت موبایل ')
                    ->url(url('/toggle-mobile-mode'))
                    ->icon('heroicon-o-device-phone-mobile'),
            ])
            ->colors([
                'primary' => Color::Sky,
            ])->databaseNotifications()->databaseNotificationsPolling('30s')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
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
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('اسناد')
                    ->icon('heroicon-o-document'),
                NavigationGroup::make()
                    ->label('دبیرخانه')->collapsed(),
                NavigationGroup::make()
                    ->label('دستور')->collapsed(),
                NavigationGroup::make()
                    ->label('دستورکار / فعالیت ها')->collapsed(),
                NavigationGroup::make()
                    ->label('اطلاع رسانی')->collapsed(),
                NavigationGroup::make()
                    ->label('صورت جلسه')->collapsed(),
                NavigationGroup::make()
                    ->label('مراجعه کننده')->collapsed(),
                NavigationGroup::make()
                    ->label('مراجع دریافت نامه')->collapsed(),
                NavigationGroup::make()
                    ->label('سیستم')->collapsed(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                BreezyCore::make()->myProfile(
                    hasAvatars: true,
                )->enableBrowserSessions(condition: true)
                    ->avatarUploadComponent(fn() => FileUpload::make('avatar_url')->label('تصویر پروفایل')->imageEditor()->imageCropAspectRatio('1:1')->disk('profile-photos'))
                ,
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
                FilamentFullCalendarPlugin::make()
                    ->schedulerLicenseKey('')
                    ->selectable(true)
                    ->editable()
                    ->timezone(config('app.timezone'))
                    ->locale(config('app.locale'))
                    ->plugins(['dayGrid','timeGrid'])
                    ->config([]),
                ActivitylogPlugin::make()
                    ->authorize(
                        fn () => auth()->user()?->can('view_activitylog')
                    )
                    ->navigationGroup('سیستم'),
                FilaChatPlugin::make(),
//                FilamentPWAPlugin::make()->allowPWASettings(false),
            ])->viteTheme('resources/css/filament/admin/theme.css');
    }
}
