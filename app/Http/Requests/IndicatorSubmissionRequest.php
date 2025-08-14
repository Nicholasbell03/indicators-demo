<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndicatorSubmissionRequest extends FormRequest
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
            'indicator_task_id' => ['required', 'exists:indicator_tasks,id'],
            'value' => ['required', 'string', 'max:65535'],
            'comment' => ['nullable', 'string', 'max:65535'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['nullable'], // Can be either a file or an array with id
        ];
    }
}
