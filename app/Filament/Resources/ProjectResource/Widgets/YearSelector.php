<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Morilog\Jalali\Jalalian;
use phpDocumentor\Reflection\Types\Static_;

class YearSelector extends Widget implements HasForms
{
    protected static string $view = 'filament.resources.project-resource.widgets.year-selector';

    use InteractsWithForms;

    public string|null $selectedYear = null;

    public function mount($selectedYear): void
    {
        $this->selectedYear = $selectedYear;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedYear')
                    ->label('انتخاب سال')->placeholder('همه سال ها')
                    ->options($this->getYears())
                    ->afterStateUpdated(function ($state) {
                        $this->js("window.location.href = window.location.pathname + '?year={$state}'");
                    })->live()
            ])
            ->live();
    }

    protected function getYears(): array
    {
        $current = Jalalian::now()->getYear();
        $years = [];

        for ($i = 0; $i <= 5; $i++) {
            $years[$current - $i] = $current - $i;
        }

        return $years;
    }

    public function getColumnSpan(): int | string | array
    {
        return 'full'; // پر کردن عرض صفحه
    }
}
