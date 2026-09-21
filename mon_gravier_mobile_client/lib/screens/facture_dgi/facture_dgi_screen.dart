import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:searchable_listview/searchable_listview.dart';

import '../../components/bouton_retour.dart';
import '../../components/carte_operation.dart';
import '../../components/empty_user_widget.dart';
import '../../components/etat_vide.dart';
import '../../constants.dart';
import '../../globale.dart';
import '../../impression/impression_facture_dgi_pdf.dart';
import '../../models/facture_dgi.dart';

/// « MES FACTURES DGI » (lot 95, 16/09/2026) : les factures certifiées par la
/// DGI (ventes, locations, transports) et les avoirs, tels que le site les
/// tient — mêmes lignes que « Factures DGI » de Mon compte sur le web. Toucher
/// une ligne ouvre le PDF du site, à voir, imprimer ou partager.
class FactureDgiScreen extends StatefulWidget {
  static String routeName = "/FacturesDgi";

  const FactureDgiScreen({super.key});

  @override
  State<FactureDgiScreen> createState() => _FactureDgiScreenState();
}

class _FactureDgiScreenState extends State<FactureDgiScreen> {
  List<FactureDgi> factures = [];
  bool _charge = false;
  String motif = '';

  Future<void> charger() async {
    if (!await verifierConnexion()) {
      motif = "Veuillez vérifier votre connexion internet";
    } else {
      try {
        final reponse = await http
            .post(Uri.parse('${lienAPI()}liste-factures-dgi'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode({
                  "access": user.token.toString(),
                  "type": user.type.toString(),
                }))
            .timeout(const Duration(minutes: 1));
        final datas = jsonDecode(reponse.body);
        if (reponse.statusCode == 200 && datas is Map && datas['code'] == 200) {
          factures = ((datas['data'] as List?) ?? [])
              .map((e) => FactureDgi.fromJson(Map<String, dynamic>.from(e)))
              .toList();
          motif = '';
        } else {
          motif = (datas is Map && datas['message'] != null)
              ? datas['message'].toString()
              : 'réponse ${reponse.statusCode}';
        }
      } catch (e) {
        motif = messageErreurTechnique(e);
        if (kDebugMode) {
          print('Factures DGI : $e');
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
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => charger());
  }

  @override
  Widget build(BuildContext context) {
    final bool connecte = user.token != null && user.token != "";
    return Scaffold(
      appBar: AppBar(
        title: const Text("Mes factures DGI"),
        leading: BoutonRetour(onTap: () => Get.back()),
      ),
      body: !connecte
          ? const EmptyUserWidget()
          : !_charge
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: charger,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(kSpaceLg, kSpaceSm, kSpaceLg, kSpaceSm),
                    child: SearchableList<FactureDgi>(
                      searchFieldEnabled: true,
                      shrinkWrap: true,
                      autoFocusOnSearch: false,
                      physics: const AlwaysScrollableScrollPhysics(),
                      emptyWidget: EtatVide(
                        compact: true,
                        icone: Icons.verified_outlined,
                        titre: motif.isEmpty ? "Aucune facture DGI" : "Factures indisponibles",
                        message: motif.isEmpty
                            ? "Vos factures certifiées par la DGI apparaîtront ici, comme sur le site."
                            : motif,
                      ),
                      builder: (liste, index, f) {
                        final bool avoir = f.estUnAvoir;
                        // Textes courts : la carte coupait « Facture de vente n° … » et
                        // « Certifiée DGI » (retour du 16/09/2026).
                        final String etat = avoir
                            ? "Avoir"
                            : (f.certifiee ? "Certifiée" : "En attente");
                        final String mention = f.certifiee && (f.reference ?? '').isNotEmpty
                            ? "Réf. ${f.reference}"
                            : [
                                if ((f.numAffaire ?? '').isNotEmpty) "${_affaire(f.service)} n° ${f.numAffaire}",
                                if (avoir && (f.origineNumero ?? '').isNotEmpty) "sur la facture n° ${f.origineNumero}",
                              ].join(" — ");
                        return Padding(
                          padding: const EdgeInsets.only(bottom: kSpaceMd),
                          child: CarteOperation(
                            icone: avoir ? Icons.undo_outlined : Icons.receipt_long_outlined,
                            numero: "${avoir ? 'Avoir' : 'Facture'} n° ${f.numero ?? ''}",
                            montant: formaterMontant((f.montant ?? 0).abs()),
                            mention: mention,
                            date: f.date,
                            statut: etat,
                            couleurStatut: avoir
                                ? kWarningColor
                                : (f.certifiee ? kSuccessColor : kWarningColor),
                            fondStatut: avoir
                                ? kWarningSoftColor
                                : (f.certifiee ? kSuccessSoftColor : kWarningSoftColor),
                            onTap: () => Get.toNamed(ImpressionFactureDgiPdf.routeName,
                                arguments: [f.id ?? 0, f.numero ?? '']),
                          ),
                        );
                      },
                      initialList: factures,
                      filter: (q) => factures
                          .where((f) =>
                              (f.numero ?? '').contains(q) ||
                              (f.reference ?? '').toLowerCase().contains(q.toLowerCase()) ||
                              (f.numAffaire ?? '').contains(q) ||
                              (f.date ?? '').contains(q) ||
                              (f.montant ?? 0).toString().contains(q))
                          .toList(),
                      inputDecoration: const InputDecoration(
                        hintText: "Rechercher une facture...",
                        floatingLabelBehavior: FloatingLabelBehavior.never,
                        prefixIcon: Icon(Icons.search, size: 20),
                      ),
                    ),
                  ),
                ),
    );
  }

  String _affaire(String? service) {
    switch ((service ?? '').toUpperCase()) {
      case 'LOCATION':
        return 'Location';
      case 'LIVRAISON':
        return 'Livraison';
      default:
        return 'Commande';
    }
  }
}
