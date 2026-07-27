<?php

namespace App\Filament\Resources\LetterResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLetterRequest extends FormRequest
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
			'subject' => 'required|string',
			'description' => 'required|string',
			'summary' => 'required|string',
			'file' => 'required|string',
			'mokatebe' => 'required|string',
			'kind' => 'required',
			'type_id' => 'required',
			'status' => 'required|integer',
			'user_id' => 'required',
			'organ_id' => 'required',
			'daftar_id' => 'required',
			'peiroow_letter_id' => 'required'
		];
    }
}
