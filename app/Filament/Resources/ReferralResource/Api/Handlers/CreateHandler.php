<?php
namespace App\Filament\Resources\ReferralResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\ReferralResource;
use App\Filament\Resources\ReferralResource\Api\Requests\CreateReferralRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = ReferralResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Referral
     *
     * @param CreateReferralRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateReferralRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}