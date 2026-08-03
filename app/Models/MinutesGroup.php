<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Rupadana\ApiService\Contracts\HasAllowedFilters;
use Rupadana\ApiService\Contracts\HasAllowedSorts;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;

class MinutesGroup extends Model implements HasAllowedSorts,HasAllowedFilters
{
    use HasFactory;


    protected $fillable = [
        'name',
        'parent_id',
    ];

    public function minutes(){
        return $this->belongsToMany(Minutes::class);
    }

    public function parent(){
        return $this->belongsTo(MinutesGroup::class);
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

    public static function getAllowedSorts(): array
    {
        return [
            'id',
            'name',
            'parent_id',
        ];
    }

    public static function getAllowedFilters(): array
    {
        return [
            'id',
            'name',
            'parent_id',
            AllowedFilter::callback(
                'search',
                function (Builder $query, $value) {

                    $query->where(function ($q) use ($value) {

                        if (is_numeric($value)) {
                            $q->orWhere('id', $value);
                        }

                        $q->orWhere('name', 'LIKE', "%{$value}%");

                    });

                }
            ),
        ];
    }
}
