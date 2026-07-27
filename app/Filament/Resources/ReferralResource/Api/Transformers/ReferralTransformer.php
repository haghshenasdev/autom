<?php
namespace App\Filament\Resources\ReferralResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Referral;

/**
 * @property Referral $resource
 */
class ReferralTransformer extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
