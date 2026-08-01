<?php

namespace App\Filament\Resources\ProjectResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\ProjectResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\ProjectResource\Api\Transformers\ProjectTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ProjectResource::class;


    /**
     * Show Project
     *
     * @param Request $request
     * @return ProjectTransformer
     */
    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = static::getEloquentQuery();

        $query = QueryBuilder::for($query)
            ->with([
                'organ',
                'city',
                'user',
                'group',
            ])
            ->where(static::getKeyName(), $id)
            ->first();

        if (!$query) return static::sendNotFoundResponse();

        return new ProjectTransformer($query);
    }
}
