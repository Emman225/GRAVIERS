import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/impression/impression_location_pdf.dart';
import 'package:mon_gravier_com/models/InformationsCommande.dart';
import 'package:mon_gravier_com/screens/note_client/note_client_screen.dart';

import '../../components/bouton_retour.dart';
import '../../components/codes_livraison.dart';
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

  /// La réponse du serveur est-elle arrivée ? Sans ce drapeau, le message
  /// « aucun article » s'afficherait pendant le chargement.
  bool _reponseRecue = false;
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
            // Écran quitté pendant le chargement : la réponse revient sur un écran
            // détruit et le rafraîchissement échoue (voir devis_screen.dart).
            if (mounted) setState(() {
              _reponseRecue = true;
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
            if (mounted) setState(() => _reponseRecue = true);
            afficherErreur(infoLoc.message ?? '');
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          if (mounted) setState(() => _reponseRecue = true);
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        if (mounted) setState(() => _reponseRecue = true);
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
          if (lignes.isNotEmpty && location.id != null) {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (context) => ImpressionLocationPdf(location, lignes),
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
        child: (_reponseRecue && lignes.isEmpty)
            ? ListView(
                padding: const EdgeInsets.all(kSpaceLg),
                children: [_aucunArticle()],
              )
            : ListView.builder(
          // La première case porte les codes de la location (10/09/2026),
          // les suivantes les lignes.
          itemCount: lignes.length + 1,
          itemBuilder: (context, position) {
            if (position == 0) return _blocCodes();
            final index = position - 1;
            return GestureDetector(
            onTap: (){
              if (lignes[index].etatLocation == LOCATION_TERMINE) {
                // L'ÉCRAN DE NOTATION ATTEND DES IDENTIFIANTS DE PRODUIT,
                // pas une ligne de commande.
                //
                // On lui passait ici un objet `LigneCommande` entier, reconstruit
                // champ par champ — un reste de l'époque où l'on notait un seul
                // produit. Depuis, cet écran note PLUSIEURS produits et lit une
                // `List<int>` : l'affectation levait un `_TypeError` dans son
                // initState, et l'écran restait une page grise.
                Get.toNamed(
                  NoteClientScreen.routeName,
                  arguments: <int>[lignes[index].produitId ?? 0],
                );
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
          );
          },
        ),
      ),
      bottomNavigationBar: CheckoutCard(niveau: 3, data: [idLocation, etatLocation, clientATerme], montantTotal: 0),
    );
  }

  /// LES CODES DE LA LOCATION (10/09/2026), comme sur le détail d'une
  /// commande : le code de livraison à remettre au livreur, ou le bon
  /// d'enlèvement à présenter au fournisseur quand le client retire lui-même.
  Widget _blocCodes() {
    final donnees = infoLoc.data;
    final codes = donnees?.codes ?? const <CodesLigne>[];
    final etatLivraison = donnees?.etatLivraisonLibelle ?? '';
    if (donnees == null || (codes.isEmpty && etatLivraison.isEmpty)) {
      return const SizedBox.shrink();
    }
    final retrait = donnees.retraitSurPlace;
    final livre = donnees.etatLivraisonCode == 'LIVREE' || donnees.etatLivraisonCode == 'RETIREE';
    return Container(
      margin: const EdgeInsets.fromLTRB(kSpaceSm, kSpaceMd, kSpaceSm, kSpaceSm),
      padding: const EdgeInsets.all(kSpaceMd),
      decoration: BoxDecoration(
        color: kSurfaceColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
        border: Border.all(color: kBorderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Où en est le matériel (10/09/2026) : « Livrée le … », « Retirée le … ».
          if (etatLivraison.isNotEmpty) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: kSpaceSm, vertical: 3),
              decoration: BoxDecoration(
                color: livre ? kSuccessSoftColor : kAccentSoftColor,
                borderRadius: BorderRadius.circular(kRadiusPill),
              ),
              child: Text(
                etatLivraison,
                style: kEtiquetteStyle.copyWith(
                    color: livre ? kSuccessColor : kAccentColor, fontSize: 11),
              ),
            ),
            const SizedBox(height: kSpaceSm),
          ],
          if (codes.isNotEmpty)
            Text(
              retrait
                  ? "Bon d'enlèvement à présenter au fournisseur"
                  : "Code de livraison à remettre au livreur",
              style: kCorpsSecondaireStyle.copyWith(fontWeight: FontWeight.w700),
            ),
          if (codes.isNotEmpty) const SizedBox(height: kSpaceXs),
          for (final c in codes)
            CodesLivraison(
              codeLivraison: retrait ? null : c.codeLivraison,
              codeEnlevement: retrait ? c.codeEnlevement : null,
              numeroCommande: donnees.location?.numero,
            ),
        ],
      ),
    );
  }

  /// UN ÉCRAN VIDE NE DIT RIEN.
  ///
  /// Le corps était un simple ListView sur `lignes` : quand la liste revenait
  /// vide, la page s'affichait entièrement BLANCHE — pas un mot, pas une piste.
  /// Le client ne pouvait distinguer ni une panne, ni une location annulée,
  /// ni un matériel retiré.
  Widget _aucunArticle() {
    return Container(
      padding: const EdgeInsets.all(kSpaceXl),
      decoration: BoxDecoration(
        color: kSurfaceColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
        border: Border.all(color: kBorderColor),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.inventory_2_outlined,
              size: 40, color: kTextSecondaryColor),
          const SizedBox(height: kSpaceMd),
          const Text(
            "Aucun matériel à afficher",
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: kTextColor,
            ),
          ),
          const SizedBox(height: kSpaceSm),
          Text(
            "Le matériel de cette location n'a pas pu être chargé. "
            "Réessayez ; si l'écran reste vide, contactez-nous en indiquant "
            "le numéro de la location.",
            textAlign: TextAlign.center,
            style: kCorpsSecondaireStyle.copyWith(height: 1.35),
          ),
          const SizedBox(height: kSpaceLg),
          OutlinedButton.icon(
            onPressed: chargerDetailsLocation,
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text("Réessayer"),
          ),
        ],
      ),
    );
  }

}
