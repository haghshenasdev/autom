<?php
namespace App\Filament\Resources\ReplicationResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\ReplicationResource;
use Illuminate\Routing\Router;


class ReplicationApiService extends ApiService
{
    protected static string | null $resource = ReplicationResource::class;

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
