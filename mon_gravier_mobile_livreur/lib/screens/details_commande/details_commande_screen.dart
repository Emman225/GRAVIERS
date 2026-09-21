import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/models/Commande.dart';
import 'package:mon_gravier_com_livreur/models/InformationsCommande.dart';

import '../../../components/bouton_retour.dart';
import '../../constants.dart';
import '../../helper/constants.dart';
import 'components/check_out_card.dart';

class DetailsCommandeScreen extends StatefulWidget {
  static String routeName = "/details_commande";

  const DetailsCommandeScreen({super.key});

  @override
  State<DetailsCommandeScreen> createState() => _DetailsCommandeScreenState();
}

class _DetailsCommandeScreenState extends State<DetailsCommandeScreen> {

  int idCommande = 0;
  int montantTotal = 0;
  InformationsCommande infoCom = InformationsCommande();
  Commande commande = Commande();
  List<LigneCommande> lignes = [];

  chargerDetailsCommande() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
      };

      if (kDebugMode) {
        print(param);
      }

      // try {
        final http.Response retourHttp = await http
            .post(Uri.parse('${lienAPI()}details-commande/$idCommande'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          infoCom = InformationsCommande.fromJson(datas);
          if (infoCom.code == 200) {
            setState(() {
              commande = infoCom.data?.commande ?? Commande();
              lignes = infoCom.data?.lignes ?? [];
              montantTotal = lignes.fold(0, (sum, l){
                num lePrix = l.prix ?? 0;
                double laQte = l.qte?.toDouble() ?? 0;
                return (sum + (lePrix * laQte)).toInt();
              });
            });
          }else{
            afficherErreur(infoCom.message ?? '');
          }
        } else {
          // Sans cette branche, une reponse serveur en erreur ne produisait
          // AUCUNE reaction a l'ecran.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez reessayer.");
        }
      // } catch (e) {
      //   afficherErreur("Une erreur s'est produite veuillez reesayer plus tard");
      //   if (kDebugMode) {
      //     print(e.toString());
      //   }
      // }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    idCommande = Get.arguments;
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerDetailsCommande();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          children: [
            const Text(
              "Détails commande",
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
      body: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 5),
        child: ListView.builder(
          itemCount: lignes.length,
          itemBuilder: (context, index) => GestureDetector(
            onTap: (){
              if (lignes[index].etatLivraison == LIVRAISON_LIVREE) {
              }else{
               afficherInfo("L'article n'a pas encore été livré!");
              }
            },
            child: Padding(
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
                          style: const TextStyle(color: Colors.black, fontSize: 16),
                          maxLines: 2,
                        ),
                        const SizedBox(height: 8),
                        Text.rich(
                          TextSpan(
                            text: "${formaterMontant(lignes[index].prixMoyen!.toDouble())} / ${lignes[index].unite.toString()}",
                            style: const TextStyle(
                                fontWeight: FontWeight.w600, color: kPrimaryColor),
                            children: [
                              TextSpan(
                                  text: "   × ${lignes[index].qte}",
                                  style: Theme.of(context).textTheme.bodyLarge),
                              TextSpan(
                                  text: "\nÉtat de livraison : ${lignes[index].etatLivraison}",
                                  style: red14MediumTextStyle),
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
      ),
      bottomNavigationBar: CheckoutCard(niveau: 3, data: const [], montantTotal: montantTotal),
    );
  }
}
