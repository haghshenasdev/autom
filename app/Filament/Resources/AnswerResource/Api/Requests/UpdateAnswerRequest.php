<?php

namespace App\Filament\Resources\AnswerResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnswerRequest extends FormRequest
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
			'letter_id' => 'required',
			'result' => 'required|string',
			'summary' => 'required|string',
			'file' => 'required|string',
			'organ_id' => 'required'
		];
    }
}
