<?php
namespace App\Filament\Resources\OrganResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\OrganResource;
use Illuminate\Routing\Router;


class OrganApiService extends ApiService
{
    protected static string | null $resource = OrganResource::class;

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
