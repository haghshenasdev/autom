<?php

namespace App\Models;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Titleholder extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'official',
        'organ_id',
    ];

    public function organ(): BelongsTo
    {
        return $this->belongsTo(Organ::class);
    }

    public function minutes(){
        return $this->belongsToMany(Minutes::class);
    }

    public function Replications(): HasMany
    {
        return $this->hasMany(Replication::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(letter::class);
    }

    public static function formSchema()
    {
        return [
            TextInput::make('name')
                ->required()
                ->label('نام')
                ->maxLength(255),
            TextInput::make('official')
                ->required()
                ->label('سمت'),
            TextInput::make('phone')
                ->label('شماره تماس')
                ->tel(),
            Select::make('organ_id')
                ->label('سازمان')
                ->relationship('organ', 'name')
                ->searchable()
                ->required()
                ->preload()
                ->createOptionForm(Organ::formSchema()),
        ];
    }
}
