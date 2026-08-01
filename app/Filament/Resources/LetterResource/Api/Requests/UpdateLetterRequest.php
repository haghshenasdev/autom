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
			'description' => 'nullable|string',
			'summary' => 'nullable|string',
			'file' => 'string',
			'mokatebe' => 'nullable|string',
			'kind' => 'required',
			'type_id' => 'nullable',
			'status' => 'nullable|integer',
			'organ_id' => 'nullable',
			'daftar_id' => 'required|integer',
			'peiroow_letter_id' => 'nullable|integer'
		];
    }
}
