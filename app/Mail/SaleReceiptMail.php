<?php

namespace App\Mail;

use App\Models\Sale;
use App\Models\ShopSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SaleReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance. `$pdf` is the A4 bill rendered in the browser (raw PDF bytes).
     */
    public function __construct(
        public Sale $sale,
        public ShopSetting $shop,
        public string $pdf,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your receipt {$this->sale->invoice_no} - {$this->shop->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.sale-receipt',
            text: 'mail.sale-receipt-text',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, "{$this->sale->invoice_no}.pdf")->withMime('application/pdf'),
        ];
    }
}
