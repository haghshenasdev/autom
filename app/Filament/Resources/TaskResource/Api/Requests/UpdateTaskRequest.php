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
			'status' => 'required|integer',
			'progress' => 'required|integer',
			'description' => 'required|string',
			'completed' => 'required',
			'completed_at' => 'required',
			'started_at' => 'required',
			'ended_at' => 'required',
			'repeat' => 'required',
			'amount' => 'required',
			'created_by' => 'required',
			'Responsible_id' => 'required',
			'city_id' => 'required',
			'organ_id' => 'required',
			'minutes_id' => 'required'
		];
    }
}
