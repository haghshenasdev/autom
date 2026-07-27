<?php

namespace App\Filament\Resources\CustomerResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
			'birth_date' => 'required|date',
			'code_melli' => 'required|string',
			'phone' => 'required|string',
			'city_id' => 'required'
		];
    }
}
