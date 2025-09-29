<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use App\Models\AppendixOther;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Date;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AppendixOthersRelationManager extends RelationManager
{
    protected static string $relationship = 'appendix_others';

    protected static ?string $label = 'ضمیمه ها';

    protected static ?string $pluralLabel = 'ضمیمه';

    protected static ?string $modelLabel = 'ضمیمه';

    protected static ?string $title = 'ضمیمه ها';

    public function form(Form $form): Form
    {
        $formSchema = AppendixOther::formSchema();
        $formSchema[] = FileUpload::make('file')
            ->label('فایل')
            ->disk('private_appendix_other')
            ->downloadable()
            ->visibility('private')
            ->imageEditor()
            ->required()
            //->hiddenOn('aaaa')
//                    ->getUploadedFileUsing(fn (?Model $record) => $record->getFilePath($this->ownerRecord->id))
            ->getUploadedFileNameForStorageUsing( fn (TemporaryUploadedFile $file,?Model $record) => $this->getFileNamePath($file,$record));
        return $form
            ->schema($formSchema);
    }

    private function getFileNamePath(TemporaryUploadedFile $file,?Model $record) : string
    {
        $path = "task/{$this->ownerRecord->id}";
        return "{$path}/".
            Date::now()->format('Y-m-d_H-i-s') .
            "." . explode('/',$file->getMimeType())[1];

    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\AssociateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->mutateRecordDataUsing(function (array $data){
                    $data['file'] = $this->cachedMountedTableActionRecord->getFilePath($this->ownerRecord->id);
                    return $data;
                })->mutateFormDataUsing(function (array $data){
                    if ($data['file'] == $this->cachedMountedTableActionRecord->getFilePath($this->ownerRecord->id))
                    {
                        $data['file'] = explode('.',$data['file'])[1];
                    }
                    return $data;
                }),
                Tables\Actions\DissociateAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DissociateBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
