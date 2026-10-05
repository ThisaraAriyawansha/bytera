<?php

namespace App\Mail;

use App\Models\Job;
use App\Models\JobStatusHistory;
use App\Models\ShopSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance for the job's latest status update.
     */
    public function __construct(
        public Job $job,
        public JobStatusHistory $update,
        public ShopSetting $shop,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Update on your job {$this->job->job_no} - {$this->shop->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.job-update',
            text: 'mail.job-update-text',
        );
    }
}
