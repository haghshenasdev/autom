<?php
namespace App\Filament\Resources\MinutesGroupResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\MinutesGroupResource;
use Illuminate\Routing\Router;


class MinutesGroupApiService extends ApiService
{
    protected static string | null $resource = MinutesGroupResource::class;

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
