import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/impression/impression_location_pdf.dart';
import 'package:mon_gravier_com/models/InformationsCommande.dart';
import 'package:mon_gravier_com/screens/note_client/note_client_screen.dart';

import '../../constants.dart';
import '../../helper/constants.dart';
import 'components/check_out_card.dart';

class DetailsLocationScreen extends StatefulWidget {
  static String routeName = "/details_location";

  const DetailsLocationScreen({super.key});

  @override
  State<DetailsLocationScreen> createState() => _DetailsLocationScreenState();
}

class _DetailsLocationScreenState extends State<DetailsLocationScreen> {

  int idLocation = 0;
  String etatLocation = "";
  // int montantTotal = 0;
  bool clientATerme = false;
  InformationsLocation infoLoc = InformationsLocation();
  UneLocation location = UneLocation();
  List<LigneLocation> lignes = [];
  TextEditingController motifController = TextEditingController();

  chargerDetailsLocation() async {
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
            .post(Uri.parse('${lienAPI()}details-location/$idLocation'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          infoLoc = InformationsLocation.fromJson(datas);
          if (infoLoc.code == 200) {
            setState(() {
              clientATerme = infoLoc.data?.clientATerme ?? false;
              location = infoLoc.data?.location ?? UneLocation();
              lignes = infoLoc.data?.lignes ?? [];
              // montantTotal = lignes.fold(0, (sum, l){
              //   num lePrix = l.prix ?? 0;
              //   double laQte = l.qte?.toDouble() ?? 0;
              //   double nbreJr = l.nombreJour?.toDouble() ?? 0;
              //   return (sum + (lePrix * laQte * nbreJr)).toInt() + ;
              // });
            });
          }else{
            EasyLoading.showError(infoLoc.message ?? '');
          }
        }
      } catch (e) {
        EasyLoading.showError("Une erreur s'est produite veuillez reesayer plus tard");
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
    var datas = Get.arguments;
    idLocation = datas[0];
    etatLocation = datas[1];
    motifController = TextEditingController();
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerDetailsLocation();
    });
  }

  @override
  void dispose() {
    motifController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          children: [
            const Text(
              "Détails location",
              style: TextStyle(color: Colors.black),
            ),
            Text(
              "${lignes.length} article(s)",
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
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
      floatingActionButton: FloatingActionButton(
        onPressed: (){
          if (lignes.isNotEmpty && location.id != null) {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (context) => ImpressionLocationPdf(location, lignes),
              ),
            );
          }else{
            EasyLoading.showError("Impossible de récupérer les détails de cette opération");
          }
        },
        backgroundColor: greenColor,
        child: const Icon(Icons.print, color: whiteColor),
      ),
      body: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 5),
        child: ListView.builder(
          itemCount: lignes.length,
          itemBuilder: (context, index) => GestureDetector(
            onTap: (){
              if (lignes[index].etatLocation == LOCATION_TERMINE) {
                Get.toNamed(NoteClientScreen.routeName, arguments: LigneCommande(
                  image: lignes[index].image,
                  prixReduction: lignes[index].prixReduction,
                  prixMoyen: lignes[index].prixMoyen,
                  qte: lignes[index].qte,
                  unite: lignes[index].unite,
                  nom: lignes[index].nom,
                  id: lignes[index].id,
                  etatLivraison: lignes[index].etatLocation,
                  description: lignes[index].description,
                  reference: lignes[index].reference,
                  prix: lignes[index].prix,
                  produitId: lignes[index].produitId,
                  commandeId: lignes[index].locationId,
                  statut: lignes[index].statut,
                ));
              }else{
               EasyLoading.showInfo("L'article n'a pas encore été livré!");
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
                          color: const Color(0xFFF5F6F9),
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
                        ),
                        const SizedBox(height: 8),
                        Text.rich(
                          TextSpan(
                            // Prix STOCKÉ sur la ligne (inclut le prix personnalisé du client),
                            // repli sur le prix catalogue si absent.
                            text: "${formaterMontant((lignes[index].prix ?? 0) > 0 ? lignes[index].prix!.toDouble() : (lignes[index].prixMoyen ?? 0).toDouble())} / ${lignes[index].unite.toString()}",
                            style: const TextStyle(
                                fontWeight: FontWeight.w600, color: kPrimaryColor),
                            children: [
                              TextSpan(
                                  text: " x${lignes[index].qte}",
                                  style: Theme.of(context).textTheme.bodyLarge),
                              TextSpan(
                                  text: "\t (${lignes[index].etatLocation})",
                                  style: red14MediumTextStyle),
                            ],
                          ),
                        ),
                        Text(
                          "Du ${formaterDate(lignes[index].debut.toString(), format: 'dd/MM/yyyy')} au ${formaterDate(lignes[index].fin.toString(), format: 'dd/MM/yyyy')} soit ${lignes[index].nombreJour} Jour(s)",
                          style: const TextStyle(color: Colors.grey, fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
      bottomNavigationBar: CheckoutCard(niveau: 3, data: [idLocation, etatLocation, clientATerme], montantTotal: 0),
    );
  }

}
