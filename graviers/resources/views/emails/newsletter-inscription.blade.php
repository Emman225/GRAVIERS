<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription confirmée</title>
</head>
<body style="font-family: Arial, sans-serif; color:#333; max-width:600px; margin:0 auto;">
    <div style="background:#1c57a3; color:#fff; padding:20px; text-align:center;">
        <img src="{{ asset('frontend/assets/imgs/logo/dalakoun-blanc.png') }}" alt="DALAKOUN"
            style="max-width:180px; width:100%; height:auto; margin-bottom:10px;" />
        <h2 style="margin:0;">Inscription confirmée</h2>
    </div>
    <div style="padding:20px;">
        <p>Bonjour,</p>

        <p>Votre adresse <strong>{{ $adresse }}</strong> est bien inscrite à notre lettre
            d'information. Vous recevrez nos nouveautés, nos promotions et nos conseils
            sur les matériaux de construction.</p>

        <p style="text-align:center; margin:30px 0;">
            <a href="{{ config('app.url') }}"
               style="background:#1c57a3; color:#fff; padding:12px 26px; border-radius:5px;
                      text-decoration:none; display:inline-block;">Découvrir nos produits</a>
        </p>

        <p style="font-size:13px; color:#666;">
            Vous ne souhaitez plus recevoir nos messages ? Répondez simplement à ce courriel
            en indiquant « désinscription », et nous retirerons votre adresse.
        </p>
    </div>
    <div style="background:#f8f9fa; padding:15px; text-align:center; font-size:12px; color:#666;">
        <p style="margin:0;">DALAKOUN SARL — Abidjan, Côte d'Ivoire</p>
        <p style="margin:5px 0 0;">{{ \Help::emailContact() }}</p>
    </div>
</body>
</html>
