<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Note;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NoteCreatedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** Ile znakow tresci notatki pokazujemy w mailu. */
    private const int EXCERPT_LENGTH = 200;

    public function __construct(
        public readonly Note $note,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nowa notatka: {$this->note->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.note-created',
            with: [
                'recipientName' => $this->note->loadMissing('user')->user?->name ?? 'Użytkowniku',
                'title' => $this->note->title,
                'excerpt' => Str::limit($this->note->content, self::EXCERPT_LENGTH),
                'createdAt' => $this->note->created_at?->format('d.m.Y H:i'),
            ],
        );
    }
}
