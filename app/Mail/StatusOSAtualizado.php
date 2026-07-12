<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StatusOSAtualizado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $osId,
        public string $statusNome,
        public ?string $clienteNome = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Atualização da OS #{$this->osId}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.status-os-atualizado',
            with: [
                'osId' => $this->osId,
                'statusNome' => $this->statusNome,
                'clienteNome' => $this->clienteNome,
            ],
        );
    }
}
