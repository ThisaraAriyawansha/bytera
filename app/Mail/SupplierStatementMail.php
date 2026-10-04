<?php

namespace App\Mail;

use App\Models\ShopSetting;
use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Supplier $supplier,
        public ShopSetting $shop,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Account Statement - {$this->shop->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.supplier-statement',
            text: 'mail.supplier-statement-text',
            with: [
                'payments' => $this->supplier->payments()->latest()->latest('id')->limit(10)->get(),
            ],
        );
    }
}
