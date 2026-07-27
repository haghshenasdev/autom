<?php

namespace App\Filament\Resources\TypeResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\TypeResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\TypeResource\Api\Transformers\TypeTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = TypeResource::class;


    /**
     * Show Type
     *
     * @param Request $request
     * @return TypeTransformer
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

        return new TypeTransformer($query);
    }
}
