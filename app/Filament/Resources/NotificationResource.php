<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Filament\Resources\NotificationResource\RelationManagers;
use App\Models\Notification;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

class NotificationResource extends Resource
{
    protected static ?string $model = \Illuminate\Notifications\DatabaseNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $label = "اطلاع رسانی";

    protected static ?string $navigationGroup = 'اطلاع رسانی';


    protected static ?string $pluralModelLabel = "اطلاع رسانی ها";

    protected static ?string $pluralLabel = "اطلاع رسانی";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')->label('عنوان')->required(),
                Textarea::make('body')->label('متن')->required(),
                Select::make('recipient_id')
                    ->label('گیرنده')
                    ->options(\App\Models\User::pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data.title')->label('عنوان'),
                TextColumn::make('data.body')->label('متن'),
                TextColumn::make('notifiable_type')->label('نوع گیرنده'),
                TextColumn::make('created_at')->label('تاریخ ارسال')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListNotifications::route('/'),
            'edit' => Pages\EditNotification::route('/{record}/edit'),
        ];
    }
}
