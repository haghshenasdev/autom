<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Morilog\Jalali\Jalalian;

class YearSelector extends Widget
{
    protected static string $view = 'filament.resources.project-resource.widgets.year-selector';

    public $selectedYear;

    public function mount(): void
    {
        // مقدار پیش‌فرض سال را تنظیم کنید (مثلاً سال جاری)
        $this->selectedYear = now()->year;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view(static::$view, [
            'years' => $this->getYears(),
        ]);
    }

    protected function getYears(): array
    {
        // اینجا می‌توانید سال‌ها را بر اساس نیاز خود تنظیم کنید
        return range(Jalalian::now()->getYear(), Jalalian::now()->getYear() - 5); // سال‌های از 2000 تا سال جاری
    }
}
