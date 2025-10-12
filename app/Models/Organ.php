<?php

namespace App\Models;

use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organ extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'organ_type_id',
    ];

    public function type()
    {
        return $this->belongsTo(OrganType::class,'organ_type_id');
    }

    public function approve()
    {
        return $this->belongsToMany(Approve::class);
    }

    public function Titleholders(): HasMany
    {
        return $this->hasMany(Titleholder::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(letter::class);
    }


    public function minutes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Minutes::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public static function formSchema()
    {
        return [
            TextInput::make('name')
                ->required()
                ->label('نام')
                ->maxLength(255),
            TextInput::make('address')
                ->label('آدرس'),
            TextInput::make('phone')
                ->label('شماره تماس')
                ->tel(),
        ];
    }
}
