<?php

namespace App\Filament\Resources\MinutesResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMinutesRequest extends FormRequest
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
			'text' => 'required|string',
			'file' => 'required|string',
			'date' => 'required',
			'typer_id' => 'required',
			'task_id' => 'required'
		];
    }
}
