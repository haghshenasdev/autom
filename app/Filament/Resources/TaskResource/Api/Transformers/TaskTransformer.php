<?php
namespace App\Filament\Resources\TaskResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Task;

/**
 * @property Task $resource
 */
class TaskTransformer extends JsonResource
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
            'progress' => $this->progress,
            'completed' => $this->completed,

            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'completed_at' => $this->completed_at,

            'amount' => $this->amount,
            'repeat' => $this->repeat,

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

            'creator' => $this->whenLoaded('creator', function () {

                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'avatar_url'=>$this->creator->avatar_url ?? null,
                ];

            }),



            /*
            |--------------------------------------------------------------------------
            | مسئول
            |--------------------------------------------------------------------------
            */

            'responsible' => $this->whenLoaded('responsible', function () {

                return [
                    'id' => $this->responsible->id,
                    'name' => $this->responsible->name,
                    'avatar_url'=>$this->responsible->avatar_url ?? null,
                ];

            }),



            /*
            |--------------------------------------------------------------------------
            | صورتجلسه مرتبط
            |--------------------------------------------------------------------------
            */

            'minutes' => $this->whenLoaded('minutes', function () {

                return [
                    'id' => $this->minutes->id,
                    'title' => $this->minutes->title,
                    'date' => $this->minutes->date,
                ];

            }),



            /*
            |--------------------------------------------------------------------------
            | پروژه ها
            |--------------------------------------------------------------------------
            */

            'projects' => $this->whenLoaded('project', function () {

                return $this->project->map(function ($item) {

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];

                });

            }),



            /*
            |--------------------------------------------------------------------------
            | دسته بندی
            |--------------------------------------------------------------------------
            */

            'task_groups' => $this->whenLoaded('task_group', function () {

                return $this->task_group->map(function ($item) {

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];

                });

            }),



            /*
            |--------------------------------------------------------------------------
            | فایل های ضمیمه
            |--------------------------------------------------------------------------
            */

            'appendix_others' => $this->whenLoaded('appendix_others', function () {

                return $this->appendix_others->map(function ($item) {

                    return [
                        'id' => $item->id,
                        'title' => $item->title,
                        'file' => $item->file,
                    ];

                });

            }),


        ];
    }
}
