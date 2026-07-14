<?php

namespace App\Livewire;

use Livewire\Component;

use App\Models\Commande;

class ListeFacture extends Component
{
    public $factures;
    public $commande;
    public $enlevements;
    public $dd;
    public $numero;
    public $kehechose;



    public function mount($numero){

        $this->kehechose = $numero;
    }


    public function voirFacture($numero){

        $this->commande = Commande::where('numero',$numero)->first();
        $factures = Facture::where('commande_id',$commande->id)->get();

        $this->factures =  $factures;

    }




    public function render()
    {
        // $this->commande = $commande;
        return view('livewire.liste-facture');
    }
}
