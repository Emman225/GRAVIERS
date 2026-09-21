import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../components/bouton_retour.dart';
import '../../components/codes_livraison.dart';
import '../../constants.dart';
import '../../helper/constants.dart';
import '../../models/Cart.dart';
import '../../models/InformationsCommande.dart';
import 'components/cart_card.dart';

class DetailsDemandeLivraisonAfficheScreen extends StatefulWidget {
  static String routeName = "/detailsDemandeLivraisonAffiche";
  const DetailsDemandeLivraisonAfficheScreen({super.key});

  @override
  State<DetailsDemandeLivraisonAfficheScreen> createState() => _DetailsDemandeLivraisonAfficheScreenState();
}

class _DetailsDemandeLivraisonAfficheScreenState extends State<DetailsDemandeLivraisonAfficheScreen> {

  List<Cart> datas = [];

  /// Codes de livraison des courses acceptées (10/09/2026) et numéro de la
  /// demande, reçus avec les lignes. L'écran acceptait une simple liste :
  /// il la lit encore, pour ne rien casser.
  List<CodesLigne> codes = [];
  String? numeroDemande;

  @override
  void initState() {
    final args = Get.arguments;
    if (args is Map) {
      datas = (args['lignes'] as List<Cart>?) ?? [];
      codes = (args['codes'] as List<CodesLigne>?) ?? [];
      numeroDemande = args['numero']?.toString();
    } else if (args is List<Cart>) {
      datas = args;
    }
    super.initState();
  }

  /// Le code de livraison à remettre au livreur, en tête du détail — comme
  /// sur le détail d'une commande ou d'une location.
  Widget _blocCodes() {
    if (codes.isEmpty) return const SizedBox.shrink();
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
          Text(
            "Code de livraison à remettre au livreur",
            style: kCorpsSecondaireStyle.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: kSpaceXs),
          for (final c in codes)
            CodesLivraison(
              codeLivraison: c.codeLivraison,
              codeEnlevement: null,
              numeroCommande: numeroDemande,
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Détails demande de livraison",
        ),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: datas.isEmpty
          ? Center(
              child: Image.asset('assets/images/empty_card.gif'),
            )
          : Container(
              width: double.infinity,
              height: heightOfScreen(context),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 5),
                child: ListView.builder(
                  // La première case porte les codes (10/09/2026), les suivantes les lignes.
                  itemCount: datas.length + 1,
                  itemBuilder: (context, position) {
                    if (position == 0) return _blocCodes();
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      child: CartCard(cart: datas[position - 1]),
                    );
                  },
                ),
              ),
            ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
    );
  }
}
