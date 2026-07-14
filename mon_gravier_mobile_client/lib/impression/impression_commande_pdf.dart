import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:printing/printing.dart';

import 'package:pdf/pdf.dart';

import '../../globale.dart';
import '../models/Commande.dart';
import '../models/InformationsCommande.dart';
import 'fne_template.dart';

class ImpressionCommandePdf extends StatelessWidget {
  final UneCommande commande;
  final List<LigneCommande> lignes;
  late final double somme;

  // Prix STOCKÉ sur la ligne (inclut le prix personnalisé du client),
  // repli sur le prix catalogue si absent.
  static double _prixLigne(LigneCommande l) =>
      (l.prix ?? 0) > 0 ? l.prix!.toDouble() : (l.prixMoyen ?? 0).toDouble();

  ImpressionCommandePdf(this.commande, this.lignes, {super.key}) {
    somme = lignes.fold(0.0, (sum, p) => sum + (_prixLigne(p) * p.qte!));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Imprimer ma commande",
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
        pdfFileName: "${commande.numero}.pdf",
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
      double montant = _prixLigne(l) * l.qte!;
      totalHt += montant;

      articles.add(FneArticle(
        ref: (i + 1).toString().padLeft(2, '0'),
        designation: l.nom ?? '',
        puHt: _prixLigne(l),
        qte: l.qte!.toDouble(),
        unite: l.unite ?? 'U',
        taxes: 'TVA (${tva}%)',
        remise: 0,
        montantHt: montant,
      ));
    }

    double montantTvaCalc = commande.montant_tva ?? 0;
    double coutLivraison = commande.cout_livraison_client ?? 0;
    double remise = commande.remise ?? 0;
    double totalTtc = totalHt + montantTvaCalc;
    double totalAPayer = commande.montantTotal?.toDouble() ?? totalTtc + coutLivraison - remise;

    // Ligne livraison
    if (coutLivraison > 0) {
      articles.add(FneArticle(
        ref: '',
        designation: 'Coût de livraison (${commande.adresse ?? ""})',
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
      typeDocument: 'Bon de commande',
      numero: commande.numero ?? '',
      date: DateFormat('dd/MM/yyyy HH:mm:ss').format(DateTime.now()),
      client: clientFne,
      articles: articles,
      totalHt: totalHt,
      totalTva: montantTvaCalc,
      totalTtc: totalTtc,
      totalAPayer: totalAPayer,
      resumeFiscal: resumeFiscal,
      modePaiement: commande.modePaiement,
      adresseLivraison: commande.adresse,
    );

    return pdf.save();
  }
}
