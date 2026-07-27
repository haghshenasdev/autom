<?php

namespace App\Filament\Resources\AppendixResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\AppendixResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\AppendixResource\Api\Transformers\AppendixTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = AppendixResource::class;


    /**
     * Show Appendix
     *
     * @param Request $request
     * @return AppendixTransformer
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

        return new AppendixTransformer($query);
    }
}
