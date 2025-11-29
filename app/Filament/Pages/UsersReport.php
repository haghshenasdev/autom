<?php

namespace App\Filament\Pages;

use App\Filament\Resources\UserResource\Widgets\UsersReport\UsersActivityChart;
use App\Filament\Resources\UserResource\Widgets\UsersReport\UsersReportStatsOverview;
use Filament\Pages\Page;

class UsersReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.users-report';

    protected static ?string $title = 'گزارش جامع فعالیت های کاربران';

    protected function getHeaderWidgets(): array
    {
        return [
            UsersReportStatsOverview::make(),
            UsersActivityChart::make(),
        ];
    }
}
