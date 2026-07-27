<?php

namespace App\Filament\Resources\ApproveResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateApproveRequest extends FormRequest
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
			'title' => 'required|string',
			'description' => 'required|string',
			'status' => 'required',
			'city_id' => 'required',
			'task_id' => 'required',
			'amount' => 'required',
			'time' => 'required|integer',
			'minute_id' => 'required'
		];
    }
}
