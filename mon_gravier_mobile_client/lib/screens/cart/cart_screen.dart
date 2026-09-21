import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/models/code_promo.dart';
import 'package:roundcheckbox/roundcheckbox.dart';

import '../../components/bouton_retour.dart';
import '../../components/etat_vide.dart';
import '../init_screen.dart';
import 'components/cart_card.dart';
import 'components/check_out_card.dart';

class CartScreen extends StatefulWidget {
  static String routeName = "/cart";

  const CartScreen({super.key});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  TextEditingController libelleController = TextEditingController();
  TextEditingController codePromoController = TextEditingController();
  Timer? timer;

  @override
  void initState() {
    libelleController = TextEditingController();
    codePromoController = TextEditingController();
    reduction = Reduction();
    montantTva = 0;
    coutReduction = 0;
    utiliserPoint = false;
    meFaireLivre = true;
    if(user.token == null){
      meFaireLivre = false;
    }
    if (kDebugMode) {
      print(tva);
    }
    super.initState();
    timer = Timer.periodic(const Duration(seconds: 3), (Timer t) {
      // dispose() annule bien ce minuteur, mais l'annulation et le battement
      // peuvent se croiser : il suffit que l'écran soit quitté à l'instant où
      // le minuteur se déclenche pour que setState soit appelé sur un état
      // détruit. En version release, l'exception qui en résulte n'a plus de
      // trace lisible — elle ressort en « _TypeError » sans origine.
      if (!mounted) {
        t.cancel();
        return;
      }
      setState(() {});
    });
  }

  @override
  void dispose() {
    libelleController.dispose();
    codePromoController.dispose();
    timer?.cancel();
    super.dispose();
  }

  allerAccueil() async {
    if (kDebugMode) {
      print("---------> Fermeture");
    }
    Get.offAllNamed(InitScreen.routeName);
    return true;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        leading: BoutonRetour(
          onTap: () => allerAccueil(),
          tooltip: "Retour à l'accueil",
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text("Votre panier"),
            Text(
              paniers.isEmpty
                  ? "Aucun article"
                  : "${paniers.length} article(s)",
              style: kLegendeStyle,
            ),
          ],
        ),
        actions: [
          // Le bouton « vider » restait affiché sur un panier DÉJÀ vide : une
          // action proposée qui ne pouvait rien faire.
          if (paniers.isNotEmpty)
            IconButton(
              tooltip: "Vider le panier",
              onPressed: () async {
                final vider = await confirmationAction(
                  context,
                  "Vider le panier",
                  "Voulez-vous retirer tous les articles de votre panier ?",
                );
                if (!mounted || !vider) return;
                setState(() {
                  paniers.clear();
                  // Le panier vidé, il ne provient plus d'aucun devis.
                  devisRepris = null;
                });
              },
              icon: const Icon(Icons.delete_outline),
            ),
          const SizedBox(width: kSpaceXs),
        ],
      ),

      // PANIER VIDE : l'écran n'affichait qu'une image animée, sans un mot ni
      // le moindre chemin de sortie. Le client se retrouvait devant un dessin.
      body: paniers.isEmpty
          ? EtatVide(
              icone: Icons.shopping_cart_outlined,
              titre: "Votre panier est vide",
              message:
                  "Parcourez le catalogue et ajoutez le sable, le gravier ou "
                  "le matériel dont vous avez besoin.",
              libelleAction: "Voir le catalogue",
              action: () => Get.offAllNamed(InitScreen.routeName),
            )

          // LISTE DÉFILANTE. Elle ne l'était PAS : la liste extérieure portait
          // `NeverScrollableScrollPhysics`, si bien qu'au-delà de trois ou
          // quatre articles le bas du panier — code promo, livraison, points,
          // TVA — devenait inatteignable. Le défilement est rendu à la liste
          // extérieure, et retiré de la liste intérieure qui, elle, n'a pas à
          // défiler séparément.
          : ListView(
              physics: const BouncingScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(
                  kSpaceLg, kSpaceLg, kSpaceLg, kSpaceXl),
              children: [
                // ----------------------------------------------- ARTICLES
                ListView.separated(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  padding: EdgeInsets.zero,
                  itemCount: paniers.length,
                  separatorBuilder: (_, __) => const SizedBox(height: kSpaceMd),
                  itemBuilder: (context, index) => Dismissible(
                    key: Key(paniers[index].product.id.toString()),
                    direction: DismissDirection.endToStart,
                    onDismissed: (direction) {
                      setState(() {
                        paniers.removeAt(index);
                      });
                    },
                    background: Container(
                      padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
                      decoration: BoxDecoration(
                        color: kErrorSoftColor,
                        borderRadius: BorderRadius.circular(kRadiusMd),
                      ),
                      alignment: Alignment.centerRight,
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            "Retirer",
                            style: TextStyle(
                              color: kErrorColor,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          SizedBox(width: kSpaceSm),
                          Icon(Icons.delete_outline, color: kErrorColor),
                        ],
                      ),
                    ),
                    child: Container(
                      padding: const EdgeInsets.all(kSpaceMd),
                      decoration: BoxDecoration(
                        color: kSurfaceColor,
                        borderRadius: BorderRadius.circular(kRadiusMd),
                        border: Border.all(color: kBorderColor),
                      ),
                      child: CartCard(
                        cart: paniers[index],
                        showCounter:
                            paniers[index].product.type_affaire == VENTE,
                      ),
                    ),
                  ),
                ),

                // Le glissement latéral n'était signalé nulle part : la seule
                // façon de retirer un article restait à deviner.
                const Padding(
                  padding: EdgeInsets.only(top: kSpaceSm),
                  child: Text(
                    "Glissez un article vers la gauche pour le retirer.",
                    style: kLegendeStyle,
                  ),
                ),

                // ---------------------------------------------- CODE PROMO
                if (reduction.id == null || reduction.id! <= 0) ...[
                  const SizedBox(height: kSpaceXl),
                  _Encart(
                    titre: "Code promotionnel",
                    enfant: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: TextFormField(
                            keyboardType: TextInputType.text,
                            controller: codePromoController,
                            textInputAction: TextInputAction.done,
                            textCapitalization: TextCapitalization.characters,
                            decoration: const InputDecoration(
                              hintText: "Saisissez votre code",
                              floatingLabelBehavior: FloatingLabelBehavior.never,
                            ),
                          ),
                        ),
                        const SizedBox(width: kSpaceMd),
                        SizedBox(
                          height: 50,
                          child: OutlinedButton(
                            onPressed: () => _verifierCodePromo(),
                            child: const Text("Appliquer"),
                          ),
                        ),
                      ],
                    ),
                  ),
                ] else ...[
                  const SizedBox(height: kSpaceXl),
                  _Encart(
                    titre: "Code promotionnel",
                    enfant: Row(
                      children: [
                        const Icon(Icons.check_circle,
                            color: kSuccessColor, size: 18),
                        const SizedBox(width: kSpaceSm),
                        Expanded(
                          child: Text(
                            "Réduction de ${reduction.tauxReduction ?? 0} % appliquée",
                            style: kCorpsStyle.copyWith(
                                fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                // ------------------------------------------- LIVRAISON
                const SizedBox(height: kSpaceMd),
                _Encart(
                  enfant: Row(
                    children: [
                      RoundCheckBox(
                        size: 26,
                        isChecked: meFaireLivre,
                        checkedColor: kPrimaryColor,
                        borderColor: kSecondaryColor,
                        onTap: (selected) {
                          if (user.token == null) {
                            meFaireLivre = false;
                            afficherErreur(
                                "Connectez-vous ou créez un compte pour être livré");
                          } else {
                            meFaireLivre = selected!;
                          }
                          setState(() {});
                        },
                      ),
                      const SizedBox(width: kSpaceMd),
                      const Expanded(
                        child: Text(
                          "Me faire livrer",
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                            color: kTextColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                // ------------------------------------- POINTS DE FIDÉLITÉ
                if (nombrePoint > 0) ...[
                  const SizedBox(height: kSpaceMd),
                  _Encart(
                    enfant: Row(
                      children: [
                        RoundCheckBox(
                          size: 26,
                          isChecked: utiliserPoint,
                          checkedColor: kPrimaryColor,
                          borderColor: kSecondaryColor,
                          onTap: (selected) {
                            setState(() {
                              utiliserPoint = selected!;
                            });
                            double mtn = montantPoint * nombrePoint;
                            if (utiliserPoint == true) {
                              afficherSucces(
                                  "Réduction de ${formaterMontant(mtn)} appliquée");
                            } else {
                              afficherInfo(
                                  "Réduction de ${formaterMontant(mtn)} retirée");
                            }
                          },
                        ),
                        const SizedBox(width: kSpaceMd),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                "Utiliser mes points de fidélité",
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w600,
                                  color: kTextColor,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                utiliserPoint && pointsRetenus < nombrePoint
                                    // Tous les points n'ont pas pu être posés :
                                    // une commande ne peut pas descendre sous le
                                    // minimum à payer. Le dire, plutôt que de
                                    // laisser le client compter lui-même.
                                    ? "$pointsRetenus point(s) appliqué(s) sur "
                                        "$nombrePoint, soit "
                                        "${formaterMontant(montantPoint * pointsRetenus)} "
                                        "de remise. Le reste vous est conservé."
                                    : "$nombrePoint $devise, soit "
                                        "${formaterMontant(montantPoint * nombrePoint)} de remise",
                                style: kLegendeStyle,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                // ------------------------------------------------- TVA
                if (tva > 0) ...[
                  const SizedBox(height: kSpaceMd),
                  _Encart(
                    enfant: Row(
                      children: [
                        Expanded(
                          child: Text(
                            "TVA ($tva %)",
                            style: kCorpsSecondaireStyle.copyWith(
                                fontWeight: FontWeight.w600),
                          ),
                        ),
                        Text(
                          formaterMontant(montantTva),
                          style: kCorpsStyle.copyWith(
                            fontWeight: FontWeight.w700,
                            fontFeatures: const [FontFeature.tabularFigures()],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                // ------------------------------------------------- AIRSI
                // Acompte de 5 % sur le HT + TVA, dû par le client sans régime
                // réel d'imposition (10/09/2026) ; compris dans le total à payer.
                if (tauxAirsi > 0) ...[
                  const SizedBox(height: kSpaceMd),
                  _Encart(
                    enfant: Row(
                      children: [
                        Expanded(
                          child: Text(
                            "AIRSI (${formaterTaux(tauxAirsi)} %)",
                            style: kCorpsSecondaireStyle.copyWith(
                                fontWeight: FontWeight.w600),
                          ),
                        ),
                        Text(
                          formaterMontant(montantAirsi),
                          style: kCorpsStyle.copyWith(
                            fontWeight: FontWeight.w700,
                            fontFeatures: const [FontFeature.tabularFigures()],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
      bottomNavigationBar: CheckoutCard(niveau: 1, data: const []),
    );
  }


  _verifierCodePromo() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "code": codePromoController.text.trim(),
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}verifier-code-promo'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          RetourCodePromo retourPromo = RetourCodePromo.fromJson(datas);
          if (retourPromo.code == 200) {
            setState(() {
              reduction = retourPromo.data ?? Reduction();
            });
            afficherSucces(retourPromo.message ?? '');
          } else {
            afficherErreur(retourPromo.message ?? '');
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        user.code = 500;
        user.message = messageErreurTechnique(e);
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

/// ENCART DU PANIER.
///
/// Les réglages (code promo, livraison, points, TVA) étaient séparés par de
/// simples `Divider` : quatre rangées flottantes sur fond blanc, sans qu'on
/// puisse dire où finissait l'une et où commençait la suivante. Chacune tient
/// désormais dans son propre bloc.
class _Encart extends StatelessWidget {
  const _Encart({required this.enfant, this.titre});

  final Widget enfant;
  final String? titre;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(kSpaceLg),
      decoration: BoxDecoration(
        color: kSurfaceColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
        border: Border.all(color: kBorderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (titre != null) ...[
            Text(titre!, style: kEtiquetteStyle.copyWith(color: kTextMutedColor)),
            const SizedBox(height: kSpaceMd),
          ],
          enfant,
        ],
      ),
    );
  }
}
