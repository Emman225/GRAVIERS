import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/constants.dart';

import 'package:mon_gravier_com_livreur/helper/constants.dart';
import 'package:mon_gravier_com_livreur/models/retour_livraison.dart';
import 'package:mon_gravier_com_livreur/screens/details_livraison/details_livraison_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../../globale.dart';

class LivraisonListeScreen extends StatelessWidget {
  LivraisonListeScreen({super.key, required this.livraisons});

  List<UneLivraison> livraisons;

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
            builder: (livraisons, index, c) {
              final bool estTerminee = c.etatLivraison == LIVRAISON_LIVREE;
              return GestureDetector(
              onTap: () => Get.toNamed(DetailsLivraisonScreen.routeName,
                  arguments: [c, (c.detailCommandeId! > 0) ? 1 : 2, c.qte]),
              child: Padding(
                padding: const EdgeInsets.all(8.0),
                child: Container(
                  height: c.etatLivraison == LIVRAISON_EN_ATTENTE ? 150 : 240,
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
                      mainAxisAlignment: mainSpaceBet,
                      children: [
                        Flexible(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              if(c.etatLivraison == LIVRAISON_LIVREE) ...[
                                Text('N°Livraison: ${c.numero}',
                                  style: const TextStyle(
                                    color: kPrimaryColor,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                              if(c.etatLivraison != LIVRAISON_EN_ATTENTE) ...[
                                if (('${c.code_enlevement ?? ''}').trim().isNotEmpty)
                                  Text('N°BE: ${c.code_enlevement}',
                                    style: const TextStyle(
                                      color: Colors.red,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                if (('${c.nom_fournisseur ?? ''}').trim().isNotEmpty)
                                  Text('Fournisseur: ${c.nom_fournisseur}',
                                    style: black14BoldTextStyle,
                                  ),
                              ],
                              Text(
                                "Cout de livraison: ${formaterMontant(c.coutLivraison?.toDouble() ?? 0)}",
                                style: const TextStyle(
                                  color: Colors.blue,
                                ),
                              ),
                              if(c.etatLivraison != LIVRAISON_EN_ATTENTE) ...[
                                Text(
                                  'Client: ${c.nomClient} - ${c.contactClient}',
                                  style: const TextStyle(
                                    color: Colors.green,
                                  ),
                                ),
                              ],
                              if (('${c.typeLivraison ?? ''}').trim().isNotEmpty)
                                Text(
                                  'Type: ${c.typeLivraison}',
                                  style: const TextStyle(
                                    color: kPrimaryColor,
                                  ),
                                ),
                              if(c.etatLivraison != LIVRAISON_LIVREE) ...[
                                Text(
                                  'Lieu: ${c.adresse}',
                                  style: const TextStyle(
                                    color: Colors.black,
                                  ),
                                ),
                              ],
                              Text(
                                'Date livraison: ${formaterDate(c.dateLivraison.toString())}',
                                style: const TextStyle(
                                  color: Colors.black,
                                ),
                              ),
                              if(c.etatLivraison == LIVRAISON_LIVREE) ...[
                                Text('Livraison eff.: ${formaterDate(c.updatedAt.toString())}',),
                              ],
                            ],
                          ),
                        ),
                        const Icon(Icons.arrow_forward_ios),
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
            initialList: livraisons,
            filter: (p0) {
              return livraisons
                  .where((c) => (c.code_enlevement.toString().toUpperCase().contains(p0.trim().toUpperCase()) ||
                      c.adresse.toString().toUpperCase().contains(p0.trim().toUpperCase()) ||
                      c.nom_fournisseur.toString().toUpperCase().contains(p0.trim().toUpperCase()) ||
                      c.typeLivraison.toString().toUpperCase().contains(p0.trim().toUpperCase()) ||
                      c.coutLivraison.toString().toUpperCase().contains(p0.trim().toUpperCase())))
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
