<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class AdminCustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::query()
            ->with('plan')
            ->when($request->filled('status'), fn($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('plan_id'), fn($query) => $query->where('plan_id', $request->integer('plan_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . $request->string('search')->toString() . '%';
                $query->where(fn($nested) => $nested->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->latest()
            ->paginate(5)
            ->withQueryString();

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'plans' => Plan::query()->orderBy('sort_order')->get(),
            'filters' => $request->only(['status', 'plan_id', 'search']),
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        $customer->load('plan');

        /** @var LengthAwarePaginator<int, array<string, mixed>> $tasks */
        $tasks = $customer->trelloTasks()
            ->withCount('versions')
            ->with([
                'latestVersion',
                'versions' => fn($query) => $query->reorder('version_number', 'desc'),
            ])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn(TrelloTask $task): array => $this->formatTaskForCustomerShow($task));

        return Inertia::render('admin/customers/show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'status' => $customer->status->value,
                'trello_board_url' => $customer->trello_board_url,
                'trello_board_id' => $customer->trello_board_id,
                'plan' => $customer->plan ? ['name' => $customer->plan->name] : null,
            ],
            'tasks' => $tasks,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTaskForCustomerShow(TrelloTask $task): array
    {
        $latestVersion = $task->latestVersion;
        $latestVersionId = $task->latest_version_id;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'created_at' => $task->created_at?->toIso8601String(),
            'workflow_status' => $task->workflow_status->value,
            'workflow_label' => $task->workflow_status->label(),
            'pipeline_status' => $latestVersion?->pipeline_status->value,
            'versions_count' => (int) $task->versions_count,
            'versions' => $task->versions->map(fn(TrelloTaskVersion $version): array => [
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
    }
}
