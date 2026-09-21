import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_demande_paiement.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../components/etat_vide.dart';
import '../../../components/bouton_retour.dart';
import '../../constants.dart';
import '../../globale.dart';
import '../../helper/constants.dart';
import 'package:http/http.dart' as http;

import 'edit_demande_retrait_screen.dart';

class ListeDemandeRetraitScreen extends StatefulWidget {
  static String routeName = "/listeDemandeRetrait";

  const ListeDemandeRetraitScreen({super.key});

  @override
  State<ListeDemandeRetraitScreen> createState() => _ListeDemandeRetraitScreenState();
}

class _ListeDemandeRetraitScreenState extends State<ListeDemandeRetraitScreen> {

  RetourListeDemandePaiement retDemande = RetourListeDemandePaiement();
  List<DemandePaiement> demandes = [];

  chargerDemandePaiement() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        final http.Response retourHttp = await http
            .post(Uri.parse('${lienAPI()}lister-demande-paiement'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        if (kDebugMode) {
          print('lister-demande-paiement status: ${retourHttp.statusCode}');
        }
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          if (mounted) setState(() {
            retDemande = RetourListeDemandePaiement.fromJson(datas);
            demandes = retDemande.data ?? [];
          });
        } else {
          if (mounted) afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        user.code = 500;
        user.message = "Une erreur s'est produite veuillez reesayer plus tard";
        // Ce bloc de secours n.affichait RIEN : l.ecran restait muet en cas de
        // coupure reseau ou de reponse illisible.
        if (mounted) afficherErreur("Impossible de contacter le serveur. Verifiez votre connexion et reessayez.");
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
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerDemandePaiement();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Gestion des demandes de paiement"),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xff03dac6),
        foregroundColor: Colors.black,
        onPressed: () async {
          await Get.toNamed(EditDemandeRetraitScreen.routeName,
              arguments: DemandePaiement());
          chargerDemandePaiement();
        },
        icon: const Icon(Icons.add),
        label: const Text('Nouvelle demande'),
      ),
      body: SafeArea(
        child: Container(
          width: double.infinity,
          height: heightOfScreen(context),
          child: Padding(
            padding: const EdgeInsets.all(15.0),
            child: SearchableList<DemandePaiement>(
              searchFieldEnabled: true,
              shrinkWrap: true,
              // Le clavier ne s'ouvre plus tout seul : il masquait la moitie
              // de la liste des l'arrivee.
              autoFocusOnSearch: false,
              sortWidget: const Icon(Icons.sort),
              sortPredicate: (a, b) {
                String mtna = a.id.toString() ?? '';
                String mtnb = b.id.toString() ?? '';
                return mtna.compareTo(mtnb);
              },
              physics: const BouncingScrollPhysics(),
              builder: (list, index, c) {
                final bool estTerminee = c.paye == true;
                return GestureDetector(
                onTap: () async {
                  if (c.paye == false) {
                    await Get.toNamed(EditDemandeRetraitScreen.routeName, arguments: c);
                    chargerDemandePaiement();
                  }else{
                    if (mounted) afficherErreur("Vous avez déjà été payé vous ne pouvez plus modifier cette ligne");
                  }
                },
                child: Padding(
                  padding: const EdgeInsets.all(8.0),
                  child: Container(
                    height: 150,
                    decoration: BoxDecoration(
                      color: estTerminee ? Colors.green[50] : Colors.grey[200],
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
                                // Même règle que sur l'écran d'accueil : « Compte » n'a de
                                // sens que pour une demande de retrait, où l'apporteur
                                // indique où il veut être payé. Un règlement enregistré
                                // par un gestionnaire n'en a pas — la ligne affichait
                                // « Compte: » suivi de rien. On la masque, et on
                                // l'intitule « Référence » quand la valeur vient d'un
                                // règlement déjà payé.
                                if ((c.numero_compte ?? '').trim().isNotEmpty &&
                                    (c.numero_compte ?? '').trim() != '-')
                                  Text(
                                    '${c.paye == true ? 'Référence' : 'Compte'}: ${c.numero_compte}',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                Text('Montant : ${formaterMontant(c.montant?.toDouble() ?? 0)}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text("Moyen de paiement : ${c.modePaiement}",
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  // Point 20 : une demande validée est « à payer »
                                  // jusqu'à ce que l'entreprise déclare le versement effectué.
                                  c.paye != true
                                      ? 'Payé : Non'
                                      : (c.etatReglement == 'EFFECTUEE'
                                          ? 'Paiement effectué'
                                          : (c.etatReglement == null ? 'Payé : Oui' : 'Validé — paiement en cours')),
                                  style: const TextStyle(
                                    color: kPrimaryColor,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  'Date : ${c.date_demande}',
                                  style: const TextStyle(
                                    color: Colors.black,
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
                ),
              );
              },
              emptyWidget: const EtatVide(
              compact: true,
              icone: Icons.payments_outlined,
              titre: "Aucune demande de retrait",
              message:
                  "Vos demandes de retrait apparaîtront ici une fois enregistrées.",
            ),
              initialList: demandes,
              filter: (p0) {
                return demandes
                    .where((c) => (c.modePaiement.toString().toUpperCase().contains(p0.toUpperCase()) ||
                    c.paye.toString().toUpperCase().contains(p0.toUpperCase()) ||
                    c.numero_compte.toString().toUpperCase().contains(p0.toUpperCase()) ||
                    c.montant.toString().toUpperCase().contains(p0.toUpperCase()) ||
                    c.date_demande.toString().toUpperCase().contains(p0.toUpperCase())))
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
