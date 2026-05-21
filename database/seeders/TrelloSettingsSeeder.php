<?php

namespace Database\Seeders;

use App\Models\TrelloSetting;
use Illuminate\Database\Seeder;

class TrelloSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $templateBoardId = filled(env('TRELLO_TEMPLATE_BOARD_ID'))
            ? (string) env('TRELLO_TEMPLATE_BOARD_ID')
            : null;

        $backgroundId = filled(env('TRELLO_BOARD_BACKGROUND_ID'))
            ? (string) env('TRELLO_BOARD_BACKGROUND_ID')
            : null;

        TrelloSetting::query()->firstOrCreate(
            [],
            [
                'template_board_id' => $templateBoardId,
                'background_id' => $backgroundId,
            ],
        );
    }
}
