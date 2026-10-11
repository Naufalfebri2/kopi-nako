<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificationMail extends Mailable
{
    public function __construct(public string $body)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Notifikasi Kopi Nako — Manajemen Shift');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification');
    }
}
