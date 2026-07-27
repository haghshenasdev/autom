<?php

namespace App\Filament\Resources\ReplicationResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\ReplicationResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\ReplicationResource\Api\Transformers\ReplicationTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ReplicationResource::class;


    /**
     * Show Replication
     *
     * @param Request $request
     * @return ReplicationTransformer
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

        return new ReplicationTransformer($query);
    }
}
