<?php
namespace App\Filament\Resources\ContentGroupResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ContentGroup;

/**
 * @property ContentGroup $resource
 */
class ContentGroupTransformer extends JsonResource
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
