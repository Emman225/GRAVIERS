import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/commande_success/commande_success_screen.dart';
import 'package:printing/printing.dart';
import 'package:http/http.dart' as http;

import 'package:pdf/pdf.dart';
import 'package:intl/intl.dart';

import '../components/bouton_retour.dart';
import '../../globale.dart';
import '../models/retour_liste_ligne_paiement.dart';
import 'fne_template.dart';

class ImpressionRecuPaiementPdf extends StatefulWidget {
  static String routeName = "/ImprimerRecuPaiement";

  const ImpressionRecuPaiementPdf({super.key});

  @override
  State<ImpressionRecuPaiementPdf> createState() =>
      _ImpressionRecuPaiementPdfState();
}

class _ImpressionRecuPaiementPdfState extends State<ImpressionRecuPaiementPdf> {
  RetourListeLignePaiement ret = RetourListeLignePaiement();
  List<LignePaiement> lignes = [];
  LignePaiement ligne = LignePaiement();
  double somme = 0;
  bool _dataLoaded = false;

  int niveau = 1;
  String codePaiement = "";
  int idPaiement = 0;

  /// LE REÇU DU SITE (lot 88, 15/09/2026) : le client voit sur son téléphone le
  /// même reçu que sur le web. Null quand le site n'a pas répondu : le reçu
  /// local (gabarit FNE) sert alors de repli.
  Uint8List? recuDuSite;
  String motifRecuLocal = '';

  Future<void> chargerRecuDuSite(Map<String, dynamic> param) async {
    try {
      final reponse = await http
          .post(Uri.parse('${lienAPI()}recu-paiement-pdf'),
              headers: {"Content-Type": "application/json"},
              body: jsonEncode(param))
          .timeout(const Duration(seconds: 45));
      final type = reponse.headers['content-type'] ?? '';
      if (reponse.statusCode == 200 &&
          type.contains('pdf') &&
          reponse.bodyBytes.length > 4 &&
          String.fromCharCodes(reponse.bodyBytes.sublist(0, 4)) == '%PDF') {
        recuDuSite = reponse.bodyBytes;
      } else {
        // Le motif renvoyé par l'API (jeton refusé, site injoignable…), sinon le code HTTP.
        try {
          final json = jsonDecode(reponse.body);
          motifRecuLocal = (json is Map && json['message'] != null)
              ? json['message'].toString()
              : 'réponse ${reponse.statusCode}';
        } catch (_) {
          motifRecuLocal = 'réponse ${reponse.statusCode}';
        }
        if (kDebugMode) {
          print('Reçu du site indisponible : $motifRecuLocal');
        }
      }
    } catch (e) {
      motifRecuLocal = messageErreurTechnique(e);
      if (kDebugMode) {
        print('Reçu du site : ${e.toString()}');
      }
    }
  }

  chargerInfosPaiement() async {
    if (await verifierConnexion()) {
      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "niveau": niveau,
        "codePaiement": codePaiement,
        "idPaiement": idPaiement,
      };
      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}liste-ligne-paiement-sur-code'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        if (kDebugMode) {
          print(retourHttp.body);
        }
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          ret = RetourListeLignePaiement.fromJson(datas);
          lignes = ret.data ?? [];
          ligne = lignes.isEmpty ? LignePaiement() : lignes[0];
          somme = lignes.fold(0.0, (sum, p) => sum + (p.montant ?? 0));
          // Le reçu du site, même format que le web (lot 88).
          await chargerRecuDuSite(param);
          if (recuDuSite == null) {
            afficherInfo("Reçu simplifié (version $versionApplication) : $motifRecuLocal");
          }
        }
      } catch (e) {
        user.code = 500;
        if (kDebugMode) {
          print(e.toString());
        }
        afficherErreur(messageErreurTechnique(e));
      }
      if (mounted) {
        setState(() {
          _dataLoaded = true;
        });
      }
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
      if (mounted) {
        setState(() {
          _dataLoaded = true;
        });
      }
    }
  }

  @override
  void initState() {
    var data = Get.arguments;
    if (kDebugMode) {
      print(data);
    }
    niveau = data[0];
    codePaiement = data[1];
    idPaiement = data[2];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerInfosPaiement();
    });
  }

  @override
  Widget build(BuildContext context) {
    return WillPopScope(
      onWillPop: () async {
        if (niveau == 1) {
          Get.toNamed(CommandeSuccessScreen.routeName,
              arguments: "Paiement effectué avec succès");
        } else {
          Get.back();
        }
        return true;
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text(
            "Imprimer mon reçu de paiement",
          ),
          elevation: 0,
          leading: BoutonRetour(onTap: () {
                if (niveau == 1) {
                  Get.toNamed(CommandeSuccessScreen.routeName,
                      arguments: "Paiement effectué avec succès");
                } else {
                  Get.back();
                }
              }),
        ),
        body: !_dataLoaded
            ? const Center(child: CircularProgressIndicator())
            : PdfPreview(
                canChangeOrientation: false,
                canChangePageFormat: false,
                canDebug: false,
                initialPageFormat: PdfPageFormat.a4,
                pdfFileName: "$codePaiement.pdf",
                build: (context) async {
                  // Le reçu du site quand il est là, le reçu local sinon (lot 88).
                  return recuDuSite ?? await makePdf();
                },
              ),
      ),
    );
  }

  makePdf() async {
    // Préparer les articles FNE à partir des lignes de paiement
    List<FneArticle> articles = [];

    for (int i = 0; i < lignes.length; i++) {
      final l = lignes[i];
      articles.add(FneArticle(
        ref: (i + 1).toString().padLeft(2, '0'),
        designation: l.libelle ?? 'Paiement',
        puHt: l.montant?.toDouble() ?? 0,
        qte: 1,
        unite: 'Forfait',
        taxes: '0',
        remise: 0,
        montantHt: l.montant?.toDouble() ?? 0,
      ));
    }

    // Résumé fiscal
    List<FneResumeFiscal> resumeFiscal = [
      FneResumeFiscal(
        categorie: 'TVA exo.lég - Pas de TVA sur HT 00,00% - D',
        sousTotal: somme,
        taux: '0%',
        totalTaxes: 0,
      ),
    ];

    // Client FNE
    FneClient clientFne = FneClient(
      nom: ligne.nom ?? user.nom ?? '',
      adresse: '${ligne.pays ?? ""}, ${ligne.ville ?? ""}, ${ligne.adresse ?? ""}',
    );

    final pdf = await FneTemplate.genererDocument(
      typeDocument: 'Reçu de paiement',
      numero: ligne.codePaiement ?? codePaiement,
      date: ligne.datePaiement ?? DateFormat('dd/MM/yyyy HH:mm:ss').format(DateTime.now()),
      client: clientFne,
      articles: articles,
      totalHt: somme,
      totalTva: 0,
      totalTtc: somme,
      totalAPayer: somme,
      resumeFiscal: resumeFiscal,
      vendeur: ligne.userId != null ? ligne.gestionnaire : 'IMLOD ONLINE',
      modePaiement: ligne.moyenPaiement,
    );

    return pdf.save();
  }
}
