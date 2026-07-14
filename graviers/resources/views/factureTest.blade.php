<!DOCTYPE html>
<html>
<head>
    <style>
        @page {
    size: 21cm 14.8cm landscape;
}

* {
    font-family: 'Century Gothic';
}

/* Header Styles */
header {
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    border-bottom: 2px solid #333;
    padding-bottom: 10px;
    height: 70px;
}

.logo {
    width: 60px;
    height: auto;
    position: absolute;
}

.company-details {
    text-align: right;
}

.company-details h1 {
    margin: 0;
    font-size: 20px;
}

.company-details p {
    margin: 2px 0;
    font-size: 14px;
}

/* Receipt Container */
.receipt-container {
    /* margin: 20px; */
}

.receipt-title {
    font-size: 10pt;
    color: #595959;
    margin-bottom: 20px;
}

/* Table Styles */
.receipt-table {
    width: 100%;
    position: absolute;
    border-collapse: collapse;
    top: 120px;
}

.section-header {
    /* background-color: grey; */
    color: #ffffff;
    text-align: center;
    width: 20%;
    /* border: 0.75pt solid #bfbfbf; */
}

.field-label {
    width: 155.25pt;
    background-color: #808080;
    color: #ffffff;
    font-size: 9pt;
    /* padding: 8pt; */
    border: 0.75pt solid #bfbfbf;
}

.field-value {
    border: 0.75pt solid #bfbfbf;
    padding: 10px;
    text-align: center;
    font-size: 10pt;
}

.note {
    color: #ffc000;
}

.client-contact, .client-location {
    vertical-align: left;
    padding: 10px;
}

.client-contact p, .client-location p {
    margin: 5px 0;
}

.empty-cell {
    height: 42.5pt;
}

td[colspan="2"].field-value {
    border-top: 0.75pt solid #bfbfbf;
    border-right: 0.75pt solid #bfbfbf;
    border-bottom: 0.75pt solid #bfbfbf;
    padding-right: 5.03pt;
    padding-left: 5.4pt;
}

/* Empty Cells */
.empty-cell {
    background: red;
    /* height: 42.5pt; */
    /* vertical-align: top; */
}

/* Text Styles */
.text-primary {
    color: #000;
}

strong {
    font-weight: bold;
}
.client-section{
    background: grey;
    height: 50px;
}
    </style>
</head>
<body>
    <header>
        <img src="{{'data:image/png;base64,'.base64_encode(file_get_contents($image))}}" alt="Logo" class="logo">
        <div class="company-details">
            <h1 class="text-primary">IMLOD</h1>
            <p>Sis à Yopougon Terminus 27</p>
            <p>Email: imlod@gravierr.com</p>
            <p>Téléphone: +225 01 23 45 67 89</p>
        </div>
    </header>

    <div class="receipt-container">
        <h1 class="receipt-title">RECU DU PAIEMENT {{$ligne->paiement->code}}</h1>

        <table  class="receipt-table">

            <tr >
                <td colspan="3" class="section-header" style="color: #fff; background:#595959"><p><strong><em>CLIENT</em></strong></p></td>
                <td class="field-label">Le nom de l'agent ayant réceptionné les fonds :</td>
                <td colspan="2" class="field-value"><strong>{{$user->nom.' '.$user->prenom}}</strong></td>
            </tr>

            <tr>
                <td colspan="3">
                    <p>À L'ATTENTION DE : <strong>M./Mme {{$ligne->paiement->client->nom.' '.$ligne->paiement->client->prenom}}</strong></p>
                </td>
                <td class="field-label">DATE</td>
                <td colspan="2" class="field-value"><strong>{{$ligne->created_at->translatedFormat('d F Y')}}</strong></td>
            </tr>

            <tr>
                <td colspan="3"></td>
                <td class="field-label">N° {{$ligne->paiement->location_id == null ? 'COMMANDE' : 'LOCATION'}}</td>
                <td colspan="2" class="field-value">
                    <strong>
                        @if($ligne->paiement->location_id == null)
                            {{$ligne->paiement->devis->numero}}
                        @else
                            {{$ligne->paiement->location->numero}}
                        @endif
                    </strong>
                </td>
            </tr>

            <tr>
                <td colspan="3" class="client-contact">
                    <p>Adresse du client: <strong>{{$ligne->paiement->client->user->adresse}}</strong></p>
                    <p>Téléphone du client: <strong>{{$ligne->paiement->client->contact1}}</strong></p>
                </td>
                <td class="field-label">N° DE REÇU</td>
                <td colspan="2" class="field-value"><strong>{{$ligne->paiement->code}}</strong></td>
            </tr>

            <tr>
                <td colspan="3"></td>
                <td class="field-label">Moyen de paiement</td>
                <td colspan="2" class="field-value"><strong>{{$ligne->modePaiement->description}}</strong></td>
            </tr>

            <tr>
                <td colspan="3"></td>
                <td class="field-label">
                    N° DU CHEQUE OU VIREMENT
                    <em class="note">(si cheque ou virement)</em>
                </td>
                <td colspan="2" class="field-value"><strong>{{$ligne->reference}}</strong></td>
            </tr>

            <tr>
                <td colspan="3" class="client-location">
                    <p>Ville du client: <strong>{{$client->user->ville->nom}}</strong></p>
                    <p>Pays du client: <strong>{{$ligne->paiement->client->user->ville->pays->nom}}</strong></p>
                </td>
                <td class="field-label">MONTANT PAYÉ</td>
                <td colspan="2" class="field-value"><strong>{{number_format($ligne->montant,'0','',' ')}} FCFA</strong></td>
            </tr>
        </table>
    </div>
</body>
</html>
