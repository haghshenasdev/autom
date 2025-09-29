<?php

namespace App\Filament\Resources;

use AlperenErsoy\FilamentExport\Actions\FilamentExportBulkAction;
use App\Filament\Resources\CustomerLetterResource\RelationManagers\CustomersRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\LettersRelationManager;
use App\Filament\Resources\LetterResource\Pages;
use App\Filament\Resources\LetterResource\RelationManagers;
use App\Models\Customer;
use App\Models\Letter;
use App\Models\Titleholder;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class LetterResource extends Resource
{
    protected static ?string $model = Letter::class;

    protected static ?int $navigationSort = 0;

    protected static ?string $label = "نامه";

    protected static ?string $navigationGroup = 'نامه';


    protected static ?string $pluralModelLabel = "نامه ها";

    protected static ?string $pluralLabel = "نامه";

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->label('شماره ثبت')
                    ->readOnly()
                    ->disabled()
                    ->hiddenOn('create')
                ,
                Forms\Components\TextInput::make('subject')
                    ->label('موضوع')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('توضیحات'),
                Forms\Components\Select::make('status')
                    ->options(letter::getStatusListDefine())->label('وضعیت')
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
                Select::make('customers')
                    ->label('صاحب')
                    ->multiple()
                    ->required()
                    ->relationship(null,'name')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Customer::query()->where('name', 'like', "%{$search}%")->orWhere('code_melli','like',"%$search%")->selectRaw("id, concat(name, '-', code_melli) as code_name")->limit(10)->pluck('code_name', 'id')->toArray())
//                    ->getOptionLabelsUsing(fn (array $values): array => Customer::query()->whereIn('id', $values)->selectRaw("id, concat(name, '-', code_melli) as code_name")->pluck('code_name', 'id')->toArray())
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->name} {$record->code_melli}")->lazy()
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
                    ])
                ,
                FileUpload::make('file')
                    ->label('فایل')
                    ->disk('private')
                    ->downloadable()
                    ->getUploadedFileNameForStorageUsing(static fn (TemporaryUploadedFile $file,?Model $record) => "{$record->id}/{$record->id}." . explode('/',$file->getMimeType())[1])
                    ->visibility('private')
                    ->preserveFilenames()
                    ->imageEditor()
                    ->hiddenOn('create'),

                Section::make('امکانات بیشتر')->schema([
                    Forms\Components\Select::make('titleholder')
                        ->label('گیرنده نامه')
                        ->relationship(null, 'name')
                        ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->name} - {$record->official} ، {$record->organ()->first('name')->name}")
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->label('نام')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('official')
                                ->required()
                                ->label('سمت'),
                            Forms\Components\TextInput::make('phone')
                                ->label('شماره تماس')
                                ->tel(),
                            Forms\Components\Select::make('organ_id')
                                ->label('اداره')
                                ->relationship('organ', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->required()
                                        ->label('نام')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('address')
                                        ->label('آدرس'),
                                    Forms\Components\TextInput::make('phone')
                                        ->label('شماره تماس')
                                        ->tel(),
                                ]),
                        ]),
                    Forms\Components\Select::make('projects')
                        ->label('پروژه')->multiple()
                        ->relationship('projects', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('cartables')
                        ->label('گیرنده درخواست (افزودن به کارپوشه)')
                        ->relationship('users', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('peiroow_letter_id')
                        ->label('پیرو')
                        ->relationship('letter', 'subject')
                        ->searchable()
                        ->preload()
                    ,
                ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id','desc')
            ->columns([
                TextColumn::make('id')->label('ثبت')->searchable()->sortable(),
                TextColumn::make('subject')->label('موضوع')->searchable(),
                TextColumn::make('customer_id')->label('صاحب')
                    ->html()->alignCenter()
                    ->state(function (Model $record): string {
                    $customers = $record->customers()->get(['name','code_melli']);
                    $string = '';
                    foreach ($customers as $customer){
                        $string .= $customer->name .'-'. $customer->code_melli . "<br>";
                    }
                    return $string;
                }),
                TextColumn::make('status')->label('وضعیت')->state(function (Model $record): string {
                    return letter::getStatusLabel($record->status);
                })->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type.name')->label('نوع')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')->label('ثبت کننده')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد')->jalaliDateTime(),
                Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ آخرین ویرایش')->jalaliDateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
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
                    ->options(letter::getStatusListDefine())->label('وضعیت')
                ,
                SelectFilter::make('titleholder')
                    ->label('گیرنده نامه')
                    ->relationship('titleholder', 'name')
                    ->getOptionLabelsUsing(fn (Model $record) => "{$record->name} - {$record->official} ، {$record->organ()->first('name')->name}")
                    ->searchable()
                    ->preload()
                ,
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
            ])->filtersFormColumns(3)
            ->actions([
                Tables\Actions\EditAction::make(),
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
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLetters::route('/'),
            'create' => Pages\CreateLetter::route('/create'),
            'edit' => Pages\EditLetter::route('/{record}/edit'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }



}
