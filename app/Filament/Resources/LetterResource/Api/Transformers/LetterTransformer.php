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
        return [

            'id'=>$this->id,

            'subject'=>$this->subject,
            'description'=>$this->description,
            'summary'=>$this->summary,
            'file'=>$this->file,
            'status'=>$this->status,
            'kind'=>$this->kind,

            'user'=>$this->whenLoaded('user',fn()=>[
                'id'=>$this->user->id,
                'name'=>$this->user->name,
                'image'=>$this->user->avatar_url ?? null,
            ]),


            'type'=>$this->whenLoaded('type',fn()=>[
                'id'=>$this->type->id,
                'name'=>$this->type->name,
            ]),


            'organ'=>$this->whenLoaded('organ',fn()=>[
                'id'=>$this->organ->id,
                'name'=>$this->organ->name,
            ]),


            'daftar'=>$this->whenLoaded('daftar',fn()=>[
                'id'=>$this->daftar->id,
                'name'=>$this->daftar->name,
            ]),


            'customers'=>$this->whenLoaded('customers',
                fn()=> $this->customers->map(fn($item)=>[
                    'id'=>$item->id,
                    'name'=>$item->name,
                ])
            ),


            'organs_owner'=>$this->whenLoaded('organs_owner',
                fn()=> $this->organs_owner->map(fn($item)=>[
                    'id'=>$item->id,
                    'name'=>$item->name,
                ])
            ),


            'projects'=>$this->whenLoaded('projects',
                fn()=> $this->projects->map(fn($item)=>[
                    'id'=>$item->id,
                    'name'=>$item->name,
                    'summary'=>$item->pivot->summary,
                ])
            ),


        ];
    }
}
