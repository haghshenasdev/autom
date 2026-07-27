<?php
namespace App\Filament\Resources\AnswerResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Answer;

/**
 * @property Answer $resource
 */
class AnswerTransformer extends JsonResource
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
