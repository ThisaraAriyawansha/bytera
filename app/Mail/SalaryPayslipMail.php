<?php

namespace App\Mail;

use App\Models\SalaryPayment;
use App\Models\ShopSetting;
use App\Services\SalaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalaryPayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SalaryPayment $payment,
        public ShopSetting $shop,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Salary Payment {$this->payment->payment_no} - {$this->shop->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.salary-payslip',
            text: 'mail.salary-payslip-text',
            with: [
                'calculation' => SalaryService::calculationLines($this->payment),
                'items' => $this->payment->commission_items ?? [],
            ],
        );
    }
}
