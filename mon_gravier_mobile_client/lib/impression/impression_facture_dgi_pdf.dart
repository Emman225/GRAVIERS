import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:pdf/pdf.dart';
import 'package:printing/printing.dart';

import '../components/bouton_retour.dart';
import '../components/etat_vide.dart';
import '../globale.dart';

/// LA FACTURE DGI DU SITE SUR LE TÉLÉPHONE (lot 95, 16/09/2026) : le PDF est
/// celui du site (même document que sur le web et que le courriel), relayé par
/// l'API. On le voit, on l'imprime ou on le partage (= le télécharger).
/// Arguments : [idFacture, numero].
class ImpressionFactureDgiPdf extends StatefulWidget {
  static String routeName = "/FactureDgiPdf";

  const ImpressionFactureDgiPdf({super.key});

  @override
  State<ImpressionFactureDgiPdf> createState() => _ImpressionFactureDgiPdfState();
}

class _ImpressionFactureDgiPdfState extends State<ImpressionFactureDgiPdf> {
  int idFacture = 0;
  String numero = '';
  Uint8List? pdf;
  String motif = '';
  bool _charge = false;

  Future<void> charger() async {
    if (!await verifierConnexion()) {
      motif = "Veuillez vérifier votre connexion internet";
    } else {
      try {
        final reponse = await http
            .post(Uri.parse('${lienAPI()}facture-dgi-pdf'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode({
                  "access": user.token.toString(),
                  "type": user.type.toString(),
                  "idFacture": idFacture,
                }))
            .timeout(const Duration(seconds: 60));
        final type = reponse.headers['content-type'] ?? '';
        if (reponse.statusCode == 200 &&
            type.contains('pdf') &&
            reponse.bodyBytes.length > 4 &&
            String.fromCharCodes(reponse.bodyBytes.sublist(0, 4)) == '%PDF') {
          pdf = reponse.bodyBytes;
        } else {
          try {
            final json = jsonDecode(reponse.body);
            motif = (json is Map && json['message'] != null)
                ? json['message'].toString()
                : 'réponse ${reponse.statusCode}';
          } catch (_) {
            motif = 'réponse ${reponse.statusCode}';
          }
        }
      } catch (e) {
        motif = messageErreurTechnique(e);
        if (kDebugMode) {
          print('Facture DGI : $e');
        }
      }
    }
    if (mounted) {
      setState(() {
        _charge = true;
      });
    }
  }

  @override
  void initState() {
    final data = Get.arguments;
    if (data is List && data.isNotEmpty) {
      idFacture = int.tryParse(data[0].toString()) ?? 0;
      numero = data.length > 1 ? data[1].toString() : '';
    }
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => charger());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(numero.isEmpty ? "Facture DGI" : "Facture DGI n° $numero"),
        elevation: 0,
        leading: BoutonRetour(onTap: () => Get.back()),
      ),
      body: !_charge
          ? const Center(child: CircularProgressIndicator())
          : pdf == null
              ? EtatVide(
                  icone: Icons.cloud_off_outlined,
                  titre: "Facture indisponible",
                  message: "Le site n'a pas fourni le document : $motif",
                  libelleAction: "Réessayer",
                  action: () {
                    setState(() {
                      _charge = false;
                    });
                    charger();
                  },
                )
              : PdfPreview(
                  canChangeOrientation: false,
                  canChangePageFormat: false,
                  canDebug: false,
                  allowSharing: true,
                  allowPrinting: true,
                  initialPageFormat: PdfPageFormat.a4,
                  pdfFileName: "Facture_${numero.isEmpty ? idFacture : numero}.pdf",
                  build: (context) async => pdf!,
                ),
    );
  }
}
