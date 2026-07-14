import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/constants.dart';

import 'package:mon_gravier_com/helper/constants.dart';
import 'package:searchable_listview/searchable_listview.dart';
import 'package:select_searchable_list/select_searchable_list.dart';
import 'package:http/http.dart' as http;

import '../../../globale.dart';
import '../../../impression/impression_recu_paiement_pdf.dart';
import '../../../models/ConfigModel.dart';
import '../../../models/liste_paiement.dart';

class FactureListeScreen extends StatefulWidget {
  const FactureListeScreen({super.key, required this.paiements, required this.total});
  final List<UnPaiement> paiements;
  final double total;
  @override
  State<FactureListeScreen> createState() => _FactureListeScreenState();
}

class _FactureListeScreenState extends State<FactureListeScreen> {

  TextEditingController modePaiementController = TextEditingController();
  List<ModePaiements> _listModePaiement = [];
  int _mode = 0;
  double _total = 0;
  int leStatut = 0;
  List<int> ids = [];

  @override
  void initState() {
    _listModePaiement = user.configs?.modePaiements ?? [];
    _total = 0;
    super.initState();
  }

  @override
  void dispose() {
    modePaiementController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
      floatingActionButton: (user.token != null && user.token != ""&& ids.isNotEmpty)
          ? FloatingActionButton.extended(
        backgroundColor: greenColor,
        foregroundColor: Colors.black,
        onPressed: () async {
          //Passer au paiement
          await _choixModePaiementForm();
        },
        icon: const Icon(Icons.wallet_rounded, color: whiteColor),
        label: Text('Payer factures. Tot: ${formaterMontant(_total)}', style: white16BoldTextStyle,),
      )
          : null,
      body: Container(
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
          child: SearchableList<UnPaiement>(
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
            builder: (paiements, index, p) => GestureDetector(
              onTap: () async {
                if (p.statut == 1) {
                  //Paiement effectué on imprime le reçu de paiement
                  Get.toNamed(ImpressionRecuPaiementPdf.routeName,
                      arguments: [2, "", p.id]);
                }else if (p.statut == 2) {
                  //Paiement en attente
                  setState(() {
                    if (!ids.contains(p.id)) {
                      ids.add(p.id ?? 0);
                      _total += p.montant ?? 0;
                    }else{
                      ids.remove(p.id);
                      _total -= p.montant ?? 0;
                    }
                  });
                }
              },
              child: Padding(
                padding: const EdgeInsets.all(8.0),
                child: Container(
                  height: 160,
                  decoration: BoxDecoration(
                    color: ids.contains(p.id) ? Colors.green[100] : Colors.grey[200],
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisAlignment: mainSpaceBet,
                    children: [
                      const SizedBox(
                        width: 10,
                      ),
                      Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(30),
                            image: const DecorationImage(
                                image: AssetImage(
                                    "assets/images/attente.png"),
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
                              '# ${p.numero}',
                              style: const TextStyle(
                                color: Colors.red,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              "Total: ${formaterMontant(p.montant?.toDouble() ?? 0)}",
                              style: const TextStyle(
                                color: Colors.blue,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              'Paiement de facture n° ${p.numero} pour ${p.service} effectué',
                              style: const TextStyle(
                                color: Colors.black,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            Text(
                              '${p.datePaiement}',
                              style: const TextStyle(
                                color: Colors.black,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.navigate_next_sharp, color: greyColor, size: 20),
                    ],
                  ),
                ),
              ),
            ),
            initialList: widget.paiements,
            filter: (p0) {
              return widget.paiements
                  .where((c) =>
              (c.datePaiement.toString().contains(p0) ||
                  c.numero.toString().contains(p0) ||
                  c.montant.toString().contains(p0) ||
                  c.service.toString().contains(p0)))
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
      bottomNavigationBar: (user.token != null && user.token != "")
          ? Padding(
        padding: const EdgeInsets.all(8.0),
        child: Text(
          "Total restant: ${formaterMontant(widget.total)}",
          textAlign: TextAlign.center,
          style: green18MediumTextStyle,
        ),
      )
          : null,
    );
  }

  _choixModePaiementForm() async {
    return showDialog(
        barrierDismissible: true,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            title: const Text("Réglement de facture"),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            elevation: 5.0,
            content: SizedBox(
              height: 150,
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(8.0),
                    child: DropDownTextField(
                      textEditingController: modePaiementController,
                      title: 'Mode de paiement',
                      hint: 'Choisir un mode de paiement',
                      options: {
                        for (var p in _listModePaiement)
                          p.id ?? 0: p.libelle.toString()
                      },
                      multiple: false,
                      textInputAction: TextInputAction.next,
                      onChanged: (selectedIds) {
                        setState(() {
                          _mode = selectedIds?.first ?? 0;
                        });
                      },
                    ),
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () {
                      Get.back();
                      obtenirLienPaiement();
                    },
                    child: const Text("Passer au paiement"),
                  ),
                ],
              ),
            ),
          );
        });
  }

  obtenirLienPaiement() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "ids": ids,
        "modePaiement": _mode,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}obtenir-lien-paiement'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            lancerUrl(datas['message']);
          } else {
            EasyLoading.showError(datas['message']);
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
}
