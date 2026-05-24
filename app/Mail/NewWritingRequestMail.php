<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewWritingRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public TrelloTask $task,
        public TrelloTaskVersion $version,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: 'hello@maywrites.co',
            subject: "New writing request: {$this->task->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new-writing-request',
            with: [
                'customer' => $this->customer,
                'task' => $this->task,
                'version' => $this->version,
                'adminUrl' => route('admin.writing-requests'),
            ],
        );
    }
}
