<?php
namespace App\Filament\Resources\CartableResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\CartableResource;
use Illuminate\Routing\Router;


class CartableApiService extends ApiService
{
    protected static string | null $resource = CartableResource::class;

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
