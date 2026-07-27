<?php

namespace App\Filament\Resources\ProjectResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProjectRequest extends FormRequest
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
			'description' => 'required|string',
			'required_amount' => 'required|integer',
			'user_id' => 'required',
			'organ_id' => 'required',
			'city_id' => 'required',
			'status' => 'required',
			'amount' => 'required'
		];
    }
}
