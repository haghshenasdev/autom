<?php

namespace App\Filament\Resources;

use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use App\Filament\Resources\CustomerLetterResource\RelationManagers\CustomersRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\LettersRelationManager;
use App\Filament\Resources\LetterResource\Pages;
use App\Filament\Resources\LetterResource\RelationManagers;
use App\Http\Controllers\BaleBotController;
use App\Models\Customer;
use App\Models\Letter;
use App\Models\Organ;
use App\Models\OrganType;
use App\Models\Titleholder;
use App\Models\User;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Hugomyb\FilamentMediaAction\Forms\Components\Actions\MediaAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Morilog\Jalali\Jalalian;
use phpDocumentor\Reflection\Types\True_;

class LetterResource extends Resource
{
    protected static ?string $model = Letter::class;

    protected static ?int $navigationSort = 0;

    protected static ?string $label = "نامه";

    protected static ?string $navigationGroup = 'دبیرخانه';


    protected static ?string $pluralModelLabel = "نامه ها";

    protected static ?string $pluralLabel = "نامه";

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $user_id = $user->id;
        if (!$user->can('restore_any_letter')) {
            return parent::getEloquentQuery()->orWhere('user_id', $user_id) // نامه‌هایی که user_id برابر با آیدی کاربر لاگین شده است
            ->orWhereHas('referrals', function ($query) use ($user_id) {
                $query->where('to_user_id', $user_id); // نامه‌هایی که Referral.to_user_id برابر با آیدی کاربر لاگین شده است
            })->orWhereHas('users', function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            });
        }

        return parent::getEloquentQuery();

    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->label('شماره ثبت')
                    ->readOnly()
                    ->disabled()
                    ->hiddenOn('create'),
                Forms\Components\TextInput::make('subject')
                    ->label('موضوع')->autocomplete()
                    ->required(),
                Forms\Components\TextInput::make('mokatebe')
                    ->label('شماره مکاتبه'),
                Forms\Components\DateTimePicker::make('created_at')
                    ->closeOnDateSelection()
                    ->default(Date::now())->jalali()->label('تاریخ'),
                Forms\Components\Textarea::make('description')
                    ->label('توضیحات')->nullable()->default(null),
                Forms\Components\Textarea::make('summary')
                    ->label('خلاصه (هامش)')->nullable()->default(null),
                Forms\Components\Select::make('status')
                    ->options(Letter::getStatusListDefine())->label('وضعیت')
                    ->hiddenOn('create')
                    ->default(null)
                ,
                Forms\Components\Select::make('type')
                    ->relationship(null,'name')
                    ->label('نوع')->default(null)
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('عنوان نوع')
                            ->maxLength(255),
                    ])
                ,
                Forms\Components\Select::make('kind')
                    ->options(Letter::getKindListDefine())->label('نوع ورودی')
                    ->default(1)->required(),
                Forms\Components\Select::make('daftar')
                    ->label('دفتر')
                    ->relationship('daftar', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                    ->searchable(['id','name'])
                    ->preload(),
                Fieldset::make('owner')->label('صاحب')
                    ->schema([
                        Select::make('organs_owner')
                            ->prefixActions([
                                Action::make('updateAuthor')
                                    ->icon('heroicon-o-arrows-pointing-out')
                                    ->label('انتخاب بر اساس نوع')
                                    ->action(function (array $data,Forms\Set $set,Forms\Get $get): void {
                                        $organ_owners = $get('organs_owner');
                                        $set('organs_owner', array_merge($organ_owners,$data['organ_selected_owner']));
                                    })
                                    ->form([
                                        Select::make('organ_type_id')
                                            ->label('نوع')
                                            ->options(OrganType::query()->pluck('name', 'id'))
                                            ->live()
                                            ->searchable()
                                            ->required(),
                                        Forms\Components\Select::make('organ_selected_owner')
                                            ->label('ارگان')
                                            ->options(fn (Get $get) => $get('organ_type_id')
                                                ? Organ::where('organ_type_id', $get('organ_type_id'))->pluck('name', 'id')
                                                : [])
                                            ->multiple()
                                            ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                                            ->searchable()
                                            ->preload()
                                    ])
                            ])
                            ->relationship('organs_owner','name')
                            ->multiple()
                            ->searchable(['name','id'])
                            ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                            ->label('صاحب - ارگان')->lazy(),
                        Select::make('customers')
                            ->label('صاحب - شخص')
                            ->suffixActions([
                                Action::make('سابقه')
                                    ->label('دیدن سابقه')
                                    ->url(fn(?Model $record) => $record
                                        ? env('APP_URL') . '/admin/customers/' . $record->id . '/edit'
                                        : '#', shouldOpenInNewTab: true)
                                    ->icon('heroicon-o-arrow-top-right-on-square'),
                            ])
                            ->multiple()
                            ->relationship('customers','name')
                            ->searchable(['name','code_melli'])
                            ->getOptionLabelFromRecordUsing(function (Model $record) {
                                $lastLetter = $record->letters()->latest('created_at')->first();

                                $subject = $lastLetter?->subject ?? 'بدون موضوع';
                                $date = $lastLetter?->created_at
                                    ? Jalalian::fromDateTime($lastLetter->created_at)->format('%Y/%m/%d')
                                    : 'بدون تاریخ';

                                return "{$record->name} - {$record->code_melli} - {$subject} - {$date}";
                            })->optionsLimit(5)
                            ->lazy()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->label('نام و نام خانوادگی')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('code_melli')
                                    ->required()
                                    ->unique()
                                    ->numeric()
                                    ->label('کد ملی'),
                                Forms\Components\DatePicker::make('birth_date')
                                    ->label('تاریخ تولد')->jalali()
                                ,
                                Forms\Components\TextInput::make('phone')
                                    ->label('شماره تماس')
                                    ->required()
                                    ->tel(),
                                Forms\Components\Select::make('city_id')
                                    ->label('شهر')
                                    ->relationship('city', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->label('نام شهر')
                                        ,
                                    ])
                                ,
                            ]),
                    ]),
                Forms\Components\Select::make('organ')
                    ->prefixActions([
                        Action::make('updateAuthor')
                            ->icon('heroicon-o-arrows-pointing-out')
                            ->label('انتخاب بر اساس نوع')
                            ->action(function (array $data,Forms\Set $set): void {
                                $set('organ', $data['organ_selected']);
                            })
                            ->form([
                                Select::make('organ_type_id')
                                    ->label('نوع')
                                    ->options(OrganType::query()->pluck('name', 'id'))
                                    ->live()
                                    ->searchable()
                                    ->required(),
                                Forms\Components\Select::make('organ_selected')
                                    ->label('ارگان')
                                    ->options(fn (Get $get) => $get('organ_type_id')
                                        ? Organ::where('organ_type_id', $get('organ_type_id'))->pluck('name', 'id')
                                        : [])
                                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                                    ->searchable()
                                    ->preload()
                            ])
                    ])
                    ->label('گیرنده نامه')
                    ->relationship('organ', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                    ->searchable(['id','name'])
                    ->preload()
                    ->createOptionForm(Organ::formSchema()),
                Forms\Components\Select::make('projects')
                    ->label('دستورکار')->multiple()->lazy()
                    ->relationship('projects', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                    ->searchable(['projects.id', 'projects.name'])
                    ->preload(),
                FileUpload::make('file')
                    ->label('فایل')->lazy()
                    ->disk('private')
                    ->downloadable()
                    ->getUploadedFileNameForStorageUsing(static fn (TemporaryUploadedFile $file,?Model $record) => "{$record->id}/{$record->id}." . explode('/',$file->getMimeType())[1])
                    ->visibility('private')
                    ->preserveFilenames()
                    ->imageEditor()
                    ->hintAction(
                            Action::make('باز کردن لینک')
                                ->label('نمایش فایل')
                                ->url(fn($record) => env('APP_URL').'/private-show/'.$record->getFilePath(), shouldOpenInNewTab: true)
                                ->color('primary')
                                ->icon('heroicon-o-arrow-top-right-on-square'),
                    )
                    ->hiddenOn('create'),
                Section::make('امکانات بیشتر')->schema([
                    Forms\Components\Select::make('cartables')
                        ->label('گیرنده درخواست (افزودن به کارپوشه)')
                        ->relationship('users', 'name')->multiple()
                        ->searchable()->lazy()
                        ->allowHtml()
                        ->getOptionLabelFromRecordUsing(function ($record): string {
                            return view('filament.components.select-user-result')
                                ->with('name', $record->name)
                                ->with('user', $record)
                                ->with('image', $record->getFilamentAvatarUrl())
                                ->render();
                        })
                        ->preload(),
                    Forms\Components\Select::make('peiroow_letter_id')
                        ->label('پیرو')
                        ->relationship('letter', 'subject')
                        ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->subject}")
                        ->searchable(['id','subject'])
                        ->preload()
                    ,
                ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $columns = [];
        if (request()->cookie('mobile_mode') === 'on'){
            $columns = [
                Split::make([
                    TextColumn::make('id')->label('ثبت')->prefix('🆔 ثبت : ')->searchable()->sortable(),
                    TextColumn::make('subject')->prefix('❇️ موضوع : ')
                        ->weight(FontWeight::Bold)->searchable(),
                    TextColumn::make('organ.name')->prefix('📨 گیرنده نامه : ')->label('گیرنده')
                        ->sortable(),
                    TextColumn::make('daftar.name')->prefix('🏢 دفتر : ')
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->sortable(),
                    TextColumn::make('projects.name')->prefix('🎚️ دستورکار : ')
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->listWithLineBreaks()->sortable(),
                    Tables\Columns\TextColumn::make('status')
                        ->label('وضعیت')->prefix('📊 وضعیت: ')
                        ->badge()
                        ->color(fn (string $state): string => Letter::getStatusColor($state))
                        ->state(function (Model $record): string {
                            return Letter::getStatusLabel($record->status);})->sortable()->toggleable(isToggledHiddenByDefault: false),
                    TextColumn::make('kind')->label('نوع ورودی')->prefix('📫 صادره یا وارده : ')->sortable()
                        ->state(function (Model $record): string {
                            return Letter::getKindLabel($record->kind);
                        })->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('user.name')->prefix('👤 کاربر ثبت کننده : ')->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد')->prefix('📅 تاریخ ایجاد : ')->sortable()->jalaliDateTime(),
                    Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ آخرین ویرایش')->prefix('📅 تاریخ ویرایش : ')->jalaliDateTime()->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('customers.name')->prefix('صاحب - مراجعه کننده : ')
                        ->toggleable(isToggledHiddenByDefault: true)->sortable(),
                    TextColumn::make('organs_owner.name')->prefix('صاحب - ارگان : ')
                        ->toggleable(isToggledHiddenByDefault: true)->sortable(),
                ])->from('md')
            ];
        }else{
            $columns = [
                TextColumn::make('id')->label('ثبت')->searchable()->sortable(),
                TextColumn::make('subject')->label('موضوع')
                    ->weight(FontWeight::Bold)
                    ->words(10)->searchable(),
                TextColumn::make('customers.name')->label('صاحب - مراجعه کننده')
                    ->toggleable(isToggledHiddenByDefault: true)->sortable(),
                TextColumn::make('organs_owner.name')->label('صاحب - ارگان')
                    ->toggleable(isToggledHiddenByDefault: true)->sortable(),
                TextColumn::make('organ.name')->label('گیرنده نامه')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->sortable(),
                TextColumn::make('daftar.name')->label('دفتر')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('projects.name')->label('دستورکار')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->listWithLineBreaks()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => Letter::getStatusColor($state))
                    ->state(function (Model $record): string {
                        return Letter::getStatusLabel($record->status);})->sortable()->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('kind')->label('نوع ورودی')->sortable()
                    ->state(function (Model $record): string {
                        return Letter::getKindLabel($record->kind);
                    })->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type.name')->label('نوع')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')->label('ثبت کننده')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد')->jalaliDateTime(),
                Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ آخرین ویرایش')->jalaliDateTime()->toggleable(isToggledHiddenByDefault: true),
            ];
        }
        return $table->defaultSort('letters.id','desc')
            ->columns($columns)
            ->filters([
                SelectFilter::make('customers')
                    ->label('صاحب')
                    ->multiple()
                    ->relationship('customers','name')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Customer::query()->where('name', 'like', "%{$search}%")->orWhere('code_melli','like',"%$search%")->selectRaw("id, concat(name, '-', code_melli) as code_name")->limit(10)->pluck('code_name', 'id')->toArray())
                    ->getOptionLabelsUsing(fn (?Model $record) => $record ? "{$record->name} {$record->code_melli}" : [])
                ,
                SelectFilter::make('type')
                    ->relationship('type','name')
                    ->label('نوع')
                ,
                SelectFilter::make('status')
                    ->options(Letter::getStatusListDefine())->label('وضعیت')
                ,
                SelectFilter::make('organ')
                    ->label('گیرنده نامه')
                    ->relationship('organ', 'name')
                    ->getOptionLabelsUsing(fn (Model $record) => "{$record->id} - {$record->name}}")
                    ->searchable(['id','name'])
                    ->preload()
                ,
                SelectFilter::make('daftar')->label('دفتر')
                    ->relationship('daftar', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                    ->searchable(['id','name'])
                    ->preload(),
                Filter::make('created_at')
                    ->form([
                        Fieldset::make('تاریخ ایجاد')->schema([
                            DatePicker::make('created_from')->label('از')->jalali(),
                            DatePicker::make('created_until')->label('لغایت')->jalali()
                                ->default(now()),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                ,
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\Action::make('باز کردن لینک')
                    ->label('نمایش فایل')
                    ->url(fn(Letter $record) => env('APP_URL').'/private-show/'.$record->getFilePath(), shouldOpenInNewTab: true)
                    ->visible(fn(Letter $record): bool => $record->file !== null)
                    ->color('primary')
                    ->button()
                    ->icon('heroicon-o-arrow-top-right-on-square'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make()->visible(!\auth()->user()->can('restore_any_letter')),
                Tables\Actions\Action::make('timeline')
                    ->label('تایم‌لاین')
                    ->icon('heroicon-o-clock')
                    ->modalHeading('تایم‌لاین نامه')
                    ->modalContent(fn (Letter $record) => view('filament.components.timeline-modal', [
                        'events' => $record->timeline(),
                    ]))
                    ->modalWidth('xl')->modalSubmitAction(false),
            ])
            ->bulkActions([
                ExportBulkAction::make()->label('دریافت فایل exel'),
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AnswerRelationManager::class,
            RelationManagers\AppendixRelationManager::class,
            RelationManagers\ReferralsRelationManager::class,
            RelationManagers\ReplicationsRelationManager::class,
            RelationManagers\LetterProjectRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLetters::route('/'),
            'create' => Pages\CreateLetter::route('/create'),
            'view' => Pages\ViewLetter::route('/{record}'),
            'edit' => Pages\EditLetter::route('/{record}/edit'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

}
