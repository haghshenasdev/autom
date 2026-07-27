<?php
namespace App\Filament\Resources\CartableResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Cartable;

/**
 * @property Cartable $resource
 */
class CartableTransformer extends JsonResource
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
