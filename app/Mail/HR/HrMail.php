<?php

namespace App\Mail\HR;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HrMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $emailBody,
        public string $senderName,
        public string $recipientName = '',
        public string $companyName = 'Wilson Labs',
        public string $primaryColor = '#4f6ef7',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
            replyTo: [
                new Address(
                    (string) config('mail.from.address'),
                    $this->senderName
                ),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hr.general',
        );
    }
}
