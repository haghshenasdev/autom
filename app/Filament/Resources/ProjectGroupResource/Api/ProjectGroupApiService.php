<?php
namespace App\Filament\Resources\ProjectGroupResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\ProjectGroupResource;
use Illuminate\Routing\Router;


class ProjectGroupApiService extends ApiService
{
    protected static string | null $resource = ProjectGroupResource::class;

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
