<?php

namespace App\Filament\Resources\CityResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\CityResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\CityResource\Api\Transformers\CityTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = CityResource::class;


    /**
     * Show City
     *
     * @param Request $request
     * @return CityTransformer
     */
    public function handler(Request $request)
    {
        $id = $request->route('id');
        
        $query = static::getEloquentQuery();

        $query = QueryBuilder::for(
            $query->where(static::getKeyName(), $id)
        )
            ->first();

        if (!$query) return static::sendNotFoundResponse();

        return new CityTransformer($query);
    }
}
