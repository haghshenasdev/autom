<?php
namespace App\Filament\Resources\OrganTypeResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\OrganType;

/**
 * @property OrganType $resource
 */
class OrganTypeTransformer extends JsonResource
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
