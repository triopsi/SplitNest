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
            subject: __('mail.invitation.subject', ['app' => config('app.name')]),
        );
    }

    /**
     * Get the message content definition: HTML card plus plain-text fallback.
     */
    public function content(): Content
    {
        $url = route('filament.admin.invitation', ['token' => $this->token]);

        return new Content(
            view: 'emails.invitation',
            text: 'emails.invitation-text',
            with: [
                'url' => $url,
                'hours' => config('app.invitation_ttl_hours'),
                'bodyText' => __('mail.invitation.body', ['app' => config('app.name'), 'url' => $url, 'hours' => config('app.invitation_ttl_hours')]),
            ],
        );
    }
}
