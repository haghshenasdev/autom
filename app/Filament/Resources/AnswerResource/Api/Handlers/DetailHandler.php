<?php

namespace App\Filament\Resources\AnswerResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\AnswerResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\AnswerResource\Api\Transformers\AnswerTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = AnswerResource::class;


    /**
     * Show Answer
     *
     * @param Request $request
     * @return AnswerTransformer
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

        return new AnswerTransformer($query);
    }
}
