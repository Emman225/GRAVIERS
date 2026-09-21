import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/models/retour_liste_demande_paiement.dart';
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
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          retDemande = RetourListeDemandePaiement.fromJson(datas);
          // Le code metier renvoye par l'API n'etait PAS controle : une session
          // expiree ou un refus serveur (reponse HTTP 200 mais code != 200)
          // laissait simplement une liste vide, sans aucune explication.
          if (retDemande.code == 200) {
            if (mounted) setState(() {
              demandes = retDemande.data ?? [];
            });
          } else {
            if (mounted) setState(() {
              demandes = [];
            });
            if (mounted) afficherErreur(retDemande.message ??
                "Impossible de charger vos demandes de paiement.");
          }
        } else {
          // Sans cette branche, une reponse serveur en erreur ne produisait
          // AUCUNE reaction a l'ecran.
          if (mounted) afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez reessayer.");
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
              autoFocusOnSearch: false,
              sortWidget: const Icon(Icons.sort),
              sortPredicate: (a, b) {
                String mtna = a.id.toString() ?? '';
                String mtnb = b.id.toString() ?? '';
                return mtna.compareTo(mtnb);
              },
              physics: const BouncingScrollPhysics(),
              builder: (demandes, index, c) => GestureDetector(
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
                      color: c.paye == true ? Colors.green[200] : Colors.grey[200],
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Padding(
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
                                // Un règlement saisi en agence n'a pas de numéro de
                                // compte : la ligne afficherait « Compte: » suivi de
                                // rien. On la masque, et on l'intitule « Référence »
                                // quand la valeur vient d'un versement déjà payé.
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
                                      ? 'Payé: NON'
                                      : (c.etatReglement == 'EFFECTUEE'
                                          ? 'Paiement effectué'
                                          : (c.etatReglement == null ? 'Payé: OUI' : 'Validé — paiement en cours')),
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
                  ),
                ),
              ),
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
