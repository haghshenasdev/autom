<?php

namespace App\Filament\Resources\ReferralResource\Api\Handlers;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\ReferralResource;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Http\Request;
use App\Filament\Resources\ReferralResource\Api\Transformers\ReferralTransformer;

class DetailHandler extends Handlers
{
    public static string | null $uri = '/{id}';
    public static string | null $resource = ReferralResource::class;


    /**
     * Show Referral
     *
     * @param Request $request
     * @return ReferralTransformer
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

        return new ReferralTransformer($query);
    }
}
