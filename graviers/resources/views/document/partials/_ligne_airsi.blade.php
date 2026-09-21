{{-- LA CASE « AUTRES TAXES » DES DOCUMENTS (10/09/2026) : elle porte l'AIRSI —
     acompte de 5 % sur le HT + TVA, dû par le client sans régime réel
     d'imposition — et reste à 0 pour les autres. Attendu : $airsi. --}}
@php $airsiLigne = (float) ($airsi ?? 0); @endphp
<tr>
    <td class="label">AUTRES TAXES @if ($airsiLigne >= 1) (AIRSI {{ rtrim(rtrim(number_format(\Help::tauxAirsi(), 2, ',', ' '), '0'), ',') }} %)@endif</td>
    <td class="valeur js-airsi">{{ number_format($airsiLigne, 0, '', ' ') }}</td>
</tr>
