<?php
namespace App\Filament\Resources\LetterResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Letter;

/**
 * @property Letter $resource
 */
class LetterTransformer extends JsonResource
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
