<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrelloSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template_board_id' => ['nullable', 'string', 'max:50'],
            'background_id' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array{template_board_id: ?string, background_id: ?string}
     */
    public function normalized(): array
    {
        $templateBoardId = $this->input('template_board_id');
        $backgroundId = $this->input('background_id');

        return [
            'template_board_id' => filled($templateBoardId) ? trim((string) $templateBoardId) : null,
            'background_id' => filled($backgroundId) ? trim((string) $backgroundId) : null,
        ];
    }
}
