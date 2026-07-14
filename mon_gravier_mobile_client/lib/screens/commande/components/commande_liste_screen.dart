import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/constants.dart';

import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/screens/details_commande/details_commande_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../globale.dart';
import '../../../models/Commande.dart';

class CommandeListeScreen extends StatelessWidget {
  const CommandeListeScreen({super.key, required this.commandes});

  final List<DetailsCommande> commandes;

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
          child: SearchableList<DetailsCommande>(
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
            builder: (commandes, index, c) {
              final bool estTerminee = c.etatCommande == COMMANDE_TERMINE;
              return GestureDetector(
                onTap: () {
                  Get.toNamed(DetailsCommandeScreen.routeName, arguments: [c.id, c.etatCommande, c.remise, c.montantTotal]);
                },
                child: Padding(
                  padding: const EdgeInsets.all(8.0),
                  child: Container(
                    height: 120,
                    decoration: BoxDecoration(
                      color: estTerminee ? Colors.green[50] : Colors.grey[200],
                      borderRadius: BorderRadius.circular(10),
                      border: estTerminee
                          ? Border.all(color: Colors.green.shade400, width: 1.5)
                          : null,
                    ),
                    child: Stack(
                      children: [
                        Row(
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
                                    '${formaterDate(c.dateCommande.toString())}',
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
                        if (estTerminee)
                          Positioned(
                            top: 8,
                            right: 30,
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
            initialList: commandes,
            filter: (p0) {
              return commandes
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
