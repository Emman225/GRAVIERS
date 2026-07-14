<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Votre commande</title>

    <!-- Start Common CSS -->
    <style type="text/css">
        #outlook a {
            padding: 0;
        }

        body {
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            margin: 0;
            padding: 0;
            font-family: Helvetica, arial, sans-serif;
        }

        .ExternalClass {
            width: 100%;
        }

        .ExternalClass,
        .ExternalClass p,
        .ExternalClass span,
        .ExternalClass font,
        .ExternalClass td,
        .ExternalClass div {
            line-height: 100%;
        }

        .backgroundTable {
            margin: 0;
            padding: 0;
            width: 100% !important;
            line-height: 100% !important;
        }

        .main-temp table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
            font-family: Helvetica, arial, sans-serif;
        }

        .main-temp table td {
            border-collapse: collapse;
        }
    </style>
    <!-- End Common CSS -->
</head>

<body>
    <table width="100%" cellpadding="0" cellspacing="0" border="0" class="backgroundTable main-temp"
        style="background-color: #d5d5d5;">
        <tbody>
            <tr>
                <td>
                    <table width="600" align="center" cellpadding="15" cellspacing="0" border="0"
                        class="devicewidth" style="background-color: #ffffff;">
                        <tbody>
                            <!-- Start header Section -->
                            <tr>
                                <td style="padding-top: 30px;">
                                    <img style="display: block; margin-left: auto; margin-right: auto;"
                                        src="{{ $logo }}" alt="Logo" width="100" height="100">
                                    <br><br>
                                    <h3 style="text-align: center;">Mon Gravier -- Information de commande</h3>
                                    <table width="560" align="center" cellpadding="0" cellspacing="0" border="0"
                                        class="devicewidthinner"
                                        style="border-bottom: 1px solid #eeeeee; text-align: center;">
                                        <tbody>
                                            <tr>
                                                <td style="font-size: 14px; line-height: 18px; color: #666666;">
                                                    Client: {{ $nomPrenom }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 14px; line-height: 18px; color: #666666;">
                                                    Tel: {{ $contact }} | Email: {{ $email }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    style="font-size: 14px; line-height: 18px; color: #666666; padding-bottom: 25px;">
                                                    <strong>Numéro:</strong> {{ $cde->numero }} | <strong>Date
                                                        Commande:</strong>
                                                    @if ($typeAffaire == Help::$VENTE)
                                                        {{ $cde->date_commande }}
                                                    @else
                                                        {{ $cde->date_location }}
                                                    @endif
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <!-- End header Section -->

                            <!-- Start address Section -->
                            <tr>
                                <td style="padding-top: 0;">
                                    <table width="560" align="center" cellpadding="0" cellspacing="0" border="0"
                                        class="devicewidthinner" style="margin-bottom: 20px;">
                                        <tbody>
                                            <tr>
                                                <td
                                                    style="width: 55%; font-size: 16px; font-weight: bold; color: #666666; padding-bottom: 5px;">
                                                    Addersse de livraison
                                                </td>
                                                @if ($typeAffaire == Help::$VENTE)
                                                    <td
                                                        style="width: 45%; font-size: 16px; font-weight: bold; color: #666666; padding-bottom: 5px;">
                                                        Mode de livraison
                                                    </td>
                                                @endif
                                            </tr>
                                            <tr>
                                                <td
                                                    style="width: 55%; font-size: 14px; line-height: 18px; color: #666666;">
                                                    {{ $cde->adresse ?? 'ND' }}
                                                </td>
                                                @if ($typeAffaire == Help::$VENTE)
                                                    <td
                                                        style="width: 45%; font-size: 14px; line-height: 18px; color: #666666;">
                                                        {{ $cde->type_livraison ?? 'ND' }}
                                                    </td>
                                                @endif
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <br><br>
                            <!-- End address Section -->

                            @php
                                $somme = 0;
                            @endphp

                            @foreach ($lignes as $l)
                                @php
                                    $sommeLoc = 0;
                                    if ($typeAffaire == Help::$VENTE) {
                                        $sommeLoc = $l->prix * $l->qte;
                                        $somme += $sommeLoc;
                                    } else {
                                        $sommeLoc = $l->prix * $l->qte * $l->nombre_jour;
                                        $somme += $sommeLoc;
                                    }
                                @endphp
                                <!-- Start product Section -->
                                <tr>
                                    <td style="padding-top: 0;">
                                        <table width="560" align="center" cellpadding="0" cellspacing="0"
                                            border="0" class="devicewidthinner"
                                            style="border-bottom: 1px solid #eeeeee;">
                                            <tbody>
                                                <tr>
                                                    <td rowspan="4"
                                                        style="padding-right: 10px; padding-bottom: 10px;">
                                                        <img style="height: 80px;" src="{{ $l->image }}"
                                                            alt="Product Image" width="150" height="150" />
                                                    </td>
                                                    <td colspan="2"
                                                        style="font-size: 14px; font-weight: bold; color: #666666; padding-bottom: 5px;">
                                                        {{ $l->nom }}
                                                    </td>
                                                </tr>
                                                @if ($typeAffaire == Help::$LOCATION)
                                                    <tr>
                                                        <td
                                                            style="font-size: 14px; line-height: 18px; color: #757575; width: 440px;">
                                                            {{ $l->nombre_jour }} Jour(s)
                                                        </td>
                                                    </tr>
                                                @endif
                                                <tr>
                                                    <td
                                                        style="font-size: 14px; line-height: 18px; color: #757575; width: 440px;">
                                                        Quantité: {{ $l->qte }} @if ($typeAffaire == Help::$VENTE)
                                                            {{ $l->unite }}
                                                        @endif
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="font-size: 14px; line-height: 18px; color: #757575;">
                                                        Prix unitaire: {{ Help::formatNombre($l->prix, true) }}
                                                    </td>
                                                    <td style="font-size: 14px; line-height: 18px; color: #757575;">
                                                        Sous Total:
                                                        {{ Help::formatNombre($sommeLoc, true) }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endforeach

                            <!-- Start calculation Section -->
                            <tr>
                                <td style="padding-top: 0;">
                                    <table width="560" align="center" cellpadding="0" cellspacing="0" border="0"
                                        class="devicewidthinner"
                                        style="border-bottom: 1px solid #bbbbbb; margin-top: -5px;">
                                        <tbody>
                                            <tr>
                                                <td
                                                    style="font-size: 14px; font-weight: bold; line-height: 18px; color: #666666; padding-top: 10px;">
                                                    Remise
                                                </td>
                                                <td
                                                    style="font-size: 14px; font-weight: bold; line-height: 18px; color: #666666; padding-top: 10px; text-align: right;">
                                                    {{ Help::formatNombre($cde->remise, true) }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    style="font-size: 14px; font-weight: bold; line-height: 18px; color: #666666; padding-top: 10px;">
                                                    Montant HT
                                                </td>
                                                <td
                                                    style="font-size: 14px; font-weight: bold; line-height: 18px; color: #666666; padding-top: 10px; text-align: right;">
                                                    {{ Help::formatNombre($somme, true) }}
                                                </td>
                                            </tr>
                                            @if ($cde->cout_livraison_client > 0)
                                                <tr>
                                                    <td
                                                        style="font-size: 14px; font-weight: bold; line-height: 18px; color: #666666; padding-top: 10px;">
                                                        Cout de livraison
                                                    </td>
                                                    <td
                                                        style="font-size: 14px; font-weight: bold; line-height: 18px; color: #666666; padding-top: 10px; text-align: right;">
                                                        {{ Help::formatNombre($cde->cout_livraison_client, true) }}
                                                    </td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <td
                                                    style="font-size: 14px; font-weight: bold; line-height: 18px; color: #666666;">
                                                    Montant TVA:
                                                </td>
                                                <td
                                                    style="font-size: 14px; font-weight: bold; line-height: 18px; color: #666666; text-align: right;">
                                                    {{ Help::formatNombre($montantTva, true) }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    style="font-size: 14px; font-weight: bold; line-height: 18px; color: #666666; padding-bottom: 10px;">
                                                    Montant TTC
                                                </td>
                                                <td
                                                    style="font-size: 14px; font-weight: bold; line-height: 18px; color: #666666; text-align: right; padding-bottom: 10px;">
                                                    {{ Help::formatNombre($somme + $montantTva + $cde->cout_livraison_client - $cde->remise, true) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <!-- End calculation Section -->

                            <!-- Start payment method Section -->
                            <tr>
                                <td style="padding: 0 10px;">
                                    <table width="560" align="center" cellpadding="0" cellspacing="0" border="0"
                                        class="devicewidthinner">
                                        <tbody>
                                            <tr>
                                                <td colspan="2"
                                                    style="font-size: 16px; font-weight: bold; color: #666666; padding-bottom: 5px;">
                                                    Mode de paiement
                                                </td>
                                            </tr>
                                            <tr>
                                                <td
                                                    style="width: 55%; font-size: 14px; line-height: 18px; color: #666666;">
                                                    {{ $cde->mode_paiement ?? 'ND' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2"
                                                    style="width: 100%; text-align: center; font-style: italic; font-size: 13px; font-weight: 600; color: #666666; padding: 15px 0; border-top: 1px solid #eeeeee;">
                                                    <b style="font-size: 14px;">Note:</b> Généré automatiquement.
                                                    Veuillez nous contacté pour toute préoccupation
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <!-- End payment method Section -->
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>
