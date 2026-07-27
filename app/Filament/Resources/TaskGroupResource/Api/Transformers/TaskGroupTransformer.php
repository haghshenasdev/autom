<?php
namespace App\Filament\Resources\TaskGroupResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\TaskGroup;

/**
 * @property TaskGroup $resource
 */
class TaskGroupTransformer extends JsonResource
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
