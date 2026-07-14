import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/constants.dart';

import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/devis.dart';
import 'package:mon_gravier_com/screens/details_devis/details_devis_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../globale.dart';

class DevisListeWidget extends StatefulWidget {
  const DevisListeWidget({super.key, required this.devis});

  final List<DataDevis> devis;

  @override
  State<DevisListeWidget> createState() => _DevisListeWidgetState();
}

class _DevisListeWidgetState extends State<DevisListeWidget> {
  @override
  Widget build(BuildContext context) {
    return
      Scaffold(
      body:
      Container(
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
            builder: (devis, index, c) =>
                GestureDetector(
                  onTap: () async {
                    await Get.toNamed(DetailsDevisScreen.routeName, arguments: c);
                    setState(() {});
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
                                    opacity: 0.6
                                ),
                              ),
                              child: Container()),
                          const SizedBox(
                            width: 10,
                          ),
                          Flexible(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
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
                                  formaterMontant(c.montant?.toDouble() ?? 0),
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
            initialList: widget.devis,
            filter: (p0) {
              return widget.devis
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
    );
  }
}
