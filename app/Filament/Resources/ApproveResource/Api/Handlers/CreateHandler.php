<?php
namespace App\Filament\Resources\ApproveResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\ApproveResource;
use App\Filament\Resources\ApproveResource\Api\Requests\CreateApproveRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = ApproveResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Approve
     *
     * @param CreateApproveRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateApproveRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}