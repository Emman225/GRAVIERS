{{-- RETOUR AU SITE PUBLIC.

     Les pages de connexion des espaces — administration, apporteur,
     fournisseur, livreur — s'affichent seules, sans en-tête ni menu.
     Quelqu'un qui y arrivait par erreur, ou qui renonçait à se connecter,
     n'avait plus aucun chemin vers la boutique : il fallait retaper
     l'adresse à la main.

     Un seul partiel pour les quatre pages, afin que le libellé et la
     position ne se mettent pas à diverger d'un espace à l'autre. --}}
<a href="{{ route('client.index') }}" class="auth-retour-site" title="Aller sur le site GRAVIER.COM">
    <i class="material-icons md-home"></i>
    <span class="auth-retour-site__texte">Retour au site</span>
</a>
