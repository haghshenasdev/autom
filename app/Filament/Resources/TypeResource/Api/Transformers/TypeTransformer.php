<?php
namespace App\Filament\Resources\TypeResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Type;

/**
 * @property Type $resource
 */
class TypeTransformer extends JsonResource
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
