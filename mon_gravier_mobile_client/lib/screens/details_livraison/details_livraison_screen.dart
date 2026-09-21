import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/components/fiche_details.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/details_livraison.dart';
import 'package:mon_gravier_com/models/retour_livraison.dart';

import '../../components/bouton_retour.dart';
import '../../constants.dart';

class DetailsLivraisonScreen extends StatefulWidget {
  static String routeName = "/details_livraison";

  const DetailsLivraisonScreen({super.key});

  @override
  State<DetailsLivraisonScreen> createState() => _DetailsLivraisonScreenState();
}

class _DetailsLivraisonScreenState extends State<DetailsLivraisonScreen> {
  UneLivraison livraison = UneLivraison();
  int niveau = 0;
  double qteLivree = 0;
  DetailsLivraison retLiv = DetailsLivraison();
  LigneCommande ligneCommande = LigneCommande();
  LigneLivraison ligneLivraison = LigneLivraison();

  chargerDetailsLivraison() async {
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
            .post(Uri.parse('${lienAPI()}details-livraison/${livraison.id}'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          retLiv = DetailsLivraison.fromJson(datas);
          if (retLiv.code == 200) {
            // Écran quitté pendant le chargement : la réponse revient sur un écran
            // détruit et le rafraîchissement échoue (voir devis_screen.dart).
            if (mounted) setState(() {
              ligneCommande = retLiv.data?.ligneCommande ?? LigneCommande();
              ligneLivraison = retLiv.data?.ligneLivraison ?? LigneLivraison();
            });
          } else {
            afficherErreur(retLiv.message ?? '');
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

  @override
  void initState() {
    var data = Get.arguments;
    livraison = data[0];
    niveau = data[1];
    qteLivree = data[2];
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerDetailsLivraison();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Détails livraison",
        ),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: ListView(children: [
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
              child: Image.network(ligneCommande.image ??
                  'https://img.freepik.com/vecteurs-premium/fond-conception-banniere-large-3d-abstrait-bleu-diamant-brillant_181182-21825.jpg'),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(15.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                ligneCommande.nom.toString(),
                style: const TextStyle(
                    color: kTextColor, fontSize: 17, fontWeight: FontWeight.w700),
                maxLines: 2,
              ),
              const SizedBox(height: 12),
              // Une fiche : libellés en clair, valeurs en gras (08/09/2026).
              FicheDetails(lignes: [
                // Demande de livraison (13/09/2026) : la marchandise appartient au
                // client et n'a pas de prix — la fiche affichait « 0 F / Sac ».
                // Comme sur le site, pas de prix unitaire pour une course de transport.
                if (livraison.provenance != 'LIVRAISON')
                  InfoFiche('Prix unitaire',
                      "${formaterMontant(ligneCommande.prix?.toDouble() ?? 0)} / ${ligneCommande.unite ?? ''}",
                      couleur: kPrimaryColor),
                InfoFiche(
                  (ligneCommande.etatLivraison == LIVRAISON_LIVREE ||
                          ligneCommande.etatLocation == LOCATION_TERMINE)
                      ? 'Quantité livrée'
                      : 'Quantité à livrer',
                  "$qteLivree ${ligneCommande.unite ?? ''}",
                  couleur: kSuccessColor,
                ),
                InfoFiche('Description', ligneCommande.description?.toString() ?? ''),
              ]),
            ],
          ),
        ),
      ]),
    );
  }
}
