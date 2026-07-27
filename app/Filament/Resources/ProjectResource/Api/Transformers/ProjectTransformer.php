<?php
namespace App\Filament\Resources\ProjectResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Project;

/**
 * @property Project $resource
 */
class ProjectTransformer extends JsonResource
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
