<?php
namespace App\Filament\Resources\OrganTypeResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\OrganTypeResource;
use Illuminate\Routing\Router;


class OrganTypeApiService extends ApiService
{
    protected static string | null $resource = OrganTypeResource::class;

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
