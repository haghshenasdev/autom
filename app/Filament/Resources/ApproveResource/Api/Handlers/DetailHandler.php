<?php

namespace App\Filament\Resources\ApproveResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\ApproveResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\ApproveResource\Api\Transformers\ApproveTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ApproveResource::class;


    /**
     * Show Approve
     *
     * @param Request $request
     * @return ApproveTransformer
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

        return new ApproveTransformer($query);
    }
}
