<?php

namespace App\Mail;

use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Aviso interno al restaurante cuando entra una reserva desde la web
class ReservaRecibida extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reserva $reserva)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva reserva online: '.$this->reserva->nombre.' '.$this->reserva->apellidos
                .' — '.$this->reserva->fecha_hora->format('d/m').' a las '.$this->reserva->fecha_hora->format('H:i')
                .' ('.$this->reserva->personas.' pers.)',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.reserva-recibida');
    }
}
