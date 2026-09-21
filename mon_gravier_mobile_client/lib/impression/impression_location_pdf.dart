import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:printing/printing.dart';

import 'package:pdf/pdf.dart';

import '../components/bouton_retour.dart';
import '../../globale.dart';
import '../models/InformationsCommande.dart';
import 'fne_template.dart';
import 'totaux_document.dart';

class ImpressionLocationPdf extends StatelessWidget {
  final UneLocation location;
  final List<LigneLocation> lignes;
  late final double somme;

  // Prix STOCKÉ sur la ligne (inclut le prix personnalisé du client),
  // repli sur le prix catalogue si absent.
  static double _prixLigne(dynamic l) =>
      (l.prix ?? 0) > 0 ? l.prix!.toDouble() : (l.prixMoyen ?? 0).toDouble();

  ImpressionLocationPdf(this.location, this.lignes, {super.key}) {
    somme = lignes.fold(
        0.0, (sum, p) => sum + (_prixLigne(p) * p.qte! * p.nombreJour!));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Imprimer ma location",
        ),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: PdfPreview(
        canChangeOrientation: false,
        canChangePageFormat: false,
        canDebug: false,
        initialPageFormat: PdfPageFormat.a4,
        pdfFileName: "${location.numero}.pdf",
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
      double montant = _prixLigne(l) * l.qte! * l.nombreJour!;
      totalHt += montant;

      // Le numéro de bon interne en colonne Réf (13/09/2026), la désignation nue.
      String designation = l.nom ?? '';
      if (l.debut != null && l.fin != null) {
        designation +=
            '\nDu ${formaterDate(l.debut.toString(), format: 'dd/MM/yyyy')} '
            'au ${formaterDate(l.fin.toString(), format: 'dd/MM/yyyy')} '
            'soit ${l.nombreJour} Jour(s)';
      }

      articles.add(FneArticle(
        ref: referenceLigne(location.numero_bon_commande, i + 1),
        designation: designation,
        puHt: _prixLigne(l),
        qte: l.qte!.toDouble(),
        unite: l.unite ?? 'Jour',
        taxes: 'TVA (${tva}%)',
        remise: 0,
        montantHt: montant,
      ));
    }

    double montantTvaCalc = location.montant_tva ?? 0;
    double coutLivraison = location.cout_livraison_client ?? 0;
    double tvaTransport = location.tva_transport ?? 0;
    double remise = location.remise ?? 0;

    // Comme pour le bon de commande : les totaux viennent des lignes, pas de
    // `montantTotal`, dont le sens change selon le canal.
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
        designation: 'Coût de livraison (${location.adresse ?? ""})',
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
      typeDocument: 'Bon de location',
      numero: location.numero ?? '',
      date: DateFormat('dd/MM/yyyy HH:mm:ss').format(DateTime.now()),
      client: clientFne,
      articles: articles,
      totalHt: totaux.htAffiche,
      totalTva: montantTvaCalc,
      totalTtc: totaux.ttcAffiche,
      tvaTransport: tvaTransport,
      livraisonHorsTableau: totaux.livraisonHorsTableau,
      totalAPayer: totaux.aPayer,
      remise: remise,
      resumeFiscal: resumeFiscal,
      modePaiement: location.modePaiement,
      adresseLivraison: location.adresse,
    );

    return pdf.save();
  }
}
