<?php

namespace App\Filament\Pages;

use App\Models\Letter;
use App\Models\Task;
use App\Models\TaskGroup;
use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model;
use Quadrubo\FilamentModelSettings\Pages\ModelSettingsPage;
use Quadrubo\FilamentModelSettings\Pages\Contracts\HasModelSettings;

class ManagePreferences extends ModelSettingsPage implements HasModelSettings
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'تنضیمات من';

    public static function getSettingRecord()
    {
         return auth()->user();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('مقادیر پیش فرض')
                            ->icon('heroicon-m-list-bullet')
                            ->schema([
                                Section::make('مقادیر پیش فرض نامه ها')
                                    ->description('میتوانید برای اجاد نامه ها مقادیر پیش فرض مشخص کنید تا ثبت نامه ها سریع تر انجام شود .')
                                    ->schema([
                                        Forms\Components\Select::make('letter_kind')
                                            ->options(Letter::getKindListDefine())->label('نوع ورودی')->nullable(),
                                        Forms\Components\Select::make('letter_daftar')
                                            ->label('دفتر')->model(Letter::class)
                                            ->relationship('daftar', 'name')
                                            ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                                            ->searchable(['id','name'])
                                            ->preload(),
                                        Forms\Components\Select::make('letter_type')
                                            ->model(Letter::class)
                                            ->relationship('type','name')
                                            ->label('نوع')->default(null),
                                    ]),
                                Section::make('مقادیر پیش فرض کار ها')
                                    ->description('میتوانید برای اجاد کار ها مقادیر پیش فرض مشخص کنید تا ثبت کار ها سریع تر انجام شود .')
                                    ->schema([
                                        Forms\Components\Toggle::make('task_completed')->label('انجام شده'),
                                        Forms\Components\Select::make('task_city_id')->label('شهر')
                                            ->relationship('city', 'name')->model(Task::class)
                                            ->searchable()->preload(),
                                        Forms\Components\Select::make('task_group_id')->label('دسته بندی')
                                            ->relationship('task_group', 'name')->model(Task::class)
                                            ->searchable()->preload()->multiple(),
                                        Forms\Components\Select::make('task_status')
                                            ->options(Task::getStatusListDefine())->label('وضعیت')
                                            ->default(null),
                                    ]),
                            ]),
                        Tabs\Tab::make('اطلاع رسانی ها')
                            ->icon('heroicon-m-bell')
                            ->schema([
                                Toggle::make('send_notif_bale_login')->label('ارسال پیام ورود به سامانه در بله'),
                                Fieldset::make('ارسال پیام افزوده شدن نامه به کارتابل')
                                    ->schema([
                                        Toggle::make('send_notif_bale_added_cartable')->label('ربات بله'),
                                        Toggle::make('send_notif_panel_added_cartable')->label('پنل کاربری'),
                                    ]),
                                Fieldset::make('ارسال پیام ارجاع جدید')
                                    ->schema([
                                        Toggle::make('send_notif_bale_referral')->label('ربات بله'),
                                        Toggle::make('send_notif_panel_referral')->label('پنل کاربری'),
                                    ]),
                                Fieldset::make('ارسال پیام کار جدید')
                                    ->schema([
                                        Toggle::make('send_notif_bale_task')->label('ربات بله'),
                                        Toggle::make('send_notif_panel_task')->label('پنل کاربری'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
