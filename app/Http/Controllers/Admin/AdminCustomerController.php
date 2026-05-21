<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\TrelloTask;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::query()
            ->with('plan')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('plan_id'), fn ($query) => $query->where('plan_id', $request->integer('plan_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($nested) => $nested->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'plans' => Plan::query()->orderBy('sort_order')->get(),
            'filters' => $request->only(['status', 'plan_id', 'search']),
        ]);
    }

    public function show(Customer $customer): Response
    {
        $customer->load('plan');

        $tasks = $customer->trelloTasks()
            ->with('latestVersion')
            ->latest()
            ->take(10)
            ->get()
            ->map(function (TrelloTask $task): array {
                $latestVersion = $task->latestVersion;

                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'workflow_status' => $task->workflow_status->value,
                    'workflow_label' => $task->workflow_status->label(),
                    'pipeline_status' => $latestVersion?->pipeline_status->value,
                    'document_path' => $latestVersion?->document_path,
                    'document_filename' => $latestVersion?->document_filename,
                    'has_document' => filled($latestVersion?->document_path),
                ];
            })
            ->values()
            ->all();

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
}
