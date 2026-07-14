import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_apporteur/constants.dart';
import 'package:mon_gravier_com_apporteur/globale.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_filleule.dart';
import 'package:searchable_listview/searchable_listview.dart';

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
        retourHttp = await http
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
            setState(() {
              paiements = retourList.data ?? [];
            });
          } else {
            EasyLoading.showError(retourList.message ?? '');
          }
        } else {
          EasyLoading.showError("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        EasyLoading.showError(
            "Une erreur s'est produite veuillez reesayer plus tard");
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
            child: SearchableList<PaiementFilleule>(
              searchFieldEnabled: true,
              shrinkWrap: true,
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
              initialList: paiements,
              filter: (p0) {
                return paiements
                    .where((c) => (c.libelle.toString().contains(p0) ||
                    c.montantTotal.toString().contains(p0) ||
                    c.statut.toString().contains(p0)))
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
