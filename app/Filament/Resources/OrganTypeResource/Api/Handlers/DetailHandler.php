<?php

namespace App\Filament\Resources\OrganTypeResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\OrganTypeResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\OrganTypeResource\Api\Transformers\OrganTypeTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = OrganTypeResource::class;


    /**
     * Show OrganType
     *
     * @param Request $request
     * @return OrganTypeTransformer
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

        return new OrganTypeTransformer($query);
    }
}
