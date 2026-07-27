<?php

namespace App\Filament\Resources\MinutesGroupResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\MinutesGroupResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\MinutesGroupResource\Api\Transformers\MinutesGroupTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = MinutesGroupResource::class;


    /**
     * Show MinutesGroup
     *
     * @param Request $request
     * @return MinutesGroupTransformer
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

        return new MinutesGroupTransformer($query);
    }
}
