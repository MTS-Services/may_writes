<?php

namespace App\Http\Requests\Admin;

use App\Enums\WritingWorkflowStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWritingRequestWorkflowStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'workflow_status' => ['required', 'string', Rule::in(WritingWorkflowStatus::values())],
        ];
    }
}
