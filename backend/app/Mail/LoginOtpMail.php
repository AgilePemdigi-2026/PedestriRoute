<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $otp) {}

    public function build(): self
    {
        return $this->subject('Kode Verifikasi Login PedestriRoute')
            ->view('emails.login-otp');
    }
}
