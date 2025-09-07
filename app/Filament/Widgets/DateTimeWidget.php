<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Morilog\Jalali\Jalalian;

class DateTimeWidget extends Widget
{
    protected static string $view = 'filament.widgets.date-time-widget';
    protected int | string | array $columnSpan = 1;

    public function getViewData(): array
    {
        return [
            'jalaliDate' => Jalalian::now()->format('%A %d %B %Y'),
            'time' => now()->format('H:i:s'),
        ];
    }
}
