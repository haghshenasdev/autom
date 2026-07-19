<?php
namespace App\Filament\Resources\MinutesResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Minutes;

/**
 * @property Minutes $resource
 */
class MinutesTransformer extends JsonResource
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
