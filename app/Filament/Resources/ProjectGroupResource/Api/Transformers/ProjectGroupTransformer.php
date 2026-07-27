<?php
namespace App\Filament\Resources\ProjectGroupResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ProjectGroup;

/**
 * @property ProjectGroup $resource
 */
class ProjectGroupTransformer extends JsonResource
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
