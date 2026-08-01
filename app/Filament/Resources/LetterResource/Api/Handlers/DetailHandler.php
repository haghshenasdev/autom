<?php

namespace App\Filament\Resources\LetterResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\LetterResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\LetterResource\Api\Transformers\LetterTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = LetterResource::class;


    /**
     * Show Letter
     *
     * @param Request $request
     * @return LetterTransformer
     */
    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = static::getEloquentQuery()->with([
            'user',
            'users',
            'type',
            'organ',
            'daftar',
            'letter',

            'customers',
            'organs_owner',
            'users',
            'projects',

            'Answer',
            'referrals',
            'replications',
            'Appendix',
        ]);

        $query = QueryBuilder::for(
            $query->where(static::getKeyName(), $id)
        )
            ->first();

        if (!$query) return static::sendNotFoundResponse();

        return new LetterTransformer($query);
    }
}
