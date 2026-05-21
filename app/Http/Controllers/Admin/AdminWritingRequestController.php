<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WritingWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWritingRequestWorkflowStatusRequest;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use App\Services\DocumentService;
use App\Services\WorkflowStatusSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminWritingRequestController extends Controller
{
    public function index(): Response
    {
        $tasks = TrelloTask::query()
            ->with(['customer', 'versions' => fn ($q) => $q->reorder('version_number', 'desc')])
            ->latest('updated_at')
            ->get()
            ->map(function (TrelloTask $task): array {
                $latestVersionId = $task->latest_version_id;

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'workflow_status' => $task->workflow_status->value,
                    'workflow_label' => $task->workflow_status->label(),
                    'trello_card_id' => $task->trello_card_id,
                    'customer' => [
                        'id' => $task->customer->id,
                        'name' => $task->customer->name,
                    ],
                    'versions' => $task->versions->map(fn (TrelloTaskVersion $version): array => [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'trigger' => $version->trigger->value,
                        'pipeline_status' => $version->pipeline_status->value,
                        'was_truncated' => $version->was_truncated,
                        'document_filename' => $version->document_filename,
                        'processed_at' => $version->processed_at?->toIso8601String(),
                        'is_latest' => $version->id === $latestVersionId,
                        'has_document' => filled($version->document_path),
                    ])->values()->all(),
                ];
            });

        return Inertia::render('admin/writing-requests/index', [
            'tasks' => $tasks,
            'workflowStatuses' => collect(WritingWorkflowStatus::cases())->map(fn (WritingWorkflowStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->all(),
        ]);
    }

    public function updateWorkflowStatus(
        UpdateWritingRequestWorkflowStatusRequest $request,
        TrelloTask $task,
        WorkflowStatusSyncService $workflowSync,
    ): RedirectResponse {
        $status = WritingWorkflowStatus::from($request->validated('workflow_status'));

        $workflowSync->syncFromAdmin($task->load('customer'), $status);

        return back();
    }

    public function downloadVersion(TrelloTaskVersion $version): StreamedResponse
    {
        $version->load('task.customer');
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
