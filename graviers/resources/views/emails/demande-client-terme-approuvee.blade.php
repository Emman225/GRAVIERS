<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande approuvée</title>
</head>
<body style="font-family: Arial, sans-serif; color:#333; max-width:600px; margin:0 auto;">
    <div style="background:#1c57a3; color:#fff; padding:20px; text-align:center;">
        <img src="https://graviers.fneconnect.net/backend/assets/imgs/theme/logoAvecFond.jpg" alt="Mon Gravier"
            style="max-width:180px; width:100%; height:auto; margin-bottom:10px;" />
        <h2 style="margin:0;">Demande approuvée</h2>
    </div>
    <div style="padding:20px;">
        <p>Bonjour {{ $demande->client->nom ?? '' }} {{ $demande->client->prenom ?? '' }},</p>

        <p>Nous avons le plaisir de vous informer que votre demande de compte client à terme
            (<strong>{{ $demande->objet }}</strong>) a été <strong style="color:#16a34a;">approuvée</strong>.</p>

        <table style="width:100%; border-collapse:collapse; margin:20px 0;">
            <tr>
                <td style="padding:10px; border:1px solid #ddd; background:#f8f9fa;"><strong>Plafond de crédit accordé</strong></td>
                <td style="padding:10px; border:1px solid #ddd;">
                    @if(!empty($demande->plafond_credit))
                        {{ number_format($demande->plafond_credit, 0, ',', ' ') }} FCFA
                    @else
                        Non précisé
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #ddd; background:#f8f9fa;"><strong>Délai de paiement</strong></td>
                <td style="padding:10px; border:1px solid #ddd;">
                    @if(!empty($demande->delai_paiement))
                        {{ $demande->delai_paiement }} jours
                    @else
                        Non précisé
                    @endif
                </td>
            </tr>
        </table>

        @if(!empty($demande->commentaire_admin))
            <p><strong>Commentaire de l'administration :</strong></p>
            <p style="background:#fff3cd; padding:10px; border-left:3px solid #ffc107;">{{ $demande->commentaire_admin }}</p>
        @endif

        <p>Vous pouvez désormais passer des commandes en bénéficiant des conditions de paiement à terme dans les limites convenues.</p>

        <p style="margin-top:30px;">Cordialement,<br>L'équipe Mon Gravier</p>
    </div>
    <div style="background:#f8f9fa; padding:15px; text-align:center; font-size:12px; color:#666;">
        Cet email a été envoyé automatiquement, merci de ne pas y répondre.
    </div>
</body>
</html>
