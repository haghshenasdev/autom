<?php
namespace App\Filament\Resources\MinutesResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\MinutesResource;
use Illuminate\Routing\Router;


class MinutesApiService extends ApiService
{
    protected static string | null $resource = MinutesResource::class;

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
