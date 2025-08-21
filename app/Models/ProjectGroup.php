<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;

class ProjectGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'parent_id',
    ];

    public function projects(){
        return $this->belongsToMany(Project::class);
    }

    public function parent(){
        return $this->belongsTo(ProjectGroup::class);
    }

    public static function formSchema()
    {
        return [
            Forms\Components\TextInput::make('name')
                ->required()->label('عنوان')
                ->maxLength(255),
            Forms\Components\Select::make('parent_id')->label('زیر مجموعه')
                ->relationship('parent', 'name')
                ->searchable()->preload()
        ];
    }
}
