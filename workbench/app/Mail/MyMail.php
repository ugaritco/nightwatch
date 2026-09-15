<?php

namespace App\Mail;

use Heritage\Bus\Queueable;
use Heritage\Mail\Mailable;
use Heritage\Mail\Mailables\Content;
use Heritage\Mail\Mailables\Envelope;
use Heritage\Queue\SerializesModels;

class MyMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public $subject = 'Welcome')
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Heritage\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            //
        ];
    }
}
