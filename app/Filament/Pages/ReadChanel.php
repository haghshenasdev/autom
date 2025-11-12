<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Morilog\Jalali\Jalalian;

class ReadChanel extends Page
{
    use HasPageShield;
    protected static ?string $navigationLabel = 'خبرخوان هوشمند ایتا';

    protected static ?string $navigationGroup = 'سیستم';

    protected static ?string $title = 'خبرخوان هوشمند ایتا';
    protected static ?int $navigationSort = 6;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static string $view = 'filament.pages.read-chanel';

    public $readChanel;

    public function mount(): void
    {
        $this->readChanel = \App\Models\ReadChanel::first();
    }

    protected function getFormSchema(): array
    {
        return [
            Placeholder::make('post_id')
                ->label('آیدی آخرین پست خوانده شده از کانال :')
                ->content(fn () => $this->readChanel->post_id)->hintAction(
                    Action::make('مشاهده پست')
                        ->label("مشاهده آخرین پست خوانده شده #{$this->readChanel->post_id}")
                        ->url('https://eitaa.com/hamase4/'. $this->readChanel->post_id,true)
                        ->color('primary')
                        ->icon('heroicon-o-eye')
                ),
            Placeholder::make('last_count_read')
                ->label('تعداد پست خوانده شد در آخرین پردازش موفق : ')
                ->content(fn () => $this->readChanel->last_count_read),
            Placeholder::make('created_at')
                ->label('تاریخ ایجاد : ')
                ->content(fn () => Jalalian::fromDateTime($this->readChanel->created_at)),
            Placeholder::make('updated_at')
                ->label('تاریخ آخرین بازخوانی موفق : ')
                ->content(fn () => Jalalian::fromDateTime($this->readChanel->updated_at)),
            Placeholder::make('updated_at')
                ->label('تاریخ آخرین بررسی کانال : ')
                ->content(fn () => Jalalian::fromDateTime($this->readChanel->last_read_at)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('read')
            ->label('بازخوانی کانال')
                ->action(function () {
                    $controller = app(\App\Http\Controllers\ReadChanel::class);
                    $controller->read();
                    Notification::make()
                        ->title('پست های کانال خوانده شد')
                        ->success()
                        ->send();
                    $this->mount();
                }),
        ];
    }
}
