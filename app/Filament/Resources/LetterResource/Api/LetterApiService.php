<?php
namespace App\Filament\Resources\LetterResource\Api;

use Rupadana\ApiService\ApiService;
use App\Filament\Resources\LetterResource;
use Illuminate\Routing\Router;


class LetterApiService extends ApiService
{
    protected static string | null $resource = LetterResource::class;

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
