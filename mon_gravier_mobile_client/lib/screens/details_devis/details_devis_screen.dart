import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/impression/impression_devis_pdf.dart';
import 'package:mon_gravier_com/models/Cart.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import 'package:mon_gravier_com/models/detail_devis.dart';
import 'package:mon_gravier_com/models/devis.dart';
import 'package:mon_gravier_com/screens/cart/cart_screen.dart';

import '../../components/bouton_retour.dart';
import '../../constants.dart';
import '../../helper/constants.dart';

class DetailsDevisScreen extends StatefulWidget {
  static String routeName = "/details_devis";

  const DetailsDevisScreen({super.key});

  @override
  State<DetailsDevisScreen> createState() => _DetailsDevisScreenState();
}

class _DetailsDevisScreenState extends State<DetailsDevisScreen> {
  DataDevis unDevis = Get.arguments;
  List<DataDetailDevis> lignes = [];
  int montantTotal = 0;
  RetourDetailDevis retDetDev = RetourDetailDevis();


  @override
  void initState() {
    unDevis = Get.arguments;
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerDetailsDevis();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          children: [
            const Text(
              "Détails devis",
            ),
            Text(
              "${lignes.length} article(s)",
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: (){
          if (lignes.isNotEmpty && unDevis.id != null) {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (context) => ImpressionDevisPdf(unDevis, lignes),
              ),
            );
          }else{
            afficherErreur("Impossible de récupérer les détails de cette opération");
          }
        },
        backgroundColor: greenColor,
        child: const Icon(Icons.print, color: whiteColor),
      ),
      body: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 5),
        child: ListView.builder(
          itemCount: lignes.length,
          itemBuilder: (context, index) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 15),
            child: Row(
              mainAxisAlignment: mainStart,
              children: [
                SizedBox(
                  width: 50,
                  child: AspectRatio(
                    aspectRatio: 0.88,
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: kSurfaceMutedColor,
                        borderRadius: BorderRadius.circular(15),
                      ),
                      child: Image.network(lignes[index].image.toString()),
                    ),
                  ),
                ),
                addHorizontalSpace(10),
                Flexible(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        lignes[index].nom.toString(),
                        style:
                            const TextStyle(color: Colors.black, fontSize: 16),
                        maxLines: 2,
                      ),
                      const SizedBox(height: 8),
                      Text.rich(
                        TextSpan(
                          text:
                              "${formaterMontant((lignes[index].prix ?? 0) > 0 ? lignes[index].prix!.toDouble() : (lignes[index].prixMoyen ?? 0).toDouble())} / ${lignes[index].unite.toString()}",
                          style: const TextStyle(
                              fontWeight: FontWeight.w600,
                              color: kPrimaryColor),
                          children: [
                            TextSpan(
                                text: " x${lignes[index].qte}",
                                style: Theme.of(context).textTheme.bodyLarge),
                            if(unDevis.service == LOCATION) ...[
                              TextSpan(
                                  text: " pour ${lignes[index].nbre_jour_location} jour(s)"),
                            ]
                          ],
                        ),
                      )
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      bottomNavigationBar: Padding(
        padding: const EdgeInsets.all(20.0),
        child: Row(
          children: [
            Flexible(
              child: ElevatedButton(
                style: ButtonStyle(
                  backgroundColor: MaterialStateProperty.all(redColor)
                ),
                onPressed: () async {
                  if (await confirmationAction(context, "Attention !",
                      "Voulez-vous supprimer ce devis ?")) {
                    supprimerDevis();
                  }
                },
                child: const Text("Supprimer Devis"),
              ),
            ),
            addHorizontalSpace(10),
            Flexible(
              child: ElevatedButton(
                onPressed: () async {
                  if (paniers.isEmpty) {
                    _chargerPanier();
                  } else {
                    if (await confirmationAction(context, "Attention !",
                        "Votre panier contient des articles voulez-vous les supprimer ?")) {
                      _chargerPanier();
                    }
                  }
                },
                child: const Text("Charger panier"),
              ),
            ),
          ],
        ),
      ),
    );
  }

  _chargerPanier() {
    paniers.clear();
    // On retient de quel devis ce panier provient : la commande le signalera au
    // serveur, qui pourra alors clore le devis au lieu de le laisser « en
    // attente » à vie.
    devisRepris = unDevis.id;
    for (var dd in lignes) {
      final double prixDevis = (dd.prix ?? 0) > 0
          ? dd.prix!.toDouble()
          : (dd.prixMoyen ?? 0).toDouble();
      paniers.add(Cart(
        dateDebut: dd.debut_location,
        dateDeFin: dd.fin_location,
        nbreJours: dd.nbre_jour_location,
          product: Produits(
            reference: dd.reference,
            unite: dd.unite,
            nom: dd.nom,
            prixReduction: prixDevis.toInt(),
            description: dd.description,
            prixMoyen: prixDevis.toInt(),
            prixPersonnalise: prixDevis,
            // L'identifiant du PRODUIT, pas celui de la ligne de devis.
            //
            // dd.id est l'id de la ligne detail_devis. Le panier le portait comme
            // s'il s'agissait du produit, et la commande partait ensuite avec
            // 'produit_id' = id de ligne : le serveur ne trouvait évidemment aucun
            // produit correspondant et refusait tout, avec « Un ou plusieurs
            // produits de votre panier ne sont plus disponibles » — un message
            // trompeur, puisque les produits étaient bien là.
            //
            // Sans ce contrôle serveur, la commande serait partie avec un
            // produit_id faux : lignes rattachées au mauvais article, ou rejet
            // brut de la clé étrangère.
            id: dd.produitId,
            image: dd.image,
            type_affaire: unDevis.service == LOCATION ? LOCATION : VENTE,
          ),
          numOfItem: dd.qte ?? 0,
          type: 1));
    }
    afficherSucces("Panier chargé avec succès");
    Get.toNamed(CartScreen.routeName);
  }

  chargerDetailsDevis() async {
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
            .post(Uri.parse('${lienAPI()}details-devis/${unDevis.id}'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          retDetDev = RetourDetailDevis.fromJson(datas);
          if (retDetDev.code == 200) {
            // Écran quitté pendant le chargement : la réponse revient sur un écran
            // détruit et le rafraîchissement échoue (voir devis_screen.dart).
            if (mounted) setState(() {
              lignes = retDetDev.data ?? [];
              montantTotal = lignes.fold(0, (sum, l) {
                double lePrix = l.prix ?? 0;
                double laQte = l.qte?.toDouble() ?? 0;
                return (sum + (lePrix * laQte)).toInt();
              });
            });
          } else {
            afficherErreur(retDetDev.message ?? '');
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        afficherErreur(messageErreurTechnique(e));
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  supprimerDevis() async {
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
            .post(Uri.parse('${lienAPI()}supprimer-devis/${unDevis.id}'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            afficherSucces(datas['message'] ?? '');
            Get.back();
            //Get.back();
          } else {
            afficherErreur(datas['message'] ?? '');
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        afficherErreur(messageErreurTechnique(e));
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }
}
