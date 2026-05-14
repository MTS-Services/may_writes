<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrelloTask;
use App\Services\DocumentService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFileController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/files/index', [
            'tasks' => TrelloTask::query()
                ->whereNotNull('document_path')
                ->with('customer')
                ->latest('processed_at')
                ->paginate(20),
        ]);
    }

    public function download(TrelloTask $task): StreamedResponse
    {
        $disk = DocumentService::documentsDisk();

        if (! $task->document_path || ! $disk->exists($task->document_path)) {
            abort(404, 'File not found');
        }

        return $disk->download($task->document_path, $task->document_filename ?? basename($task->document_path));
    }
}
