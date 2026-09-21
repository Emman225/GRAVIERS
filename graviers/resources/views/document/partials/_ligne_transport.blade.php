{{-- LA LIGNE DU TRANSPORT DANS LE TABLEAU DES ARTICLES (09/09/2026).

     Le coût de livraison figurait SOUS les totaux (« Transport (HT) »), et sa
     TVA sur une ligne à part : le client ne le lisait pas comme une prestation
     facturée, et l'addition des lignes ne retombait pas sur le TOTAL HT. Il est
     désormais une ligne du tableau — comme dans l'application — et porte la
     mention de TVA quand le paramétrage (Paramètres → Livraison & TVA →
     « Appliquer la TVA au transport ») l'avait taxé au moment de l'affaire.

     DÉCISION DU CLIENT (09/09/2026) : cette ligne n'existe QUE lorsque le
     transport est taxé. Case décochée, le document garde sa présentation
     d'avant — le coût de livraison sous les totaux, hors TVA.

     Attendu dans la portée : $coutLivraison, $tvaTransport, $config ;
     $adresse est facultative. --}}
@if (($coutLivraison ?? 0) > 0 && ($tvaTransport ?? 0) > 0)
    <tr>
        <td class="col-ref"></td>
        <td class="col-designation">Coût de livraison{{ !empty($adresse) ? ' (' . $adresse . ')' : '' }}</td>
        <td class="col-pu">{{ number_format($coutLivraison, 0, '', ' ') }}</td>
        <td class="col-qte">1</td>
        <td class="col-unite">Forfait</td>
        <td class="col-taxes">{{ ($tvaTransport ?? 0) > 0 ? 'TVA (' . ($config->tva ?? 0) . '%)' : '0' }}</td>
        <td class="col-rem">0</td>
        <td class="col-montant">{{ number_format($coutLivraison, 0, '', ' ') }}</td>
    </tr>
@endif
