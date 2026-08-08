<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActionRequiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $body;
    public $actionUrl;

    public function __construct(string $title, string $body, ?string $actionUrl = null)
    {
        $this->title = $title;
        $this->body = $body;
        $this->actionUrl = $actionUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.action_required',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
