<?php

namespace App\Filament\Resources\OrganResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\OrganResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\OrganResource\Api\Transformers\OrganTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = OrganResource::class;


    /**
     * Show Organ
     *
     * @param Request $request
     * @return OrganTransformer
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

        return new OrganTransformer($query);
    }
}
