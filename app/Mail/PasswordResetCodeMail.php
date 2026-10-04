<?php

namespace App\Mail;

use App\Models\ShopSetting;
use App\Services\PasswordResetService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public string $code, public ShopSetting $shop) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your password reset code - {$this->shop->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.password-reset-code',
            text: 'mail.password-reset-code-text',
            with: ['validMinutes' => PasswordResetService::CODE_TTL_MINUTES],
        );
    }
}
