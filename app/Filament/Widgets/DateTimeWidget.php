<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

class DateTimeWidget extends Widget
{
    protected static string $view = 'filament.widgets.date-time-widget';
    protected int | string | array $columnSpan = 1;

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        return [
            'jalaliDate' => CalendarUtils::convertNumbers(Jalalian::now()->format('%A %d %B %Y')),
            'time' => CalendarUtils::convertNumbers(now()->format('H:i:s')),
        ];
    }
}
