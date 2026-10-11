<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ScheduleMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $periodLabel,
        public string $pdf,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Jadwal Shift Anda — '.$this->periodLabel);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.schedule');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, 'jadwal-shift.pdf')->withMime('application/pdf'),
        ];
    }
}
