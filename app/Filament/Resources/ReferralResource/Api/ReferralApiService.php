<?php
namespace App\Filament\Resources\ReferralResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\ReferralResource;
use Illuminate\Routing\Router;


class ReferralApiService extends ApiService
{
    protected static string | null $resource = ReferralResource::class;

    public static function handlers() : array
    {
        return [
            Handlers\CreateHandler::class,
            Handlers\UpdateHandler::class,
            Handlers\DeleteHandler::class,
            Handlers\PaginationHandler::class,
            Handlers\DetailHandler::class
        ];

    }
}
