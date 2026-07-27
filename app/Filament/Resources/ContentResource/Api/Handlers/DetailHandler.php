<?php

namespace App\Filament\Resources\ContentResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\ContentResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\ContentResource\Api\Transformers\ContentTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ContentResource::class;


    /**
     * Show Content
     *
     * @param Request $request
     * @return ContentTransformer
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

        return new ContentTransformer($query);
    }
}
