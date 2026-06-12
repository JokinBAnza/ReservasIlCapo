<?php

namespace App\Mail;

use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ReservaConfirmada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reserva $reserva)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reserva confirmada en Il Capo — '.$this->reserva->fecha_hora->format('d/m/Y').' a las '.$this->reserva->fecha_hora->format('H:i'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reserva-confirmada',
            with: [
                // Enlace firmado: solo quien tiene el email puede anular
                'urlAnulacion' => URL::signedRoute('reservas.anular', ['reserva' => $this->reserva->id]),
            ],
        );
    }
}
