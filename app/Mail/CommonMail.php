<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommonMail extends Mailable
{
    use Queueable, SerializesModels;

    public $mailObj;

    public function __construct($mailObj)
    {
        $this->mailObj = $mailObj;
    }

    public function build()
    {
        $data = $this->mailObj;
        return $this->markdown('emails.commonmail')
            ->subject($data['mail_subject'])
            ->with($this->mailObj);
    }
}
