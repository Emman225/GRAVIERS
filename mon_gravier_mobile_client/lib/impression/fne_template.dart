import 'dart:convert';
import 'package:flutter/services.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:http/http.dart' as http;

import '../../globale.dart';

/// Configuration FNE de l'entreprise - à charger depuis l'API
class FneConfig {
  String raisonSociale;
  String ncc;
  String regimeImposition;
  String centreImpots;
  String rccm;
  String refBancaires;
  String cnps;
  String capitalSocial;
  String adresseSiege;
  String telephone;
  String emailEntreprise;
  String nomEtablissement;
  String nomPdv;
  double tauxTva;
  String devise;

  FneConfig({
    this.raisonSociale = 'DALAKOUN',
    this.ncc = '',
    this.regimeImposition = '',
    this.centreImpots = '',
    this.rccm = '',
    this.refBancaires = '',
    this.cnps = '',
    this.capitalSocial = '',
    this.adresseSiege = '',
    this.telephone = '',
    this.emailEntreprise = '',
    this.nomEtablissement = '',
    this.nomPdv = '',
    this.tauxTva = 0,
    this.devise = 'FCFA',
  });

  factory FneConfig.fromJson(Map<String, dynamic> json) {
    return FneConfig(
      raisonSociale: json['raison_sociale'] ?? 'DALAKOUN',
      ncc: json['ncc'] ?? '',
      regimeImposition: json['regime_imposition'] ?? '',
      centreImpots: json['centre_impots'] ?? '',
      rccm: json['rccm'] ?? '',
      refBancaires: json['ref_bancaires'] ?? '',
      cnps: json['cnps'] ?? '',
      capitalSocial: json['capital_social'] ?? '',
      adresseSiege: json['adresse_siege'] ?? '',
      telephone: json['telephone'] ?? '',
      emailEntreprise: json['email_entreprise'] ?? '',
      nomEtablissement: json['nom_etablissement'] ?? '',
      nomPdv: json['nom_pdv'] ?? '',
      tauxTva: double.tryParse(json['tva']?.toString() ?? '0') ?? 0,
      devise: json['devise'] ?? 'FCFA',
    );
  }
}

/// Info client pour FNE
class FneClient {
  String nom;
  String adresse;
  String ncc;
  String regimeImposition;

  FneClient({
    this.nom = '',
    this.adresse = '',
    this.ncc = '',
    this.regimeImposition = '',
  });
}

/// Ligne d'article FNE
class FneArticle {
  String ref;
  String designation;
  double puHt;
  double qte;
  String unite;
  String taxes;
  double remise;
  double montantHt;

  FneArticle({
    required this.ref,
    required this.designation,
    required this.puHt,
    required this.qte,
    this.unite = 'U',
    this.taxes = '0',
    this.remise = 0,
    required this.montantHt,
  });
}

/// Résumé fiscal FNE
class FneResumeFiscal {
  String categorie;
  double sousTotal;
  String taux;
  double totalTaxes;

  FneResumeFiscal({
    required this.categorie,
    required this.sousTotal,
    required this.taux,
    required this.totalTaxes,
  });
}

/// Classe utilitaire pour générer les PDF au format FNE
class FneTemplate {
  static FneConfig? _configCache;

  /// Charge la configuration FNE depuis l'API
  static Future<FneConfig> chargerConfig() async {
    if (_configCache != null) return _configCache!;

    try {
      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
      };

      final response = await http
          .post(
            Uri.parse('${lienAPI()}configuration-fne'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param),
          )
          .timeout(const Duration(seconds: 30));

      if (response.statusCode == 200) {
        var data = jsonDecode(response.body);
        if (data['data'] != null) {
          _configCache = FneConfig.fromJson(data['data']);
          return _configCache!;
        }
      }
    } catch (e) {
      // Fallback sur la config par défaut
    }

    _configCache = FneConfig();
    return _configCache!;
  }

  /// Remet la config en cache à null pour forcer le rechargement
  static void resetConfig() {
    _configCache = null;
  }

  /// Génère les données QR Code pour un document
  static String genererQrData({
    required String ncc,
    required String typeDoc,
    required String numero,
    required String date,
    required double montantTtc,
  }) {
    return 'NCC:$ncc|$typeDoc:$numero|D:$date|TTC:${montantTtc.toStringAsFixed(0)}FCFA';
  }

  /// Génère le document PDF complet au format FNE
  static Future<pw.Document> genererDocument({
    required String typeDocument,
    required String numero,
    required String date,
    required FneClient client,
    required List<FneArticle> articles,
    required double totalHt,
    required double totalTva,
    required double totalTtc,
    double autresTaxes = 0,
    double totalAPayer = 0,
    required List<FneResumeFiscal> resumeFiscal,
    String? vendeur,
    String? modePaiement,
    String? adresseLivraison,
  }) async {
    final config = await chargerConfig();

    // Charger les 2 logos
    final logoDALAKOUN = pw.MemoryImage(
      (await rootBundle.load('assets/images/logoDALAKOUN.png')).buffer.asUint8List(),
    );
    final logoCI = pw.MemoryImage(
      (await rootBundle.load('assets/images/logoci.png')).buffer.asUint8List(),
    );

    if (totalAPayer == 0) totalAPayer = totalTtc;

    // Générer les données QR Code
    final qrData = genererQrData(
      ncc: config.ncc,
      typeDoc: typeDocument,
      numero: numero,
      date: date,
      montantTtc: totalAPayer,
    );

    final pdf = pw.Document();

    pdf.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        margin: const pw.EdgeInsets.symmetric(vertical: 30, horizontal: 45),
        build: (context) => [
          // ===== LOGO DALAKOUN EN HAUT À DROITE =====
          _buildLogoHeader(logoDALAKOUN, typeDocument, numero),
          pw.SizedBox(height: 8),

          // ===== EN-TÊTE FNE : encadré émetteur + QR Code + badge CI =====
          _buildEntete(config, logoCI, qrData),
          pw.SizedBox(height: 8),

          // ===== INFOS ÉMETTEUR + CLIENT =====
          _buildInfosEmetteurClient(config, client, date, vendeur, modePaiement, adresseLivraison),
          pw.SizedBox(height: 12),

          // ===== TABLEAU ARTICLES =====
          _buildTableauArticles(articles),
          pw.SizedBox(height: 5),

          // ===== TOTAUX =====
          _buildTotaux(totalHt, totalTva, totalTtc, autresTaxes, totalAPayer),
          pw.SizedBox(height: 10),

          // ===== RÉSUMÉ FISCAL =====
          _buildResumeFiscal(resumeFiscal),

          pw.Expanded(child: pw.Container()),

          // ===== PIED DE PAGE =====
          _buildFooter(config),
        ],
      ),
    );

    return pdf;
  }

  /// Logo DALAKOUN en haut à droite + numéro document en dessous
  static pw.Widget _buildLogoHeader(
    pw.MemoryImage logoDALAKOUN,
    String typeDocument,
    String numero,
  ) {
    return pw.Row(
      mainAxisAlignment: pw.MainAxisAlignment.end,
      children: [
        pw.Column(
          crossAxisAlignment: pw.CrossAxisAlignment.end,
          children: [
            pw.SizedBox(width: 90, height: 90, child: pw.Image(logoDALAKOUN)),
            pw.SizedBox(height: 4),
            pw.Text(
              '$typeDocument Nº $numero',
              style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 10),
              textAlign: pw.TextAlign.right,
            ),
          ],
        ),
      ],
    );
  }

  /// En-tête FNE : encadré émetteur + QR Code + carte CI dans cercle orange
  static pw.Widget _buildEntete(
    FneConfig config,
    pw.MemoryImage logoCI,
    String qrData,
  ) {
    return pw.Row(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        // Encadré émetteur
        pw.Expanded(
          flex: 45,
          child: pw.Container(
            padding: const pw.EdgeInsets.all(8),
            decoration: pw.BoxDecoration(
              border: pw.Border.all(width: 1.5),
            ),
            child: pw.Column(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                pw.Text('DALAKOUN',
                    style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 11)),
                pw.Text('NCC : ${config.ncc}', style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Régime d\'imposition : ${config.regimeImposition}',
                    style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Centre des impôts : ${config.centreImpots}',
                    style: const pw.TextStyle(fontSize: 9)),
              ],
            ),
          ),
        ),
        pw.SizedBox(width: 10),
        // QR Code + Badge CI
        pw.Expanded(
          flex: 55,
          child: pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.end,
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              // QR Code
              pw.SizedBox(
                width: 80,
                height: 80,
                child: pw.BarcodeWidget(
                  barcode: pw.Barcode.qrCode(),
                  data: qrData,
                  width: 80,
                  height: 80,
                ),
              ),
              pw.SizedBox(width: 10),
              // Badge CI dans cercle orange + texte en dessous
              pw.Column(
                crossAxisAlignment: pw.CrossAxisAlignment.center,
                children: [
                  pw.Container(
                    width: 75,
                    height: 75,
                    decoration: pw.BoxDecoration(
                      border: pw.Border.all(color: PdfColors.orange, width: 2),
                      borderRadius: pw.BorderRadius.circular(37.5),
                    ),
                    alignment: pw.Alignment.center,
                    child: pw.ClipOval(
                      child: pw.SizedBox(
                        width: 55,
                        height: 55,
                        child: pw.Image(logoCI),
                      ),
                    ),
                  ),
                  pw.SizedBox(height: 4),
                  pw.Text(
                    'FACTURE NORMALISÉE\nÉLECTRONIQUE',
                    style: pw.TextStyle(
                      fontSize: 7,
                      fontWeight: pw.FontWeight.bold,
                      color: PdfColors.orange,
                    ),
                    textAlign: pw.TextAlign.center,
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  /// Informations émetteur et client
  static pw.Widget _buildInfosEmetteurClient(
    FneConfig config,
    FneClient client,
    String date,
    String? vendeur,
    String? modePaiement,
    String? adresseLivraison,
  ) {
    return pw.Row(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        // Infos émetteur
        pw.Expanded(
          flex: 55,
          child: pw.Padding(
            padding: const pw.EdgeInsets.only(right: 10),
            child: pw.Column(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                pw.Text('RCCM : ${config.rccm}', style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Références bancaires : ${config.refBancaires}',
                    style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Établissement : ${config.nomEtablissement}',
                    style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Adresse : ${config.adresseSiege}',
                    style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Nº Tel : ${config.telephone}',
                    style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Mail : ${config.emailEntreprise}',
                    style: const pw.TextStyle(fontSize: 9)),
                if (vendeur != null)
                  pw.Text('Nom du vendeur : $vendeur',
                      style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Nom de PDV : ${config.nomPdv}',
                    style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Date et heure : $date', style: const pw.TextStyle(fontSize: 9)),
                if (modePaiement != null)
                  pw.Text('Mode de paiement : $modePaiement',
                      style: const pw.TextStyle(fontSize: 9)),
                if (adresseLivraison != null)
                  pw.Text('ADRESSE : $adresseLivraison',
                      style: const pw.TextStyle(fontSize: 9)),
              ],
            ),
          ),
        ),
        // Bloc client
        pw.Expanded(
          flex: 45,
          child: pw.Container(
            padding: const pw.EdgeInsets.all(10),
            decoration: pw.BoxDecoration(
              border: pw.Border.all(color: PdfColors.grey400),
            ),
            child: pw.Column(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                pw.Text('Client',
                    style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 10)),
                pw.SizedBox(height: 3),
                pw.Text('Nom : ${client.nom}', style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Adresse : ${client.adresse}',
                    style: const pw.TextStyle(fontSize: 9)),
                pw.Text('NCC : ${client.ncc}', style: const pw.TextStyle(fontSize: 9)),
                pw.Text('Régime d\'imposition : ${client.regimeImposition}',
                    style: const pw.TextStyle(fontSize: 9)),
              ],
            ),
          ),
        ),
      ],
    );
  }

  /// Tableau des articles FNE
  static pw.Widget _buildTableauArticles(List<FneArticle> articles) {
    return pw.TableHelper.fromTextArray(
      headerStyle: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 8),
      cellStyle: const pw.TextStyle(fontSize: 8),
      headerDecoration: const pw.BoxDecoration(color: PdfColors.grey200),
      cellHeight: 22,
      cellAlignments: {
        0: pw.Alignment.center,
        1: pw.Alignment.centerLeft,
        2: pw.Alignment.centerRight,
        3: pw.Alignment.center,
        4: pw.Alignment.center,
        5: pw.Alignment.center,
        6: pw.Alignment.center,
        7: pw.Alignment.centerRight,
      },
      headers: ['Réf', 'Désignation', 'P.U HT', 'Qté', 'Unité', 'Taxes (%)', 'Rem. (%)', 'Montant HT'],
      data: articles
          .map((a) => [
                a.ref,
                a.designation,
                formaterMontant(a.puHt),
                a.qte.toStringAsFixed(0),
                a.unite,
                a.taxes,
                a.remise.toStringAsFixed(0),
                formaterMontant(a.montantHt),
              ])
          .toList(),
    );
  }

  /// Bloc des totaux
  static pw.Widget _buildTotaux(
    double totalHt,
    double totalTva,
    double totalTtc,
    double autresTaxes,
    double totalAPayer,
  ) {
    pw.Widget ligneTotaux(String label, double montant, {bool bold = false}) {
      return pw.Container(
        padding: const pw.EdgeInsets.symmetric(vertical: 2, horizontal: 8),
        decoration: pw.BoxDecoration(
          border: pw.Border.all(color: PdfColors.grey400, width: 0.5),
        ),
        child: pw.Row(
          children: [
            pw.Expanded(
              flex: 75,
              child: pw.Text(label,
                  textAlign: pw.TextAlign.right,
                  style: pw.TextStyle(
                    fontWeight: pw.FontWeight.bold,
                    fontSize: bold ? 10 : 9,
                  )),
            ),
            pw.Expanded(
              flex: 25,
              child: pw.Text(
                formaterMontant(montant),
                textAlign: pw.TextAlign.right,
                style: pw.TextStyle(
                  fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal,
                  fontSize: bold ? 10 : 9,
                ),
              ),
            ),
          ],
        ),
      );
    }

    return pw.Column(children: [
      ligneTotaux('TOTAL HT', totalHt),
      ligneTotaux('TVA', totalTva),
      ligneTotaux('TOTAL TTC', totalTtc),
      ligneTotaux('AUTRES TAXES', autresTaxes),
      ligneTotaux('TOTAL A PAYER', totalAPayer, bold: true),
    ]);
  }

  /// Résumé fiscal
  static pw.Widget _buildResumeFiscal(List<FneResumeFiscal> resume) {
    return pw.Column(
      crossAxisAlignment: pw.CrossAxisAlignment.start,
      children: [
        pw.Text('RESUME DE LA FACTURE',
            style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 10)),
        pw.SizedBox(height: 4),
        pw.TableHelper.fromTextArray(
          headerStyle: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 8),
          cellStyle: const pw.TextStyle(fontSize: 8),
          headerDecoration: const pw.BoxDecoration(color: PdfColors.grey200),
          cellHeight: 20,
          cellAlignments: {
            0: pw.Alignment.centerLeft,
            1: pw.Alignment.centerRight,
            2: pw.Alignment.center,
            3: pw.Alignment.centerRight,
          },
          headers: ['CATEGORIE', 'SOUS-TOTAL', 'TAUX (%)', 'TOTAL TAXES'],
          data: resume
              .map((r) => [
                    r.categorie,
                    formaterMontant(r.sousTotal),
                    r.taux,
                    formaterMontant(r.totalTaxes),
                  ])
              .toList(),
        ),
      ],
    );
  }

  /// Pied de page légal
  static pw.Widget _buildFooter(FneConfig config) {
    return pw.Container(
      padding: const pw.EdgeInsets.only(top: 8),
      decoration: const pw.BoxDecoration(
        border: pw.Border(top: pw.BorderSide(width: 0.5)),
      ),
      child: pw.Text(
        'DALAKOUN, SARL au Capital de ${config.capitalSocial}'
        '-RCCM N° ${config.rccm}, CC N°${config.ncc}, CNPS N°${config.cnps}\n'
        'Régime d\'imposition ${config.regimeImposition}\n'
        'Centre des impôts ${config.centreImpots}',
        style: const pw.TextStyle(fontSize: 7, color: PdfColors.grey700),
        textAlign: pw.TextAlign.center,
      ),
    );
  }
}
