<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
    }

    public function envelope(): Envelope
    {
        $business = $this->booking->tenant?->name ?? config('app.name');

        return new Envelope(
            subject: 'Invoice '.$this->booking->invoiceNumber().' — '.$business,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-invoice',
            with: [
                'booking' => $this->booking,
                'business' => $this->booking->tenant?->name ?? config('app.name'),
            ],
        );
    }
}
