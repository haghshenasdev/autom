<?php

namespace App\Models;

use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
    ];


    public function group()
    {
        return $this->belongsToMany(ContentGroup::class);
    }

    public static function formSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('عنوان'),
            SelectTree::make('group_id')->label('دسته بندی')
                ->relationship('group', 'name', 'parent_id')->searchable()
                ->enableBranchNode()->createOptionForm(ContentGroup::formSchema()),
            RichEditor::make('body')
                ->label('محتوا')
                ->fileAttachmentsDisk('private2')
                ->fileAttachmentsVisibility('private')
                ->required(),
        ];
    }
}
