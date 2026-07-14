<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande refusée</title>
</head>
<body style="font-family: Arial, sans-serif; color:#333; max-width:600px; margin:0 auto;">
    <div style="background:#dc2626; color:#fff; padding:20px; text-align:center;">
        <img src="https://graviers.fneconnect.net/backend/assets/imgs/theme/logoAvecFond.jpg" alt="Mon Gravier"
            style="max-width:180px; width:100%; height:auto; margin-bottom:10px;" />
        <h2 style="margin:0;">Demande refusée</h2>
    </div>
    <div style="padding:20px;">
        <p>Bonjour {{ $demande->client->nom ?? '' }} {{ $demande->client->prenom ?? '' }},</p>

        <p>Nous avons examiné avec attention votre demande de compte client à terme
            (<strong>{{ $demande->objet }}</strong>) et sommes au regret de vous informer
            qu'elle n'a pas pu être approuvée à ce stade.</p>

        @if(!empty($demande->motif_refus))
            <p><strong>Motif du refus :</strong></p>
            <p style="background:#fee2e2; padding:10px; border-left:3px solid #dc2626;">{{ $demande->motif_refus }}</p>
        @endif

        <p>Vous pouvez nous soumettre une nouvelle demande après avoir pris en compte les éléments mentionnés ci-dessus.
            Vous restez par ailleurs client ordinaire et pouvez continuer à passer commande comme à l'accoutumée.</p>

        <p style="margin-top:30px;">Cordialement,<br>L'équipe Mon Gravier</p>
    </div>
    <div style="background:#f8f9fa; padding:15px; text-align:center; font-size:12px; color:#666;">
        Cet email a été envoyé automatiquement, merci de ne pas y répondre.
    </div>
</body>
</html>
