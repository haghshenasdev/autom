<?php

namespace App\Filament\Resources\ContentGroupResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\ContentGroupResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\ContentGroupResource\Api\Transformers\ContentGroupTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ContentGroupResource::class;


    /**
     * Show ContentGroup
     *
     * @param Request $request
     * @return ContentGroupTransformer
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

        return new ContentGroupTransformer($query);
    }
}
