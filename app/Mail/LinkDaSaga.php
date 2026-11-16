<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LinkDaSaga extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $osId,
        public string $assunto,
        public string $chamada,
        public string $acao,
        public string $link,
        public string $valor,
        public ?string $clienteNome = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.link-da-saga');
    }
}
