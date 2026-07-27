<?php

namespace App\Filament\Resources\CustomerResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\CustomerResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\CustomerResource\Api\Transformers\CustomerTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = CustomerResource::class;


    /**
     * Show Customer
     *
     * @param Request $request
     * @return CustomerTransformer
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

        return new CustomerTransformer($query);
    }
}
