<?php

namespace App\Filament\Resources\CartableResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\CartableResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\CartableResource\Api\Transformers\CartableTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = CartableResource::class;


    /**
     * Show Cartable
     *
     * @param Request $request
     * @return CartableTransformer
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

        return new CartableTransformer($query);
    }
}
