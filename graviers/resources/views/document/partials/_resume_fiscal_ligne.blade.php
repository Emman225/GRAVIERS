{{-- LA LIGNE DU RÉSUMÉ FISCAL (09/09/2026) : une seule catégorie de TVA.

     L'assiette est le HT des articles, plus le transport lorsqu'il est taxé ;
     les taxes sont la TVA des articles plus celle du transport. Un transport
     non taxé reste hors de l'assiette (il figure dans le TOTAL HT du tableau,
     pas dans le sous-total taxable).

     Attendu : $baseArticles, $tvaArticles, $coutLivraison, $tvaTransport, $config ;
     $tauxTva (taux de l'affaire, 15/09/2026) est facultatif : sans lui, le
     taux du paramétrage. --}}
@php
    $assietteTva = (float) ($baseArticles ?? 0) + ((($tvaTransport ?? 0) > 0) ? (float) ($coutLivraison ?? 0) : 0);
    $taxesTotales = (float) ($tvaArticles ?? 0) + (float) ($tvaTransport ?? 0);
    $tauxResume = $tauxTva ?? ($config->tva ?? 0);
@endphp
@if($tauxResume > 0)
    <tr>
        <td>TVA {{ $tauxResume }}% sur HT</td>
        <td class="text-right">{{ number_format($assietteTva, 0, '', ' ') }}</td>
        <td class="text-center">{{ $tauxResume }}%</td>
        <td class="text-right">{{ number_format($taxesTotales, 0, '', ' ') }}</td>
    </tr>
@else
    @php
        // La ligne dit la NATURE de l'exonération (lot 82, 15/09/2026) : légale
        // (D) ou conventionnelle (C), selon le code choisi pour le client.
        $codeExo = strtoupper((string) ($codeExoneration ?? 'TVAD'));
    @endphp
    <tr>
        <td>{{ $codeExo === 'TVAC' ? 'TVA exo.conv. - Pas de TVA sur HT 00,00% - C' : 'TVA exo.lég - Pas de TVA sur HT 00,00% - D' }}</td>
        <td class="text-right">{{ number_format((float) ($baseArticles ?? 0) + (float) ($coutLivraison ?? 0), 0, '', ' ') }}</td>
        <td class="text-center">0%</td>
        <td class="text-right">0</td>
    </tr>
@endif
