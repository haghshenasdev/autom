<?php
namespace App\Filament\Resources\TaskGroupResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\TaskGroupResource;
use Illuminate\Routing\Router;


class TaskGroupApiService extends ApiService
{
    protected static string | null $resource = TaskGroupResource::class;

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
