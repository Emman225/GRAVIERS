import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/constants.dart';

import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/retour_livraison.dart';
import 'package:mon_gravier_com/screens/details_livraison/details_livraison_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../globale.dart';

class LivraisonListeScreen extends StatelessWidget {
  const LivraisonListeScreen({super.key, required this.livraisons});

  final List<UneLivraison> livraisons;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
          child: SearchableList<UneLivraison>(
            searchFieldEnabled: true,
            shrinkWrap: true,
            autoFocusOnSearch: false,
            sortWidget: const Icon(Icons.sort),
            sortPredicate: (a, b) {
              String mtna = a.dateLivraison ?? '';
              String mtnb = b.dateLivraison ?? '';
              return mtna.compareTo(mtnb);
            },
            physics: const BouncingScrollPhysics(),
            builder: (livraisons, index, c) => GestureDetector(
              onTap: () => Get.toNamed(DetailsLivraisonScreen.routeName, arguments: [c, (c.detailCommandeId! > 0) ? 1 : 2, c.qte]),
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: Container(
                  height: 200,
                  decoration: BoxDecoration(
                    color: Colors.grey[200],
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 15),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        if(c.clientId == c.livreurId) ...[
                          Text('# ${c.code_enlevement}',
                            style: const TextStyle(
                              color: Colors.red,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ]else ...[
                          Text('# ${c.numero}',
                            style: const TextStyle(
                              color: Colors.red,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                        Text(formaterMontant(c.coutLivraison?.toDouble() ?? 0),
                          style: const TextStyle(
                            color: Colors.blue,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        Text(
                          'Livreur: ${c.nomLivreur} - ${c.contactLivreur}',
                          style: const TextStyle(
                            color: Colors.green,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        Text(
                          'Livraison: ${c.typeLivraison}',
                          style: const TextStyle(
                            color: kPrimaryColor,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        Text(
                          'Adresse: ${c.adresse}',
                          style: const TextStyle(
                            color: Colors.black,
                          ),
                        ),
                        Text(
                          '${formaterDate(c.dateLivraison.toString())}',
                          style: const TextStyle(
                            color: Colors.black,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            initialList: livraisons,
            filter: (p0) {
              return livraisons
                  .where((c) => (c.etatLivraison.toString().contains(p0) ||
                      c.adresse.toString().contains(p0) ||
                      c.nomLivreur.toString().contains(p0) ||
                      c.typeLivraison.toString().contains(p0) ||
                      c.coutLivraison.toString().contains(p0)))
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
