import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_demande_paiement.dart';
import 'package:searchable_listview/searchable_listview.dart';

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
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}lister-demande-paiement'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        if (kDebugMode) {
          print('lister-demande-paiement status: ${retourHttp.statusCode}');
        }
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          setState(() {
            retDemande = RetourListeDemandePaiement.fromJson(datas);
            demandes = retDemande.data ?? [];
          });
        } else {
          EasyLoading.showError("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        user.code = 500;
        user.message = "Une erreur s'est produite veuillez reesayer plus tard";
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
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
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.all(8.0),
          child: ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(
              shape: const CircleBorder(),
              padding: EdgeInsets.zero,
              elevation: 0,
              backgroundColor: Colors.white,
            ),
            child: const Icon(
              Icons.arrow_back_ios_new,
              color: Colors.black,
              size: 20,
            ),
          ),
        ),
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
          decoration: const BoxDecoration(
            image: DecorationImage(
              image: AssetImage("assets/images/bg.jpg"),
              fit: BoxFit.cover,
              opacity: 0.2,
            ),
          ),
          child: Padding(
            padding: const EdgeInsets.all(15.0),
            child: SearchableList<DemandePaiement>(
              searchFieldEnabled: true,
              shrinkWrap: true,
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
                    EasyLoading.showError("Vous avez déjà été payé vous ne pouvez plus modifier cette ligne");
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
                                Text('Compte: ${c.numero_compte.toString()}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text('Montant: ${formaterMontant(c.montant?.toDouble() ?? 0)}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text("Moyen paiement: ${c.modePaiement}",
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  'Payé: ${c.paye == true ? 'OUI' : 'NON'}',
                                  style: const TextStyle(
                                    color: kPrimaryColor,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  'Date: ${c.date_demande}',
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
              inputDecoration: InputDecoration(
                labelText: "Recherchez...",
                fillColor: Colors.white,
                focusedBorder: OutlineInputBorder(
                  borderSide: const BorderSide(
                    color: kPrimaryColor,
                    width: 1.0,
                  ),
                  borderRadius: BorderRadius.circular(10.0),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
