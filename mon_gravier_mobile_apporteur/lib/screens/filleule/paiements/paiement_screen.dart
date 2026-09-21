import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_apporteur/constants.dart';
import 'package:mon_gravier_com_apporteur/globale.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_filleule.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../components/etat_vide.dart';
import '../../../../components/bouton_retour.dart';
import '../../../../helper/constants.dart';
import '../../../models/retour_liste_paiement_filleule.dart';

class PaiementFilleuleScreen extends StatefulWidget {
  static String routeName = 'paiementFilleuleListe';
  const PaiementFilleuleScreen({super.key});

  @override
  State<PaiementFilleuleScreen> createState() => PaiementFilleuleScreenState();
}

class PaiementFilleuleScreenState extends State<PaiementFilleuleScreen> {

  Filleule filleule = Filleule();
  RetourListePaiementFilleule retourList = RetourListePaiementFilleule();
  List<PaiementFilleule> paiements = [];

  chargerPaiement() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "filleule_id": filleule.id,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        final http.Response retourHttp = await http
            .post(Uri.parse('${lienAPI()}liste-paiement-filleule'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        if (kDebugMode) {
          print('liste-paiement-filleule status: ${retourHttp.statusCode}');
        }
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          if (kDebugMode) {
            print(datas);
          }
          retourList = RetourListePaiementFilleule.fromJson(datas);
          if (retourList.code == 200) {
            if (mounted) setState(() {
              paiements = retourList.data ?? [];
            });
          } else {
            if (mounted) afficherErreur(retourList.message ?? '');
          }
        } else {
          if (mounted) afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        if (mounted) afficherErreur(
            "Une erreur s'est produite veuillez reesayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      if (mounted) afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    filleule = Get.arguments;
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (user.token != null && user.token != '') {
        chargerPaiement();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text("Liste des paiements de ${filleule.nom} ${filleule.prenom}"),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: SafeArea(
        child: Container(
          width: double.infinity,
          height: heightOfScreen(context),
          child: Padding(
            padding: const EdgeInsets.all(15.0),
            child: SearchableList<PaiementFilleule>(
              searchFieldEnabled: true,
              shrinkWrap: true,
              // Le clavier ne s'ouvre plus tout seul : il masquait la moitie
              // de la liste des l'arrivee.
              autoFocusOnSearch: false,
              sortWidget: const Icon(Icons.sort),
              sortPredicate: (a, b) {
                int mtna = a.id ?? 0;
                int mtnb = b.id ?? 0;
                return mtna.compareTo(mtnb);
              },
              physics: const BouncingScrollPhysics(),
              builder: (list, index, c) {
                final bool estTerminee = c.statut == 1;
                return Padding(
                padding: const EdgeInsets.all(8.0),
                child: Container(
                  height: 150,
                  decoration: BoxDecoration(
                    color: estTerminee ? Colors.green[50] : Colors.red[100],
                    borderRadius: BorderRadius.circular(10),
                    border: estTerminee
                        ? Border.all(color: Colors.green.shade400, width: 1.5)
                        : null,
                  ),
                  child: Stack(
                    children: [
                      Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: Row(
                      children: [
                        const SizedBox(
                          width: 10,
                        ),
                        Container(
                            width: 80,
                            height: 80,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(30),
                              image: const DecorationImage(
                                  image: AssetImage("assets/images/paiement.gif"),
                                  fit: BoxFit.cover,
                                  opacity: 0.6),
                            ),
                            child: Container()),
                        const SizedBox(
                          width: 10,
                        ),
                        Flexible(
                          child:  Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text('${c.libelle}'),
                              Text(formaterMontant(c.montantTotal ?? 0),
                                style: const TextStyle(
                                  color: Colors.blue,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              Text(
                                'Payé: ${c.statut == 1 ? 'OUI' : 'NON'}',
                                style: const TextStyle(
                                  color: kPrimaryColor,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                      if (estTerminee)
                        Positioned(
                          top: 8,
                          right: 8,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: Colors.green,
                              borderRadius: BorderRadius.circular(12),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.green.withOpacity(0.3),
                                  blurRadius: 4,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.check_circle, color: Colors.white, size: 12),
                                SizedBox(width: 4),
                                Text(
                                  'Terminé',
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              );
              },
              emptyWidget: const EtatVide(
              compact: true,
              icone: Icons.payments_outlined,
              titre: "Aucun paiement",
              message:
                  "Les paiements de ce filleul apparaîtront dans cette liste.",
            ),
              initialList: paiements,
              filter: (p0) {
                return paiements
                    .where((c) => (c.libelle.toString().contains(p0) ||
                    c.montantTotal.toString().contains(p0) ||
                    c.statut.toString().contains(p0)))
                    .toList();
              },
              inputDecoration: const InputDecoration(
              hintText: "Rechercher...",
              floatingLabelBehavior: FloatingLabelBehavior.never,
              prefixIcon: Icon(Icons.search, size: 20),
            ),
            ),
          ),
        ),
      ),
    );
  }
}
