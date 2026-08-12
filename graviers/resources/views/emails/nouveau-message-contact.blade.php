<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouveau message de contact</title>
</head>
<body style="font-family: Arial, sans-serif; color:#333; max-width:600px; margin:0 auto;">
    <div style="background:#1c57a3; color:#fff; padding:20px; text-align:center;">
        <img src="https://graviers.fneconnect.net/backend/assets/imgs/theme/logoAvecFond.jpg" alt="Mon Gravier"
            style="max-width:180px; width:100%; height:auto; margin-bottom:10px;" />
        <h2 style="margin:0;">Nouveau message de contact</h2>
    </div>
    <div style="padding:20px;">
        <p>Un visiteur vient d'écrire depuis la page « Nous contacter » du site.</p>

        <table style="width:100%; border-collapse:collapse; margin:20px 0;">
            <tr>
                <td style="padding:10px; border:1px solid #ddd; background:#f8f9fa; width:38%;"><strong>Nom et prénoms</strong></td>
                <td style="padding:10px; border:1px solid #ddd;">{{ $contact->nom_prenoms }}</td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #ddd; background:#f8f9fa;"><strong>Adresse e-mail</strong></td>
                <td style="padding:10px; border:1px solid #ddd;">
                    <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                </td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #ddd; background:#f8f9fa;"><strong>Téléphone</strong></td>
                <td style="padding:10px; border:1px solid #ddd;">{{ $contact->telephone }}</td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #ddd; background:#f8f9fa;"><strong>Sujet</strong></td>
                <td style="padding:10px; border:1px solid #ddd;">{{ $contact->sujet }}</td>
            </tr>
            <tr>
                <td style="padding:10px; border:1px solid #ddd; background:#f8f9fa;"><strong>Reçu le</strong></td>
                <td style="padding:10px; border:1px solid #ddd;">
                    {{ $contact->created_at ? $contact->created_at->format('d/m/Y à H:i') : '' }}
                </td>
            </tr>
        </table>

        <p style="margin:0 0 6px;"><strong>Message :</strong></p>
        <div style="border-left:4px solid #1c57a3; background:#f8f9fa; padding:14px 16px; white-space:pre-wrap;">{{ $contact->message }}</div>

        <p style="font-size:13px; color:#666; margin-top:20px;">
            Vous pouvez répondre directement à ce courriel : la réponse partira vers l'adresse du visiteur.
            Le message est également consultable dans le back-office, rubrique
            <strong>Divers &rsaquo; Messages de contact</strong>.
        </p>
    </div>
    <div style="background:#f8f9fa; padding:15px; text-align:center; font-size:12px; color:#666;">
        <p style="margin:0;">DALAKOUN SARL — Abidjan, Côte d'Ivoire</p>
    </div>
</body>
</html>
