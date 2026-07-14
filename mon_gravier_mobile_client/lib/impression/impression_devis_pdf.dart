import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:printing/printing.dart';

import 'package:pdf/pdf.dart';

import '../../globale.dart';
import '../models/detail_devis.dart';
import '../models/devis.dart';
import 'fne_template.dart';

class ImpressionDevisPdf extends StatelessWidget {
  final DataDevis devis;
  final List<DataDetailDevis> lignes;
  late final double somme;

  ImpressionDevisPdf(this.devis, this.lignes, {super.key}) {
    if (devis.service == LOCATION) {
      somme = lignes.fold(
          0.0, (sum, p) => sum + (_prixLigne(p) * p.qte! * p.nbre_jour_location!));
    } else {
      somme = lignes.fold(0.0, (sum, p) => sum + (_prixLigne(p) * p.qte!));
    }
  }

  static double _prixLigne(DataDetailDevis l) =>
      (l.prix ?? 0) > 0 ? l.prix!.toDouble() : (l.prixMoyen ?? 0).toDouble();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Imprimer mon devis",
          style: TextStyle(color: Colors.black),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.all(8.0),
          child: ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(
              shape: const CircleBorder(),
              padding: EdgeInsets.zero,
              elevation: 0,
              backgroundColor: Colors.white,
            ),
            child: const Icon(
              Icons.arrow_back_ios_new,
              color: Colors.black,
              size: 20,
            ),
          ),
        ),
      ),
      body: PdfPreview(
        canChangeOrientation: false,
        canChangePageFormat: false,
        canDebug: false,
        initialPageFormat: PdfPageFormat.a4,
        pdfFileName: "${devis.numero}.pdf",
        build: (context) async => await makePdf(),
      ),
    );
  }

  makePdf() async {
    // Préparer les articles FNE
    List<FneArticle> articles = [];
    double totalHt = 0;

    for (int i = 0; i < lignes.length; i++) {
      final l = lignes[i];
      double montant;
      String designation = l.nom ?? '';
      String unite = l.unite ?? 'U';

      double prixUnitaire = _prixLigne(l);
      if (devis.service == LOCATION) {
        montant = prixUnitaire * l.qte! * l.nbre_jour_location!;
        designation += ' (${l.nbre_jour_location} Jrs)';
      } else {
        montant = prixUnitaire * l.qte!;
      }

      totalHt += montant;

      articles.add(FneArticle(
        ref: (i + 1).toString().padLeft(2, '0'),
        designation: designation,
        puHt: prixUnitaire,
        qte: l.qte!.toDouble(),
        unite: unite,
        taxes: 'TVA (${tva}%)',
        remise: 0,
        montantHt: montant,
      ));
    }

    double montantTvaCalc = devis.tva ?? 0;
    double coutLivraison = devis.cout_livraison ?? 0;
    double coutReductionDevis = devis.cout_reduction ?? 0;
    double totalTtc = totalHt + montantTvaCalc;
    double totalAPayer = (devis.montant ?? 0) + montantTvaCalc + coutLivraison - coutReductionDevis;

    // Ajouter ligne livraison si applicable
    if (coutLivraison > 0) {
      articles.add(FneArticle(
        ref: '',
        designation: 'Coût de livraison (${devis.adresse_livraison ?? ""})',
        puHt: coutLivraison,
        qte: 1,
        unite: 'Forfait',
        taxes: '0',
        remise: 0,
        montantHt: coutLivraison,
      ));
    }

    // Résumé fiscal
    List<FneResumeFiscal> resumeFiscal = [];
    if (tva > 0) {
      resumeFiscal.add(FneResumeFiscal(
        categorie: 'TVA $tva% sur HT',
        sousTotal: totalHt,
        taux: '$tva%',
        totalTaxes: montantTvaCalc,
      ));
    } else {
      resumeFiscal.add(FneResumeFiscal(
        categorie: 'TVA exo.lég - Pas de TVA sur HT 00,00% - D',
        sousTotal: totalHt,
        taux: '0%',
        totalTaxes: 0,
      ));
    }

    // Client FNE
    FneClient clientFne = FneClient(
      nom: user.nom ?? '',
    );

    final pdf = await FneTemplate.genererDocument(
      typeDocument: 'Devis',
      numero: devis.numero ?? '',
      date: DateFormat('dd/MM/yyyy HH:mm:ss').format(DateTime.now()),
      client: clientFne,
      articles: articles,
      totalHt: totalHt,
      totalTva: montantTvaCalc,
      totalTtc: totalTtc,
      totalAPayer: totalAPayer,
      resumeFiscal: resumeFiscal,
      adresseLivraison: devis.adresse_livraison,
    );

    return pdf.save();
  }
}
