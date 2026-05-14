<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Storage;

test('admin can download task document from default filesystem disk', function () {
    $diskName = (string) config('filesystems.default');
    Storage::fake($diskName);

    $user = User::factory()->create();
    $customer = Customer::create([
        'name' => 'Test Client',
        'email' => 'client@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $relative = 'clients/test-client/brief.docx';
    DocumentService::documentsDisk()->put($relative, 'docx-bytes');

    $task = TrelloTask::create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card-'.uniqid(),
        'trello_board_id' => 'board-1',
        'title' => 'Test task',
        'description' => null,
        'raw_payload' => [],
        'document_path' => $relative,
        'document_filename' => 'brief.docx',
    ]);

    $response = $this->actingAs($user)->get(route('admin.files.download', $task));

    $response->assertOk();
    $response->assertHeader('content-disposition');
});

test('guests cannot download admin task documents', function () {
    Storage::fake((string) config('filesystems.default'));

    $customer = Customer::create([
        'name' => 'Test Client',
        'email' => 'client2@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $task = TrelloTask::create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card-'.uniqid(),
        'trello_board_id' => 'board-1',
        'title' => 'Test task',
        'description' => null,
        'raw_payload' => [],
        'document_path' => 'clients/x/y.docx',
        'document_filename' => 'y.docx',
    ]);

    $this->get(route('admin.files.download', $task))->assertRedirect(route('login'));
});

test('admin receives 404 when document file is missing on disk', function () {
    Storage::fake((string) config('filesystems.default'));

    $user = User::factory()->create();
    $customer = Customer::create([
        'name' => 'Test Client',
        'email' => 'client3@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $task = TrelloTask::create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card-'.uniqid(),
        'trello_board_id' => 'board-1',
        'title' => 'Test task',
        'description' => null,
        'raw_payload' => [],
        'document_path' => 'clients/missing/file.docx',
        'document_filename' => 'file.docx',
    ]);

    $this->actingAs($user)->get(route('admin.files.download', $task))->assertNotFound();
});
