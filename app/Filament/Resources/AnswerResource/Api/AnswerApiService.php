<?php
namespace App\Filament\Resources\AnswerResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\AnswerResource;
use Illuminate\Routing\Router;


class AnswerApiService extends ApiService
{
    protected static string | null $resource = AnswerResource::class;

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
