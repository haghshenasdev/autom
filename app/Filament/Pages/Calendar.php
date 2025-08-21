<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Calendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static string $view = 'filament.pages.calendar';

    protected static ?string $navigationGroup = 'پروژه / جلسه / پیگیری';

    protected static ?string $navigationLabel = "تقویم کارها";

    protected static ?string $title = "تقویم کارها";
}
