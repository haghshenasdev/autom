<?php
namespace App\Filament\Resources\AppendixResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Appendix;

/**
 * @property Appendix $resource
 */
class AppendixTransformer extends JsonResource
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
