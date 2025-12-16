<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Resources\LetterResource;
use App\Models\Letter;
use App\Models\Referral;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LettersRelationManager extends RelationManager
{
    protected static string $relationship = 'letters';

    protected static ?string $label = 'نامه';

    protected static ?string $pluralLabel = 'نامه';

    protected static ?string $modelLabel = 'نامه';

    protected static ?string $title = 'نامه ها';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('subject')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return LetterResource::table($table);

//        return $table
//            ->recordTitleAttribute('subject')
//            ->columns([
//                TextColumn::make('id')->label('ثبت')->searchable(),
//                TextColumn::make('subject')->label('موضوع')->searchable(),
//                TextColumn::make('status')->label('وضعیت')->state(function (Model $record): string {
//                    return Letter::getStatusLabel($record->status);
//                }),
//                TextColumn::make('type.name')->label('نوع'),
//                TextColumn::make('user.name')->label('ثبت کننده'),
//                Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد')->jalaliDateTime(),
//                Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ آخرین ویرایش')->jalaliDateTime(),
//            ])
//            ->filters([
//                //
//            ])
//            ->headerActions([
//            ])
//            ->actions([
//                Action::make('Open')->label('نمایش نامه')
//                    ->url(fn (Letter $record): string => LetterResource::getUrl('edit',[$record->id]))
//                    ->openUrlInNewTab(),
//                Tables\Actions\DeleteAction::make(),
//            ])
//            ->bulkActions([
//                Tables\Actions\BulkActionGroup::make([
//                    Tables\Actions\DeleteBulkAction::make(),
//                ]),
//            ])
//            ->emptyStateActions([
//            ]);
    }
}
