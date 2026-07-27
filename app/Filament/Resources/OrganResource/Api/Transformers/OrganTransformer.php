<?php
namespace App\Filament\Resources\OrganResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Organ;

/**
 * @property Organ $resource
 */
class OrganTransformer extends JsonResource
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
