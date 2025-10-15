<?php

namespace App\Filament\Resources\TypeResource\RelationManagers;

use App\Models\Letter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LettersRelationManager extends RelationManager
{
    protected static string $relationship = 'letter';
//
//    public function form(Form $form): Form
//    {
//        return $form
//            ->schema([
//                Forms\Components\TextInput::make('subject')
//                    ->required()
//                    ->maxLength(255),
//            ]);
//    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->columns([
                TextColumn::make('id')->label('ثبت')->searchable(),
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
                    return Letter::getStatusLabel($record->status);
                }),
                TextColumn::make('user.name')->label('ثبت کننده'),
                Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد')->jalaliDateTime(),
                Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ آخرین ویرایش')->jalaliDateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
//                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
//                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
//                Tables\Actions\CreateAction::make(),
            ]);
    }
}
