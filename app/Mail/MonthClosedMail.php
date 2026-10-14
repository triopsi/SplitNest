<?php

namespace App\Mail;

use App\Models\MonthlyClosing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Closing summary e-mail for one participant: body text with the configured
 * placeholders replaced and the frozen PDF statement as attachment.
 */
class MonthClosedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create the mail for one participant.
     *
     * @param  MonthlyClosing  $closing  The closed month.
     * @param  string  $bodyText  Rendered body text with placeholders replaced.
     * @param  string  $pdfData  Raw PDF bytes of the statement.
     */
    public function __construct(
        public MonthlyClosing $closing,
        public string $bodyText,
        public string $pdfData,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SplitNest Abrechnung '.$this->closing->month,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'emails.month-closed',
        );
    }

    /**
     * Get the attachments for the message: the frozen PDF statement.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfData, 'SplitNest-Abrechnung-'.$this->closing->month.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
