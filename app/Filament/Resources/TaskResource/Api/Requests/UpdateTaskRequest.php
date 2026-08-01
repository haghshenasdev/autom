<?php

namespace App\Filament\Resources\TaskResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
			'name' => 'required|string',
			'status' => 'nullable|integer',
			'progress' => 'nullable|integer',
			'description' => 'string',
			'completed' => 'nullable',
			'completed_at' => 'nullable',
			'started_at' => 'nullable',
			'ended_at' => 'nullable',
			'repeat' => 'nullable',
			'amount' => 'nullable',
			'created_by' => 'required',
			'Responsible_id' => 'nullable',
			'city_id' => 'nullable',
			'organ_id' => 'nullable',
			'minutes_id' => 'nullable'
		];
    }
}
