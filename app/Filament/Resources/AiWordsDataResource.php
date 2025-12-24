<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiWordsDataResource\Pages;
use App\Filament\Resources\AiWordsDataResource\RelationManagers;
use App\Models\AiWordsData;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AiWordsDataResource extends Resource
{
    protected static ?string $model = AiWordsData::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';
    protected static ?string $navigationGroup = 'مدیریت هوش مصنوعی';
    protected static ?string $navigationLabel = 'داده‌های کلمات هوش مصنوعی';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('model_type')
                    ->disabled()
                    ->label('نوع مدل'),

                Forms\Components\TextInput::make('model_id')
                    ->disabled()
                    ->label('شناسه مدل'),

                Forms\Components\TextInput::make('target_field')
                    ->disabled()
                    ->label('فیلد هدف'),

                Forms\Components\TextInput::make('sensitivity')
                    ->numeric()
                    ->label('حساسیت'),

                // مدیریت کلمات مجاز
                Repeater::make('allowed_words')
                    ->label('کلمات مجاز')
                    ->schema([
                        TextInput::make('word')->label('کلمه'),
                        Repeater::make('synonyms')
                            ->schema([
                                TextInput::make('')->label('مترادف'),
                            ])
                            ->label('مترادف‌ها')
                            ->collapsible(),
                        Toggle::make('required')->label('ضروری'),
                        TextInput::make('order')->numeric()->label('ترتیب'),
                        TextInput::make('frequency')->numeric()->label('تعداد تکرار')->disabled(),
                        TextInput::make('percent')->numeric()->label('درصد')->disabled(),
                    ])
                    ->collapsible()
                    ->createItemButtonLabel('افزودن کلمه'),

                // مدیریت کلمات غیرمجاز
                Repeater::make('blocked_words')
                    ->label('کلمات غیرمجاز')
                    ->schema([
                        TextInput::make('word')->label('کلمه'),
                    ])
                    ->collapsible()
                    ->createItemButtonLabel('افزودن کلمه غیرمجاز'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('شناسه')->sortable(),
                Tables\Columns\TextColumn::make('model_type')->label('نوع مدل')->searchable(),
                Tables\Columns\TextColumn::make('model_id')->label('شناسه مدل'),
                Tables\Columns\TextColumn::make('model_title') ->label('عنوان مدل') ->getStateUsing(fn($record) => $record->model->title ?? $record->model->name ?? null)->searchable(),
                Tables\Columns\TextColumn::make('target_field')->label('فیلد هدف'),
                Tables\Columns\TextColumn::make('sensitivity')->label('حساسیت'),
                Tables\Columns\TextColumn::make('words_count') ->label('تعداد کلمات') ->getStateUsing(fn($record) => is_array($record->allowed_words) ? count($record->allowed_words) : 0) ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ ایجاد')->jalaliDateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین آموزش')->jalaliDateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('ویرایش'),
                Tables\Actions\Action::make('relearn')
                    ->label('آموزش مجدد')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (AiWordsData $record) {
                        // اینجا می‌توانی کلاس KeywordClassifier را صدا بزنی
                        app(\App\Services\AiKeywordClassifier::class)
                            ->learn(
                                $record->model_type::find($record->model_id),
                                $record->target_field,
                                $record->target_field,
                                null,
                                $record->sensitivity
                            );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiWordsData::route('/'),
            'create' => Pages\CreateAiWordsData::route('/create'),
            'edit' => Pages\EditAiWordsData::route('/{record}/edit'),
        ];
    }
}
