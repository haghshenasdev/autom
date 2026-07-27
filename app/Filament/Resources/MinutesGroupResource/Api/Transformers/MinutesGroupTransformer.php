<?php
namespace App\Filament\Resources\MinutesGroupResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\MinutesGroup;

/**
 * @property MinutesGroup $resource
 */
class MinutesGroupTransformer extends JsonResource
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
