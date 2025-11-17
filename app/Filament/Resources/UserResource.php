<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Phpsa\FilamentPasswordReveal\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'سیستم';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $label = "کاربر";


    protected static ?string $pluralModelLabel = "کاربران";

    protected static ?string $pluralLabel = "کاربر";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('نام')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->label('ایمیل')
                    ->required(),
                Password::make('password')->autocomplete('new_password')
                    ->label('رمز عبور')
                    ->generatable(true)->copyable(true)
                    ->dehydrated(fn ($state) => filled($state)),
                Forms\Components\Select::make('roles')
                    ->label('تعیین دسترسی')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->searchable(),
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('email')->label('ایمیل')->searchable(),
                TextColumn::make('roles.name')->label('دسترسی'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('Open')->label('گزارش فعالیت')->icon('heroicon-o-chart-pie')
                    ->url(fn ($record) => route('filament.admin.resources.users.report',['id' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'report' => Pages\UserReport::route('/{id}/report'),
        ];
    }
}
