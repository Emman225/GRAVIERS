<?php

namespace App\Mail;

use App\Models\DemandeCompteClientATerme;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DemandeClientATermeRefusee extends Mailable
{
    use Queueable, SerializesModels;

    public DemandeCompteClientATerme $demande;

    public function __construct(DemandeCompteClientATerme $demande)
    {
        $this->demande = $demande;
    }

    public function build()
    {
        return $this->subject('Votre demande de compte à terme a été refusée')
            ->view('emails.demande-client-terme-refusee');
    }
}
