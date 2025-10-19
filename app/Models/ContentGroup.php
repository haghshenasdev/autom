<?php

namespace App\Models;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentGroup extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'parent_id',
    ];

    public function contents(){
        return $this->belongsToMany(Content::class);
    }

    public function parent(){
        return $this->belongsTo(ContentGroup::class);
    }

    public static function formSchema()
    {
        return [
            TextInput::make('name')
                ->required()->label('عنوان')
                ->maxLength(255),
            Select::make('parent_id')->label('زیر مجموعه')
                ->relationship('parent', 'name')
                ->searchable()->preload()
        ];
    }
}
