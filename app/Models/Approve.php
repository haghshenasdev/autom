<?php

namespace App\Models;

use App\Models\Traits\HasStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approve extends Model
{
    use HasFactory,HasStatus;

    // مصوبه ها

    protected $fillable = [
        'minute_id',
        'amount',
        'city_id',
        'status',
        'description',
        'title',
    ];


    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function organ(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Organ::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minutes::class);
    }

    public static function formSchema() : array
    {
        return [
            TextInput::make('title')
                ->label('عنوان')->required()
                ->maxLength(255)->required(),
            Textarea::make('description')
                ->label('متن')->maxLength(500)
            ,
            TextInput::make('amount')->numeric()->nullable()->suffix('ریال')
                ->label('اعتبار اخذ شده')
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
            ,
            Select::make('city_id')
                ->label('شهر')
                ->relationship('city', 'name')
                ->searchable()
                ->preload(),
            Select::make('organ_id')
                ->label('دستگاه اجرایی')->multiple()
                ->relationship('organ', 'name')
                ->searchable()
                ->preload(),
            Select::make('project_id')
                ->label('پروژه')->multiple()
                ->relationship('project', 'name')
                ->searchable()
                ->preload(),
            Select::make('status')
                ->options(self::getStatusListDefine())->label('وضعیت')
                ->hiddenOn('create')
                ->default(null)
        ];
    }
}
