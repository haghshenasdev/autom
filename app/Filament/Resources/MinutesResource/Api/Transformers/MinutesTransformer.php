<?php
namespace App\Filament\Resources\MinutesResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Minutes;

/**
 * @property Minutes $resource
 */
class MinutesTransformer extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'text' => $this->text,
            'file' => $this->file,
            'date' => $this->date,
            'typer_id' => $this->typer_id,
            'task_id' => $this->task_id,
            'updated_at' => $this->updated_at,

            'typer' => $this->whenLoaded('typer', function () {
                return [
                    'id' => $this->typer->id,
                    'name' => $this->typer->name,
                    'avatar_url' => $this->typer->avatar_url,
                ];
            }),
            'task_creator' => $this->whenLoaded('task_creator', function () {
                return [
                    'id' => $this->task_creator->id,
                    'name' => $this->task_creator->name,
                ];
            }),

            'organs' => $this->whenLoaded('organ', function () {
                return $this->organ->map(function ($organ) {
                    return [
                        'id' => $organ->id,
                        'name' => $organ->name,
                    ];
                });
            }),

            'group' => $this->whenLoaded('group', function () {
                return $this->group->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                    ];
                });
            }),

            'created_at' => $this->created_at,
        ];
    }
}
