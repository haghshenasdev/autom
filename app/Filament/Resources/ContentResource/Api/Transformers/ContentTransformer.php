<?php
namespace App\Filament\Resources\ContentResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Content;

/**
 * @property Content $resource
 */
class ContentTransformer extends JsonResource
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
