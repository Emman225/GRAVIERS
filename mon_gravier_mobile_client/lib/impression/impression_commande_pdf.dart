import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:printing/printing.dart';

import 'package:pdf/pdf.dart';

import '../components/bouton_retour.dart';
import '../../globale.dart';
import '../models/Commande.dart';
import '../models/InformationsCommande.dart';
import 'fne_template.dart';
import 'totaux_document.dart';

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
        ),
        elevation: 0,
        leading: const BoutonRetour(),
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
        // Le numéro de bon interne en colonne Réf (10/09/2026), la désignation nue.
        ref: referenceLigne(commande.numero_bl, i + 1),
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
    double tvaTransport = commande.tva_transport ?? 0;
    double remise = commande.remise ?? 0;

    // Les totaux se calculent depuis les LIGNES, jamais depuis `montantTotal` :
    // cette colonne porte le HT côté site et le net côté mobile, si bien que le
    // même bon annonçait deux montants différents selon l'endroit où la
    // commande avait été passée. Voir TotauxDocument.
    final totaux = TotauxDocument(
      htArticles: totalHt,
      livraison: coutLivraison,
      tva: montantTvaCalc,
      tvaTransport: tvaTransport,
      remise: remise,
    );

    // Ligne livraison
    if (coutLivraison > 0 && totaux.transportEnLigne) {
      articles.add(FneArticle(
        ref: '',
        designation: 'Coût de livraison (${commande.adresse ?? ""})',
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
      // Proforma tant que rien n'est payé en ligne, facture dès qu'un règlement
      // en ligne est validé (lot 81, 15/09/2026) — le document du site.
      typeDocument: (commande.payeEnLigne ?? 0) == 1 ? 'Facture de vente' : 'Proforma',
      numero: commande.numero ?? '',
      date: DateFormat('dd/MM/yyyy HH:mm:ss').format(DateTime.now()),
      client: clientFne,
      articles: articles,
      totalHt: totaux.htAffiche,
      totalTva: montantTvaCalc,
      totalTtc: totaux.ttcAffiche,
      tvaTransport: tvaTransport,
      livraisonHorsTableau: totaux.livraisonHorsTableau,
      // AIRSI figé sur la commande, dans « autres taxes » (10/09/2026).
      autresTaxes: commande.airsi ?? 0,
      totalAPayer: totaux.aPayer + (commande.airsi ?? 0),
      remise: remise,
      resumeFiscal: resumeFiscal,
      modePaiement: commande.modePaiement,
      adresseLivraison: commande.adresse,
    );

    return pdf.save();
  }
}
