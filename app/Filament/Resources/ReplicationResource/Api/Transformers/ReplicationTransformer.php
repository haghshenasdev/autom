<?php
namespace App\Filament\Resources\ReplicationResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Replication;

/**
 * @property Replication $resource
 */
class ReplicationTransformer extends JsonResource
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
