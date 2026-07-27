<?php
namespace App\Filament\Resources\TypeResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\TypeResource;
use Illuminate\Routing\Router;


class TypeApiService extends ApiService
{
    protected static string | null $resource = TypeResource::class;

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
