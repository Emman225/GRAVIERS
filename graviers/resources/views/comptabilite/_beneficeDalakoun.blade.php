{{--
    LE BÉNÉFICE DE DALAKOUN, ANNONCÉ SANS DÉTOUR.

    Les trois écrans comptables portaient le chiffre — en marge, en colonne, en
    total — mais aucun ne le NOMMAIT. On lisait « Marge » sans savoir si elle
    était brute, nette, avant ou après le versement du fournisseur.

    Paramètres attendus :
      $titre    : « Bénéfices de DALAKOUN sur les ventes », etc.
      $produit  : ce qui est encaissé pour DALAKOUN (hors taxes, hors transport)
      $charge   : ce qui est reversé (fournisseur, livreur)
      $libelleProduit / $libelleCharge : comment les nommer
      $benefice : la différence
      $note     : une phrase de précision, facultative
--}}
@php
    $fmtB    = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $tauxB   = $produit > 0 ? round($benefice / $produit * 100, 1) : null;
    $positif = $benefice >= 0;
@endphp

<div class="card mb-4 border-{{ $positif ? 'success' : 'danger' }}">
    <div class="card-body">
        <div class="row align-items-center gx-4">
            <div class="col-12 col-lg-5 border-lg-end">
                <div class="text-muted text-uppercase small mb-1" style="letter-spacing:.05em">{{ $titre }}</div>
                <div class="display-6 fw-bold mb-0 text-{{ $positif ? 'success' : 'danger' }}">
                    {{ $fmtB($benefice) }} <span class="h4">fcfa</span>
                </div>
                @if ($tauxB !== null)
                    <div class="text-muted small">
                        soit <strong>{{ $tauxB }} %</strong> de ce qui a été facturé
                    </div>
                @endif
            </div>

            <div class="col-12 col-lg-7 mt-3 mt-lg-0">
                <div class="row text-center gx-2">
                    <div class="col-5">
                        <div class="text-muted small">{{ $libelleProduit }}</div>
                        <div class="h5 mb-0">{{ $fmtB($produit) }}</div>
                    </div>
                    <div class="col-2 d-flex align-items-center justify-content-center">
                        <span class="h4 text-muted mb-0">−</span>
                    </div>
                    <div class="col-5">
                        <div class="text-muted small">{{ $libelleCharge }}</div>
                        <div class="h5 mb-0 text-danger">{{ $fmtB($charge) }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if (!empty($note))
            <p class="text-muted small mb-0 mt-3">{{ $note }}</p>
        @endif
    </div>
</div>
