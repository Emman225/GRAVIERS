<?php

namespace App\Exports;

// use Maatwebsite\Excel\Concerns\FromCollection;
// use App\Models\Commande;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
class exportCommandeClient implements FromView
{
    /**
    * @return \Illuminate\Support\Collection
    */


    public function __construct(public $commandes)
    {

    }
    // public function collection()
    // {

    //     return $this->commandes;
    // }

    public function view(): View
    {
        return view('client.etatCommande', [
            'commandes' => $this->commandes
        ]);
    }


}
