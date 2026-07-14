import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/models/RetourVehicule.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../constants.dart';
import '../../globale.dart';
import '../../helper/constants.dart';
import 'package:http/http.dart' as http;

import 'edition_vehicule/edition_vehicule_screen.dart';

class VehiculeScreen extends StatefulWidget {
  static String routeName = "/vehicule";

  const VehiculeScreen({super.key});

  @override
  State<VehiculeScreen> createState() => _VehiculeScreenState();
}

class _VehiculeScreenState extends State<VehiculeScreen> {

  RetourVehicule retVehicule = RetourVehicule();
  List<Vehicules> vehicules = [];
  List<TypeVehicule> typeVehicules = [];

  chargerVehicule() async {
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
            .post(Uri.parse('${lienAPI()}liste-vehicule'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          setState(() {
            retVehicule = RetourVehicule.fromJson(datas);
            vehicules = retVehicule.data?.vehicules ?? [];
            typeVehicules = retVehicule.data?.types ?? [];
          });
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
      chargerVehicule();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Gestion des vehicules"),
        backgroundColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        automaticallyImplyLeading: false,
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xff03dac6),
        foregroundColor: Colors.black,
        onPressed: () async {
          await Get.toNamed(EditionVehiculeScreen.routeName,
              arguments: [Vehicules(), typeVehicules]);
          chargerVehicule();
        },
        icon: const Icon(Icons.add),
        label: const Text('Ajouter Vehicule'),
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
            child: SearchableList<Vehicules>(
              searchFieldEnabled: true,
              shrinkWrap: true,
              autoFocusOnSearch: false,
              sortWidget: const Icon(Icons.sort),
              sortPredicate: (a, b) {
                String mtna = a.nom ?? '';
                String mtnb = b.nom ?? '';
                return mtna.compareTo(mtnb);
              },
              physics: const BouncingScrollPhysics(),
              builder: (vehicules, index, c) => GestureDetector(
                onTap: () => Get.toNamed(EditionVehiculeScreen.routeName, arguments: [c, typeVehicules]),
                child: Padding(
                  padding: const EdgeInsets.all(8.0),
                  child: Container(
                    height: 150,
                    decoration: BoxDecoration(
                      color: Colors.grey[200],
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 15),
                      child: Row(
                        mainAxisAlignment: mainSpaceBet,
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
                                    image: AssetImage("assets/images/truck.gif"),
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
                                Text('Matricule: ${c.immatriculation}',
                                  style: const TextStyle(
                                    color: Colors.red,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(c.nom.toString(),
                                  style: const TextStyle(
                                    color: Colors.blue,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  '${c.marque} - ${c.modele}',
                                  style: const TextStyle(
                                    color: Colors.green,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  'Disponible: ${c.disponible == true ? 'OUI' : 'NON'}',
                                  style: const TextStyle(
                                    color: kPrimaryColor,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Text(
                                  'Type: ${c.typeVehicule}',
                                  style: const TextStyle(
                                    color: Colors.black,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const Icon(Icons.arrow_forward_ios),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              emptyWidget: const Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    Icons.error,
                    color: Colors.red,
                  ),
                  Text('Aucune donnée'),
                ],
              ),
              initialList: vehicules,
              filter: (p0) {
                return vehicules
                    .where((c) => (c.nom.toString().contains(p0) ||
                    c.description.toString().contains(p0) ||
                    c.marque.toString().contains(p0) ||
                    c.modele.toString().contains(p0) ||
                    c.capacite.toString().contains(p0)))
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
