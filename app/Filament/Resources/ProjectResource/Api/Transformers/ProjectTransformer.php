<?php
namespace App\Filament\Resources\ProjectResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Project;

/**
 * @property Project $resource
 */
class ProjectTransformer extends JsonResource
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

            // فیلدهای اصلی
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'required_amount' => $this->required_amount,
            'amount' => $this->amount,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,


            /*
            |--------------------------------------------------------------------------
            | ارگان
            |--------------------------------------------------------------------------
            */

            'organ' => $this->whenLoaded('organ', function () {

                return [
                    'id' => $this->organ->id,
                    'name' => $this->organ->name,
                ];

            }),



            /*
            |--------------------------------------------------------------------------
            | شهر
            |--------------------------------------------------------------------------
            */

            'city' => $this->whenLoaded('city', function () {

                return [
                    'id' => $this->city->id,
                    'name' => $this->city->name,
                ];

            }),



            /*
            |--------------------------------------------------------------------------
            | ایجاد کننده
            |--------------------------------------------------------------------------
            */

            'user' => $this->whenLoaded('user', function () {

                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'avatar_url'=>$this->user->avatar_url ?? null,
                ];

            }),


            /*
            |--------------------------------------------------------------------------
            | دسته بندی
            |--------------------------------------------------------------------------
            */

            'group' => $this->whenLoaded('group', function () {

                return $this->group->map(function ($item) {

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];

                });

            }),


        ];
    }
}
