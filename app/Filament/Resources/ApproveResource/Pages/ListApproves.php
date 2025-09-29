<?php

namespace App\Filament\Resources\ApproveResource\Pages;

use App\Filament\Resources\ApproveResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApproves extends ListRecords
{
    protected static string $resource = ApproveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
