<?php

namespace App\Mail;

use App\Models\HouseholdInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use SensitiveParameter;

/**
 * Invitation e-mail for a new household member: contains the one-time
 * registration link with the plain token (only the hash is stored).
 */
class HouseholdInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create the mail for one invitation.
     *
     * @param  HouseholdInvitation  $invitation  The stored invitation.
     * @param  string  $token  Plain registration token for the link.
     */
    public function __construct(
        public HouseholdInvitation $invitation,
        #[SensitiveParameter] public string $token,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.invitation_subject', ['app' => config('app.name')]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'emails.invitation',
            with: [
                'body' => __('mail.invitation_body', [
                    'app' => config('app.name'),
                    'url' => route('filament.admin.invitation', ['token' => $this->token]),
                    'hours' => config('app.invitation_ttl_hours'),
                ]),
            ],
        );
    }
}
