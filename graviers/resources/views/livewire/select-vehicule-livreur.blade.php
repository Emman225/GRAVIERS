
<div>
    

    <td class="text-end">
        <select class="form-control" wire:model.live='SelectedLivreur' required name="livreur">
            <option value="">Selection un livreur</option>
            @foreach ($livreurs as $livreur)
                <option value="{{ $livreur->id }}">
                    {{ $livreur->nom.' '.$livreur->prenom . ' | ' . $livreur->user->contact }}
                </option>
            @endforeach

        </select> 
    </td>

    <td class="text-end">
        <select class="form-control" wire:model='SelectedVehicule' wire:key="{{$SelectedLivreur}}" required name="vehicule">

            <option value="">Selection un véhicule</option>
        
                @foreach ($vehicules as $vehicule)
                <option value="{{ $vehicule->id }}">
                        {{ $vehicule->marque.' | '.$vehicule->modele }}
                    </option>
                @endforeach
        

        </select>
    </td>

</div>
