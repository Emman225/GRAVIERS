import 'dart:convert';

import 'package:contained_tab_bar_view/contained_tab_bar_view.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/empty_user_widget.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/liste_paiement.dart';

import '../../components/bouton_retour.dart';
import '../../components/onglets.dart';
import '../../helper/constants.dart';
import 'components/facture_liste_screen.dart';

class FactureScreen extends StatefulWidget {
  static String routeName = "/facture_listing";

  const FactureScreen({super.key});

  @override
  State<FactureScreen> createState() => FactureScreenState();
}

class FactureScreenState extends State<FactureScreen> {
  List<UnPaiement> paiements = [];
  ListePaiement pai = ListePaiement();
  List<Widget> pagesPaiement = [];
  List<UnPaiement> paiementAttente = [];
  List<UnPaiement> paiementEffectuee = [];

  double _totalAttente = 0;

  chargerFacture() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "statut": [1,2],
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}liste-facture'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          pai = ListePaiement.fromJson(datas);
          if (pai.code == 200) {
            // Écran quitté pendant le chargement : la réponse revient sur un écran
            // détruit et le rafraîchissement échoue (voir devis_screen.dart).
            if (mounted) setState(() {
              paiements = pai.data ?? [];
              paiementAttente = paiements
                  .where((c) => (c.statut == 2))
                  .toList();
              paiementEffectuee = paiements
                  .where((c) => (c.statut == 1))
                  .toList();

              for (var p in paiementAttente) { _totalAttente += p.montant?.toDouble() ?? 0; }

              pagesPaiement = [
                FactureListeScreen(paiements: paiementAttente, total: _totalAttente),
                FactureListeScreen(paiements: paiementEffectuee, total: _totalAttente,),
              ];
            });
          } else {
            afficherErreur(pai.message ?? '');
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        afficherErreur(messageErreurTechnique(e));
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    pagesPaiement = [
      FactureListeScreen(paiements: paiementAttente, total: _totalAttente),
      FactureListeScreen(paiements: paiementEffectuee, total: _totalAttente),
    ];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (user.token != null && user.token != '') {
        chargerFacture();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Paiements en attente / effectués"),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: SafeArea(
        child: (user.token == null || user.token == "")
            ? const EmptyUserWidget()
            : Container(
                width: double.infinity,
                height: heightOfScreen(context),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: ContainedTabBarView(
                      tabBarProperties: ongletsSegmentes(),
                      tabs: const [
                        Text('En attente'),
                        Text('Effectués'),
                      ],
                      views: pagesPaiement,
                      onChange: (index) {
                        if (kDebugMode) {
                          print(index);
                        }
                      }
                  ),
                ),
              ),
      ),
    );
  }

}
