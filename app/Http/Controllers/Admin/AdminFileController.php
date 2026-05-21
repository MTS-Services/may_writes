<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrelloTask;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFileController extends Controller
{
    public function download(TrelloTask $task): StreamedResponse|RedirectResponse
    {
        $version = $task->latestVersion;

        if ($version === null) {
            abort(404, 'No document version found for this task.');
        }

        $disk = DocumentService::documentsDisk();

        if (! $version->document_path || ! $disk->exists($version->document_path)) {
            abort(404, 'File not found');
        }

        return $disk->download(
            $version->document_path,
            $version->document_filename ?? basename($version->document_path),
        );
    }
}
