<div>
    <div class="row">
        <div class="col-2">
            <button class=" mt-3 btn btn-info float-start ms-3" wire:click="voirFacture({{$kehechose}})">Voir les factures</button>
        </div>
    </div>
    <div class="col-lg-10 mt-70">
        <div class="table-responsive">
            <h3>Les factures </h3>
            <table  class="table table-striped">
                <thead class="thead-dark">

                    <tr>
                        <th style="background-color: #1c57a3; color: white;" width="">Numero facture</th>
                        <th style="background-color: #1c57a3; color: white;" width="">Date facture</th>
                        <th style="background-color: #1c57a3; color: white;" width="">Montant</th>
                        <th style="background-color: #1c57a3; color: white;" width="">Voir</th>
                        <th style="background-color: #1c57a3; color: white;" width="">Télécharger</th>

                    </tr>
                </thead>
                <tbody>
                    @foreach ($factures as $facture )
                        <tr>
                            <td>  {{$facture->numero}} </td>
                            <td>date</td>
                            <td>Montant</td>
                            <td>Voir</td>
                            <td>Telecharger</td>
                        </tr>
                    @endforeach

                </tbody>
            </table>
        </div>
    </div>
</div>
