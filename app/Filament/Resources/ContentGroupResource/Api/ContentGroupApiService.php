<?php
namespace App\Filament\Resources\ContentGroupResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\ContentGroupResource;
use Illuminate\Routing\Router;


class ContentGroupApiService extends ApiService
{
    protected static string | null $resource = ContentGroupResource::class;

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
