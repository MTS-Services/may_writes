<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTrelloSettingsRequest;
use App\Services\TrelloService;
use App\Services\TrelloSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminTrelloSettingsController extends Controller
{
    public function edit(TrelloSettings $trelloSettings): Response
    {
        return Inertia::render('admin/trello-settings', [
            'settings' => $trelloSettings->toAdminArray(),
            'resolved' => [
                'template_board_id' => $trelloSettings->templateBoardId(),
                'background_id' => $trelloSettings->backgroundId(),
            ],
        ]);
    }

    public function update(
        UpdateTrelloSettingsRequest $request,
        TrelloSettings $trelloSettings,
        TrelloService $trelloService,
    ): RedirectResponse {
        $data = $request->normalized();

        if (filled($data['template_board_id']) && ! $trelloService->templateBoardExists((string) $data['template_board_id'])) {
            return back()
                ->withErrors(['template_board_id' => 'Trello board not found or not accessible with the configured API credentials.'])
                ->withInput();
        }

        $trelloSettings->update($data);

        return back()->with('success', 'Trello integration settings saved.');
    }
}
