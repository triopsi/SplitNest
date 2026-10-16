<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use SensitiveParameter;

/**
 * Password reset e-mail using the shared application mail layout.
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create the mail with the panel reset URL and configured token lifetime.
     */
    public function __construct(
        #[SensitiveParameter] public string $url,
        public int $minutes,
    ) {}

    /**
     * Get the translated subject.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.password_reset.subject', ['app' => config('app.name')]),
        );
    }

    /**
     * Use the application HTML card and a plain-text alternative.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            text: 'emails.password-reset-text',
        );
    }
}
