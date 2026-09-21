import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:printing/printing.dart';

import 'package:pdf/pdf.dart';

import '../components/bouton_retour.dart';
import '../../globale.dart';
import '../models/detail_devis.dart';
import '../models/devis.dart';
import 'fne_template.dart';
import 'totaux_document.dart';

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
        ),
        elevation: 0,
        leading: const BoutonRetour(),
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
      // Le numéro de bon interne en colonne Réf (13/09/2026), la désignation nue.
      String designation = l.nom ?? '';
      String unite = l.unite ?? 'U';

      double prixUnitaire = _prixLigne(l);
      if (devis.service == LOCATION) {
        montant = prixUnitaire * l.qte! * l.nbre_jour_location!;
        designation += ' (${l.nbre_jour_location} jour(s))';
      } else {
        montant = prixUnitaire * l.qte!;
      }

      totalHt += montant;

      articles.add(FneArticle(
        ref: referenceLigne(devis.numero_bon_commande, i + 1),
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
    // ATTENTION : `devis.montant` n'est pas la source des totaux. Comme
    // `montant_total` d'une commande, cette colonne ne veut pas dire la même
    // chose selon le canal. Les totaux se calculent depuis les lignes.
    double coutLivraison = devis.cout_livraison ?? 0;
    double tvaTransport = devis.tva_transport ?? 0;
    double coutReductionDevis = devis.cout_reduction ?? 0;

    final totaux = TotauxDocument(
      htArticles: totalHt,
      livraison: coutLivraison,
      tva: montantTvaCalc,
      tvaTransport: tvaTransport,
      remise: coutReductionDevis,
    );

    // Ajouter ligne livraison si applicable
    if (coutLivraison > 0 && totaux.transportEnLigne) {
      articles.add(FneArticle(
        ref: '',
        designation: 'Coût de livraison (${devis.adresse_livraison ?? ""})',
        puHt: coutLivraison,
        qte: 1,
        unite: 'Forfait',
        taxes: tvaTransport > 0 ? 'TVA ($tva%)' : '0',
        remise: 0,
        montantHt: coutLivraison,
      ));
    }

    // Résumé fiscal
    List<FneResumeFiscal> resumeFiscal = [];
    if (tva > 0) {
      // Une catégorie : l'assiette compte le transport quand il est taxé, et
      // les taxes fondent les deux TVA (09/09/2026, comme le site).
      resumeFiscal.add(FneResumeFiscal(
        categorie: 'TVA $tva% sur HT',
        sousTotal: totaux.assietteTva,
        taux: '$tva%',
        totalTaxes: totaux.tvaDocument,
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
      totalHt: totaux.htAffiche,
      totalTva: montantTvaCalc,
      totalTtc: totaux.ttcAffiche,
      tvaTransport: tvaTransport,
      livraisonHorsTableau: totaux.livraisonHorsTableau,
      totalAPayer: totaux.aPayer,
      remise: coutReductionDevis,
      resumeFiscal: resumeFiscal,
      adresseLivraison: devis.adresse_livraison,
    );

    return pdf.save();
  }
}
