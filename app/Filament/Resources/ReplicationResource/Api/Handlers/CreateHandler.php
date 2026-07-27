<?php
namespace App\Filament\Resources\ReplicationResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\ReplicationResource;
use App\Filament\Resources\ReplicationResource\Api\Requests\CreateReplicationRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = ReplicationResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Replication
     *
     * @param CreateReplicationRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateReplicationRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}