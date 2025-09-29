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
    ];

    public function approve()
    {
        return $this->belongsToMany(Approve::class);
    }

    public function Titleholders(): HasMany
    {
        return $this->hasMany(Titleholder::class);
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
