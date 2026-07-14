import 'package:flutter/material.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/impression/fne_template.dart';
import 'package:intl/intl.dart';

class FnePreviewWidget extends StatelessWidget {
  final FneConfig config;
  final FneClient client;
  final List<FneArticle> articles;
  final double totalHt;
  final double totalTva;
  final double totalTtc;
  final double autresTaxes;
  final double totalAPayer;
  final List<FneResumeFiscal> resumeFiscal;
  final String date;
  final String? vendeur;
  final String? modePaiement;
  final String? adresseLivraison;

  const FnePreviewWidget({
    Key? key,
    required this.config,
    required this.client,
    required this.articles,
    required this.totalHt,
    required this.totalTva,
    required this.totalTtc,
    required this.autresTaxes,
    required this.totalAPayer,
    required this.resumeFiscal,
    required this.date,
    this.vendeur,
    this.modePaiement,
    this.adresseLivraison,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    String qrData = FneTemplate.genererQrData(
      ncc: config.ncc,
      typeDoc: "TICKET PROFORMA",
      numero: "0000", // Pas encore validée
      date: date,
      montantTtc: totalAPayer,
    );

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.all(12.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Logo & Type Doc
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Image.asset('assets/images/logoDALAKOUN.png', width: 70, height: 70),
                  const SizedBox(height: 4),
                  const Text(
                    'PROFORMA',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 8),

          // En-tête FNE (Émetteur + QR + Badge CI)
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Encadré émetteur
              Expanded(
                flex: 45,
                child: Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    border: Border.all(width: 1.5),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('DALAKOUN', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 10)),
                      Text('NCC : ${config.ncc}', style: const TextStyle(fontSize: 8)),
                      Text('Régime d\'imposition : ${config.regimeImposition}', style: const TextStyle(fontSize: 8)),
                      Text('Centre des impôts : ${config.centreImpots}', style: const TextStyle(fontSize: 8)),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 10),
              // QR Code + Badge
              Expanded(
                flex: 55,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      width: 60,
                      height: 60,
                      child: const Icon(Icons.qr_code_2, size: 50, color: Colors.black87),
                    ),
                    const SizedBox(width: 5),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Container(
                          width: 50,
                          height: 50,
                          decoration: BoxDecoration(
                            border: Border.all(color: Colors.orange, width: 2),
                            borderRadius: BorderRadius.circular(25),
                          ),
                          alignment: Alignment.center,
                          child: ClipOval(
                            child: Image.asset('assets/images/logoci.png', width: 35, height: 35, fit: BoxFit.cover),
                          ),
                        ),
                        const SizedBox(height: 2),
                        const Text(
                          'FACTURE NORMALISÉE\nÉLECTRONIQUE',
                          style: TextStyle(fontSize: 6, fontWeight: FontWeight.bold, color: Colors.orange),
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Infos Émetteur / Client
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 55,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('RCCM : ${config.rccm}', style: const TextStyle(fontSize: 8)),
                    Text('Références bancaires : ${config.refBancaires}', style: const TextStyle(fontSize: 8)),
                    Text('Établissement : ${config.nomEtablissement}', style: const TextStyle(fontSize: 8)),
                    Text('Adresse : ${config.adresseSiege}', style: const TextStyle(fontSize: 8)),
                    Text('Nº Tel : ${config.telephone}', style: const TextStyle(fontSize: 8)),
                    Text('Mail : ${config.emailEntreprise}', style: const TextStyle(fontSize: 8)),
                    if (vendeur != null) Text('Nom du vendeur : $vendeur', style: const TextStyle(fontSize: 8)),
                    Text('Nom de PDV : ${config.nomPdv}', style: const TextStyle(fontSize: 8)),
                    Text('Date et heure : $date', style: const TextStyle(fontSize: 8)),
                    if (modePaiement != null) Text('Mode de paiement : $modePaiement', style: const TextStyle(fontSize: 8)),
                    if (adresseLivraison != null) Text('ADRESSE : $adresseLivraison', style: const TextStyle(fontSize: 8)),
                  ],
                ),
              ),
              const SizedBox(width: 5),
              Expanded(
                flex: 45,
                child: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey.shade400),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Client', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 9)),
                      const SizedBox(height: 2),
                      Text('Nom : ${client.nom}', style: const TextStyle(fontSize: 8)),
                      Text('Adresse : ${client.adresse}', style: const TextStyle(fontSize: 8)),
                      Text('NCC : ${client.ncc}', style: const TextStyle(fontSize: 8)),
                      Text('Régime d\'imp. : ${client.regimeImposition}', style: const TextStyle(fontSize: 8)),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Tableau articles
          Table(
            border: TableBorder.all(color: Colors.grey.shade400, width: 0.5),
            columnWidths: const {
              0: FlexColumnWidth(1.2),
              1: FlexColumnWidth(3),
              2: FlexColumnWidth(1.5),
              3: FlexColumnWidth(0.8),
              4: FlexColumnWidth(0.8),
              5: FlexColumnWidth(1.5),
              6: FlexColumnWidth(1.2),
              7: FlexColumnWidth(1.8),
            },
            children: [
              TableRow(
                decoration: BoxDecoration(color: Colors.grey.shade200),
                children: [
                  _headerCell("Réf"), _headerCell("Désignation"), _headerCell("P.U HT"),
                  _headerCell("Qté"), _headerCell("Unité"), _headerCell("Taxes(%)"),
                  _headerCell("Rem(%)"), _headerCell("Montant HT"),
                ],
              ),
              ...articles.map((a) => TableRow(
                children: [
                  _cell(a.ref),
                  _cell(a.designation, align: TextAlign.left),
                  _cell(formaterMontant(a.puHt), align: TextAlign.right),
                  _cell(a.qte.toStringAsFixed(0)),
                  _cell(a.unite),
                  _cell(a.taxes),
                  _cell(a.remise.toStringAsFixed(0)),
                  _cell(formaterMontant(a.montantHt), align: TextAlign.right),
                ],
              )),
            ],
          ),
          const SizedBox(height: 10),

          // Totaux
          Row(
            children: [
              const Spacer(),
              SizedBox(
                width: MediaQuery.of(context).size.width * 0.5,
                child: Column(
                  children: [
                    _totauxRow('TOTAL HT', totalHt),
                    _totauxRow('TVA', totalTva),
                    _totauxRow('TOTAL TTC', totalTtc),
                    _totauxRow('AUTRES TAXES', autresTaxes),
                    _totauxRow('TOTAL A PAYER', totalAPayer, isBold: true),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 15),

          // Résumé fiscal
          const Text('RÉSUMÉ DE LA FACTURE (FISCAL)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 10)),
          const SizedBox(height: 4),
          Table(
            border: TableBorder.all(color: Colors.grey.shade400, width: 0.5),
            columnWidths: const {
              0: FlexColumnWidth(4),
              1: FlexColumnWidth(2),
              2: FlexColumnWidth(2),
              3: FlexColumnWidth(2),
            },
            children: [
              TableRow(
                decoration: BoxDecoration(color: Colors.grey.shade200),
                children: [
                  _headerCell("CATÉGORIE", align: TextAlign.left),
                  _headerCell("SOUS-TOTAL", align: TextAlign.right),
                  _headerCell("TAUX (%)"),
                  _headerCell("TAXES", align: TextAlign.right),
                ],
              ),
              ...resumeFiscal.map((r) => TableRow(
                children: [
                  _cell(r.categorie, align: TextAlign.left),
                  _cell(formaterMontant(r.sousTotal), align: TextAlign.right),
                  _cell(r.taux),
                  _cell(formaterMontant(r.totalTaxes), align: TextAlign.right),
                ],
              )),
            ],
          ),
          const SizedBox(height: 15),

          // Legal Footer
          Container(
            width: double.infinity,
            padding: const EdgeInsets.only(top: 8),
            decoration: const BoxDecoration(
              border: Border(top: BorderSide(width: 0.5, color: Colors.grey)),
            ),
            child: Text(
              'DALAKOUN, SARL au Capital de ${config.capitalSocial} - RCCM N° ${config.rccm}, CC N°${config.ncc}, CNPS N°${config.cnps}\n'
              'Régime d\'imposition ${config.regimeImposition}\n'
              'Centre des impôts ${config.centreImpots}',
              style: const TextStyle(fontSize: 7, color: Colors.black54),
              textAlign: TextAlign.center,
            ),
          )
        ],
      ),
    );
  }

  Widget _headerCell(String text, {TextAlign align = TextAlign.center}) {
    return Padding(
      padding: const EdgeInsets.all(4),
      child: Text(text, textAlign: align, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 6.5)),
    );
  }

  Widget _cell(String text, {TextAlign align = TextAlign.center}) {
    return Padding(
      padding: const EdgeInsets.all(4),
      child: Text(text, textAlign: align, style: const TextStyle(fontSize: 6.5)),
    );
  }

  Widget _totauxRow(String label, double amount, {bool isBold = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 2, horizontal: 4),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade400, width: 0.5),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: 8, fontWeight: isBold ? FontWeight.bold : FontWeight.w600)),
          Text(formaterMontant(amount), style: TextStyle(fontSize: 8, fontWeight: isBold ? FontWeight.bold : FontWeight.normal)),
        ],
      ),
    );
  }
}
