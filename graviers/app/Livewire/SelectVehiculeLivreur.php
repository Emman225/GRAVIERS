<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Livreur;
use App\Models\Vehicule;

class SelectVehiculeLivreur extends Component
{
    public $livreurs=[];
    public $vehicules=[];
    public $SelectedLivreur;
    public $SelectedVehicule;



    public function mount(){
        $this->livreurs = Livreur::all();
        $this->vehicules = [];
    }

    public function updatedSelectedLivreur(){
        if($this->SelectedLivreur){
            $this->vehicules = Vehicule::where('livreur_id', $this->SelectedLivreur)->get();
        }
        else{
            $this->vehicules = [];
        }
    
    }


    public function render()
    {
        return view('livewire.select-vehicule-livreur',['debug' => [
            'livreurs' => $this->livreurs,
            'vehicules' => $this->vehicules,
            'selectedLivreur' => $this->SelectedLivreur,
            'selectedVehicule' => $this->SelectedVehicule,
        ]]);
    }
}
