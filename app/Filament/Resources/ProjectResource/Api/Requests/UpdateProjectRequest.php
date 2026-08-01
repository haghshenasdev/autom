<?php

namespace App\Filament\Resources\ProjectResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
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
			'description' => 'nullable|string',
			'required_amount' => 'nullable|integer',
			'user_id' => 'nullable',
			'organ_id' => 'nullable',
			'city_id' => 'nullable',
			'status' => 'nullable',
			'amount' => 'nullable'
		];
    }
}
