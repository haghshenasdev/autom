<?php

namespace App\Filament\Resources\NotificationResource\Pages;

use App\Filament\Pages\SendNotification;
use App\Filament\Resources\NotificationResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ارسال جدید')->label('ارسال جدید')->icon('heroicon-o-paper-airplane')
                ->url(SendNotification::getUrl()),
        ];
    }
}
