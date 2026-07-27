<?php
namespace App\Filament\Resources\ApproveResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\ApproveResource;
use Illuminate\Routing\Router;


class ApproveApiService extends ApiService
{
    protected static string | null $resource = ApproveResource::class;

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
