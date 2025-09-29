<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApproveResource\Pages;
use App\Filament\Resources\ApproveResource\RelationManagers;
use App\Models\Approve;
use App\Models\letter;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ApproveResource extends Resource
{
    protected static ?string $model = Approve::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $label = "مصوبه";

    protected static ?string $navigationGroup = 'صورت جلسه';


    protected static ?string $pluralModelLabel = "مصوبه ها";

    protected static ?string $pluralLabel = "مصوبه";

    public static function form(Form $form): Form
    {
        $formSchema = Approve::formSchema();
        $formSchema[] = Select::make('minute_id')
            ->label('صورت جلسه')
            ->relationship('minute', 'title')
            ->searchable()->required()
            ->preload();

        return $form
            ->schema($formSchema);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id','desc')
            ->columns([
                TextColumn::make('id')->label('ثبت')
                    ->searchable()->sortable(),
                TextColumn::make('title')->label('عنوان')
                    ->searchable(),
                TextColumn::make('minute.title')->label('صورت جلسه'),
                TextColumn::make('project.name')->label('پروژه')->listWithLineBreaks()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('organ.name')->label('اداره')->listWithLineBreaks()->toggleable(),
                TextColumn::make('amount')->label('اعتبار')->toggleable()->sortable()->numeric()->suffix('ریال'),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => Approve::getStatusColor($state))
                    ->state(function (Model $record): string {
                    return Approve::getStatusLabel($record->status);})->sortable()->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('city.name')->label('شهر')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد')->sortable()->jalaliDateTime()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ آخرین ویرایش')->sortable()->jalaliDateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('minute_id')
                    ->label('صورت جلسه')->multiple()->preload()
                    ->relationship('minute','title'),
                SelectFilter::make('city_id')
                    ->label('شهر')->multiple()->preload()
                    ->relationship('city','name'),
                SelectFilter::make('project')
                    ->label('پروژه')->multiple()->preload()
                    ->relationship('project','name'),
                SelectFilter::make('organ')
                    ->label('اداره')->multiple()->preload()
                    ->relationship('organ','name'),
                SelectFilter::make('status')
                    ->options(Approve::getStatusListDefine())->label('وضعیت')
            ])->filtersFormColumns(2)
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('Open')->label('نمایش صورت جلسه مربوطه')->iconButton()->icon('heroicon-o-document-text')
                    ->url(fn (Approve $record): string => MinutesResource::getUrl('edit',[$record->minute_id]))
                    ->openUrlInNewTab()
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
            'index' => Pages\ListApproves::route('/'),
            'create' => Pages\CreateApprove::route('/create'),
            'edit' => Pages\EditApprove::route('/{record}/edit'),
        ];
    }
}
