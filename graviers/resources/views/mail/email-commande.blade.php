<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Mon Gravier — Information de commande</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="background:#f5f5f5;padding:20px 0;">
    <tr>
      <td align="center">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #ddd;">
          <tr>
            <td align="center" style="padding:20px;">
              <div style="width:80px;height:80px;border-radius:50%;background:#e9e9e9;line-height:80px;text-align:center;font-weight:bold;color:#0b71c8;margin:auto;">
                <img src="https://graviers.fneconnect.net/backend/assets/imgs/theme/logoAvecFond.jpg" alt="Logo">
              </div>
              <h2 style="margin:10px 0 0;font-size:16px;font-weight:bold;">Mon Gravier -- Information de commande</h2>
            </td>
          </tr>
          <tr>
            <td style="padding:15px 20px;border-top:1px solid #eee;border-bottom:1px solid #eee;font-size:13px;line-height:1.4;">
              <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td width="60%" style="vertical-align:top;">
                    <strong>Client: {{$commande->client->nom.' '.$commande->client->prenom}}</strong><br>
                    Tél: <a href="tel:+2250757638479" style="color:#0b71c8;text-decoration:none;">{{$commande->client->contact1}}</a><br>
                    Email: {{$commande->client->user->email}}
                  </td>
                  <td width="40%" align="right" style="vertical-align:top;">
                    Numéro: <a href="#" style="color:#0b71c8;text-decoration:none;">{{$commande->numero}}</a><br>
                    Date Commande: 2025-09-06 00:14:37
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:15px 20px;font-size:13px;line-height:1.4;">
              <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td width="50%" valign="top">
                    <strong>Adresse de livraison</strong><br>
                    Angré, Cocody, Abidjan, Côte d'Ivoire
                  </td>
                  <td width="50%" valign="top">
                    <strong>Mode de livraison</strong><br>
                    En vrac
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          @php $i =0 @endphp
          @foreach ($commande->detailCommande as $commande)
          <tr>
            <td style="padding:15px 20px;background:#f0f0f0;font-size:13px;line-height:1.4;">
              <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td width="25%" valign="top">
                    <img src="{{asset('storage/'. $commande->produit->image)}}" alt="Produit" style="width:100%;max-width:120px;border-radius:6px;">
                  </td>
                  <td width="50%" valign="top" style="padding-left:10px;">
                    <strong>{{$commande->produit->nom}}</strong><br>
                    {{$commande->qte}}<br>
                    {{Help::formatNombre($commande->produit->prix_moyen, true)}}
                  </td>
                  <td width="25%" align="right" valign="top">
                    <span style="color:#777;font-size:12px;">Sous Total</span><br>
                    <strong style="font-size:14px;">{{Help::formatNombre($commande->produit->prix_moyen * $commande->qte, true)}}</strong>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          @endforeach
          <tr>
            <td style="padding:15px 20px;font-size:13px;line-height:1.6;">
              <table width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td>Montant HT</td>
                  <td align="right">{{Help::formatNombre($ht, true)}}</td>
                </tr>
                <tr>
                  <td>Cout de livraison</td>
                  <td align="right" style="color:#0b71c8;font-weight:bold;">{{Help::formatNombre($fraisLivraison, true)}}</td>
                </tr>
                <tr>
                  <td>Montant TVA</td>
                  <td align="right">{{Help::formatNombre($tva, true)}}</td>
                </tr>
                <tr>
                  <td>Remise</td>
                  <td align="right">- {{Help::formatNombre($remise, true)}}</td>
                </tr>
                <tr style="border-top:1px solid #ddd;">
                  <td><strong>Montant TTC</strong></td>
                  <td align="right"><strong>{{Help::formatNombre($total, true)}}</strong></td>
                </tr>
                <tr>
                  <td>Mode de paiement</td>
                  <td align="right">{{$modepaiement}}</td>
                </tr>
              </table>
              <x-mail::button :url="route('client.index')">
                Revenir aux achat
            </x-mail::button>
              <p style="margin-top:10px;font-size:11px;color:#777;">Note: Généré automatiquement. Veuillez nous contacter pour toute préoccupation.</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="background:#fafafa;padding:10px;font-size:11px;color:#777;">
              &copy; Mon Gravier — Tous droits réservés
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
