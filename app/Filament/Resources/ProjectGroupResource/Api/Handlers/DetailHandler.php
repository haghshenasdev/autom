<?php

namespace App\Filament\Resources\ProjectGroupResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\ProjectGroupResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\ProjectGroupResource\Api\Transformers\ProjectGroupTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ProjectGroupResource::class;


    /**
     * Show ProjectGroup
     *
     * @param Request $request
     * @return ProjectGroupTransformer
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

        return new ProjectGroupTransformer($query);
    }
}
