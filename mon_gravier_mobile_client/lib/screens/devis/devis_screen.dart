import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/empty_user_widget.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/devis.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../constants.dart';
import '../../helper/constants.dart';
import '../details_devis/details_devis_screen.dart';

class DevisScreen extends StatefulWidget {
  static String routeName = "/devis";

  const DevisScreen({super.key});

  @override
  State<DevisScreen> createState() => DevisScreenState();
}

class DevisScreenState extends State<DevisScreen> {
  List<DataDevis> devis = [];
  RetourListeDevis retourDevis = RetourListeDevis();

  chargerDevis() async {
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
            .post(Uri.parse('${lienAPI()}liste-devis'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          retourDevis = RetourListeDevis.fromJson(datas);
          if (retourDevis.code == 200) {
            setState(() {
              devis = retourDevis.data ?? [];
            });
          } else {
            EasyLoading.showError(retourDevis.message ?? '');
          }
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
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (user.token != null && user.token != '') {
        chargerDevis();
      }
    });
  }

  List<Widget> pages = [];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Mes devis enregistré"),
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
        child: (user.token == null || user.token == "")
            ? const EmptyUserWidget()
            : Container(
                width: double.infinity,
                height: heightOfScreen(context),
                decoration: const BoxDecoration(
                  image: DecorationImage(
                    image: AssetImage("assets/images/bg.jpg"),
                    fit: BoxFit.cover,
                    opacity: 0.1,
                  ),
                ),
                child: Container(
                  width: double.infinity,
                  height: heightOfScreen(context),
                  decoration: const BoxDecoration(
                    image: DecorationImage(
                      image: AssetImage("assets/images/bg.jpg"),
                      fit: BoxFit.cover,
                      opacity: 0.1,
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(15.0),
                    child: SearchableList<DataDevis>(
                      searchFieldEnabled: true,
                      shrinkWrap: true,
                      autoFocusOnSearch: false,
                      sortWidget: const Icon(Icons.sort),
                      sortPredicate: (a, b) {
                        double mtna = a.montant ?? 0;
                        double mtnb = b.montant ?? 0;
                        return mtna.compareTo(mtnb);
                      },
                      physics: const BouncingScrollPhysics(),
                      builder: (devis, index, c) => GestureDetector(
                        onTap: () async {
                          await Get.toNamed(DetailsDevisScreen.routeName,
                              arguments: c);
                          chargerDevis();
                        },
                        child: Padding(
                          padding: const EdgeInsets.all(8.0),
                          child: Container(
                            height: 120,
                            decoration: BoxDecoration(
                              color: Colors.grey[200],
                              borderRadius: BorderRadius.circular(10),
                            ),
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
                                          image: AssetImage(
                                              "assets/images/devis.gif"),
                                          fit: BoxFit.cover,
                                          opacity: 0.6),
                                    ),
                                    child: Container()),
                                const SizedBox(
                                  width: 10,
                                ),
                                Flexible(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Text(
                                        '# ${c.numero}',
                                        style: const TextStyle(
                                          color: Colors.red,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      Text(
                                        formaterMontant(
                                            c.montant?.toDouble() ?? 0),
                                        style: const TextStyle(
                                          color: Colors.blue,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      Text(
                                        '${c.libelle}',
                                        style: const TextStyle(
                                          color: Colors.black,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      Text(
                                        '${c.dateDevis}',
                                        style: const TextStyle(
                                          color: Colors.black,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const Icon(Icons.navigate_next_sharp, color: blackColor, size: 20),
                              ],
                            ),
                          ),
                        ),
                      ),
                      initialList: devis,
                      filter: (p0) {
                        return devis
                            .where((c) =>
                                (c.dateDevis.toString().contains(p0) ||
                                    c.libelle.toString().contains(p0) ||
                                    c.montant.toString().contains(p0)))
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
      ),
    );
  }
}
