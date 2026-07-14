import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/constants.dart';

import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/screens/details_location/details_location_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../globale.dart';
import '../../../models/Commande.dart';

class LocationListeScreen extends StatelessWidget {
  const LocationListeScreen({super.key, required this.locations});

  final List<DetailsLocation> locations;

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
          child: SearchableList<DetailsLocation>(
            searchFieldEnabled: true,
            shrinkWrap: true,
            autoFocusOnSearch: false,
            sortWidget: const Icon(Icons.sort),
            sortPredicate: (a, b) {
              double mtna = a.montantTotal ?? 0;
              double mtnb = b.montantTotal ?? 0;
              return mtna.compareTo(mtnb);
            },
            physics: const BouncingScrollPhysics(),
            builder: (locations, index, c) => GestureDetector(
              onTap: () {
                Get.toNamed(DetailsLocationScreen.routeName, arguments: [c.id, c.etatLocation]);
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
                              image: AssetImage("assets/images/commande.gif"),
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
                            if (c.remise != null && c.remise! > 0) ...[
                              Text(
                                formaterMontant(c.remise!.toDouble() + c.montantTotal!.toDouble()),
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: kPrimaryColor,
                                  decoration: TextDecoration.lineThrough,
                                ),
                              ),
                            ],
                            Text(
                              formaterMontant(c.montantTotal?.toDouble() ?? 0),
                              style: const TextStyle(
                                color: Colors.blue,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            if(c.modePaiement != null) ...[
                              Text(
                                'Paiement: ${c.modePaiement}',
                                style: const TextStyle(
                                  color: Colors.black,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ],
                            Text(
                              '${formaterDate(c.dateLocation.toString())}',
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
            initialList: locations,
            filter: (p0) {
              return locations
                  .where((c) => (c.adresse.toString().contains(p0) ||
                      c.note.toString().contains(p0) ||
                      c.montantTotal.toString().contains(p0) ||
                      c.modePaiement.toString().contains(p0)))
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
