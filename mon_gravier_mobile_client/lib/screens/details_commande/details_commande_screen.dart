import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/components/afficher_image_widget.dart';
import 'package:mon_gravier_com/components/codes_livraison.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/impression/impression_commande_pdf.dart';
import 'package:mon_gravier_com/models/Commande.dart';
import 'package:mon_gravier_com/models/InformationsCommande.dart';
import 'package:mon_gravier_com/screens/note_client/note_client_screen.dart';

import '../../components/bouton_retour.dart';
import '../../components/image_reseau.dart';
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
  String etatCommande = "";
  double montantTotal = 0;
  double remise = 0;
  bool clientATerme = false;
  InformationsCommande infoCom = InformationsCommande();
  UneCommande commande = UneCommande();
  List<LigneCommande> lignes = [];
  TextEditingController motifController = TextEditingController();
  List<int> ids = [];
  List<int> idsProd = [];

  /// La réponse du serveur est-elle arrivée ? Sans ce drapeau, le message
  /// « aucun article » s'afficherait pendant le chargement.
  bool _reponseRecue = false;

  /// Ou le doigt s'est pose : sert a ancrer le menu d'actions sur la ligne
  /// touchee, au lieu du point fixe (100, 100) d'origine.
  Offset? _positionDuDoigt;

  List<Map<int, dynamic>> opts = [
    {1: const Icon(Icons.star)},
    {1: const Icon(Icons.star)},
    {1: const Icon(Icons.star)},
  ];

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

      try {
        retourHttp = await http
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
            // Écran quitté pendant l'appel : la réponse revient sur un écran détruit
            // et le rafraîchissement échoue (voir devis_screen.dart).
            if (mounted) setState(() {
              _reponseRecue = true;
              clientATerme = infoCom.data?.client_a_terme ?? false;
              commande = infoCom.data?.commande ?? UneCommande();
              lignes = infoCom.data?.lignes ?? [];
            });
          } else {
            if (mounted) setState(() => _reponseRecue = true);
            afficherErreur(infoCom.message ?? '');
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
    idCommande = datas[0];
    etatCommande = datas[1];
    remise = datas[2];
    montantTotal = datas[3];
    motifController = TextEditingController();
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerDetailsCommande();
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
          mainAxisAlignment: mainStart,
          crossAxisAlignment: crossStart,
          children: [
            const Text(
              "Détails commande à imprimer ou retourner",
            ),
            Text(
              "${lignes.length} article(s)",
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
        elevation: 0,
        leading: const BoutonRetour(),
        actions: [
          if (commande.fichier_bl != null && commande.fichier_bl != '') ...[
            IconButton(
                tooltip: 'Voir le bon de commande joint',
                onPressed: () {
                  // Lot 105 ter : l'écran dit quelle image il montre.
                  final numeroBon = (commande.numero_bl ?? '').trim();
                  Get.toNamed(AfficherImageWidget.routeName, arguments: {
                    'url': commande.fichier_bl,
                    'titre': numeroBon.isNotEmpty ? 'Bon de commande n° $numeroBon' : 'Bon de commande joint',
                    'sousTitre': 'Commande n° ${commande.numero ?? ''}',
                  });
                },
                icon: const Icon(
                  Icons.file_copy_outlined,
                  size: 30,
                )),
            addHorizontalSpace(5),
          ]
        ],
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
      // floatingActionButton: (user.token != null && user.token != ""&& ids.isNotEmpty)
      //     ? FloatingActionButton.extended(
      //   backgroundColor: greenColor,
      //   foregroundColor: Colors.black,
      //   onPressed: () async {
      //
      //   },
      //   icon: const Icon(Icons.remove_red_eye_outlined, color: whiteColor),
      //   label: const Text('Voir les options possible', style: white16BoldTextStyle,),
      // )
      //     : null,
      floatingActionButton: FloatingActionButton(
        onPressed: () {
          if (lignes.isNotEmpty && commande.id != null) {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (context) => ImpressionCommandePdf(commande, lignes),
              ),
            );
          } else {
            afficherErreur(
                "Impossible de récupérer les détails de cette opération");
          }
        },
        tooltip: "Imprimer la proforma ou la facture",
        // Le bouton était VERT, une couleur absente de la marque.
        backgroundColor: kPrimaryColor,
        child: const Icon(Icons.print_outlined, color: whiteColor),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(kSpaceLg, kSpaceLg, kSpaceLg, 96),
        children: [
          // CE QUE L'ON PEUT FAIRE ICI N'ÉTAIT ÉCRIT NULLE PART.
          //
          // Noter un produit et en demander le retour se déclenchaient en
          // touchant une ligne — sans qu'aucun élément à l'écran ne l'indique.
          // Un client qui ne l'avait pas découvert par hasard ne pouvait pas
          // utiliser ces deux fonctions.
          if (lignes.any((l) => l.etatLivraison == LIVRAISON_LIVREE))
            Container(
              margin: const EdgeInsets.only(bottom: kSpaceLg),
              padding: const EdgeInsets.all(kSpaceMd),
              decoration: BoxDecoration(
                color: kPrimarySoftColor,
                borderRadius: BorderRadius.circular(kRadiusMd),
                border: Border.all(color: kBorderColor),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.touch_app_outlined,
                      size: 18, color: kPrimaryColor),
                  const SizedBox(width: kSpaceMd),
                  Expanded(
                    child: Text(
                      "Touchez un article livré pour le noter ou en demander "
                      "le retour. Vous pouvez en sélectionner plusieurs.",
                      style: kCorpsSecondaireStyle.copyWith(height: 1.35),
                    ),
                  ),
                ],
              ),
            ),
          for (int index = 0; index < lignes.length; index++)
            _ligneArticle(lignes[index]),
          if (_reponseRecue && lignes.isEmpty) _aucunArticle(),
        ],
      ),
      bottomNavigationBar: CheckoutCard(
          niveau: 3,
          data: [idCommande, etatCommande, clientATerme],
          montantTotal: montantTotal),
    );
  }

  /// UN ÉCRAN VIDE NE DIT RIEN.
  ///
  /// Le corps était un simple ListView sur `lignes` : quand la liste revenait
  /// vide, la page s'affichait entièrement BLANCHE — pas un mot, pas une piste.
  /// Le client ne pouvait distinguer ni une panne, ni une commande annulée,
  /// ni un article retiré.
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
            "Aucun article à afficher",
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: kTextColor,
            ),
          ),
          const SizedBox(height: kSpaceSm),
          Text(
            "Les articles de cette commande n'ont pas pu être chargés. "
            "Réessayez ; si l'écran reste vide, contactez-nous en indiquant "
            "le numéro de la commande.",
            textAlign: TextAlign.center,
            style: kCorpsSecondaireStyle.copyWith(height: 1.35),
          ),
          const SizedBox(height: kSpaceLg),
          OutlinedButton.icon(
            onPressed: chargerDetailsCommande,
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text("Réessayer"),
          ),
        ],
      ),
    );
  }

  /// Une ligne d'article. Les articles LIVRÉS sont sélectionnables ; les autres
  /// ne le sont pas, et le disent.
  Widget _ligneArticle(LigneCommande ligne) {
    final bool livre = ligne.etatLivraison == LIVRAISON_LIVREE;
    final bool choisi = ids.contains(ligne.id);

    final Color couleurEtat = livre
        ? kSuccessColor
        : (ligne.etatLivraison == LIVRAISON_EN_TRAITEMENT
            ? kPrimaryMidColor
            : kWarningColor);
    final Color fondEtat = livre
        ? kSuccessSoftColor
        : (ligne.etatLivraison == LIVRAISON_EN_TRAITEMENT
            ? kPrimarySoftColor
            : kWarningSoftColor);

    return Padding(
      padding: const EdgeInsets.only(bottom: kSpaceMd),
      child: Material(
        color: choisi ? kPrimarySoftColor : kSurfaceColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          // La position du doigt sert à ancrer le menu : il s'ouvrait
          // auparavant à un point FIXE de l'écran (100, 100), sans rapport avec
          // la ligne touchée — il semblait flotter en l'air.
          onTapDown: (details) => _positionDuDoigt = details.globalPosition,
          onTap: () => _choisirArticle(ligne),
          child: Ink(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(kRadiusMd),
              border: Border.all(
                color: choisi ? kPrimaryColor : kBorderColor,
                width: choisi ? 1.4 : 1,
              ),
            ),
            child: Padding(
              padding: const EdgeInsets.all(kSpaceMd),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ImageReseau(
                    url: ligne.image.toString(),
                    width: 54,
                    height: 54,
                    fit: BoxFit.cover,
                    rayon: kRadiusSm,
                    icone: Icons.photo_outlined,
                  ),
                  const SizedBox(width: kSpaceMd),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          ligne.nom.toString(),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                            color: kTextColor,
                            height: 1.25,
                          ),
                        ),
                        const SizedBox(height: kSpaceXs),
                        Text(
                          // Prix STOCKÉ sur la ligne (inclut le prix
                          // personnalisé), repli sur le prix catalogue si
                          // absent.
                          "${formaterMontant((ligne.prix ?? 0) > 0 ? ligne.prix!.toDouble() : (ligne.prixMoyen ?? 0).toDouble())}"
                          " / ${ligne.unite}   ×${ligne.qte}",
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: kPrimaryColor,
                            fontFeatures: [FontFeature.tabularFigures()],
                          ),
                        ),
                        const SizedBox(height: kSpaceSm),
                        Row(
                          children: [
                            // L'état de livraison s'affichait en ROUGE quel
                            // qu'il soit — « LIVREE » compris.
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: kSpaceSm, vertical: 3),
                              decoration: BoxDecoration(
                                color: fondEtat,
                                borderRadius:
                                    BorderRadius.circular(kRadiusPill),
                              ),
                              child: Text(
                                ligne.etatLivraison.toString(),
                                style: kEtiquetteStyle.copyWith(
                                    color: couleurEtat, fontSize: 9),
                              ),
                            ),
                            if (choisi) ...[
                              const SizedBox(width: kSpaceSm),
                              const Icon(Icons.check_circle,
                                  size: 15, color: kPrimaryColor),
                            ],
                          ],
                        ),
                        // Les codes de la ligne (point 17) : code de livraison
                        // pour le livreur, bon d'enlèvement pour le fournisseur
                        // — le seul qui compte quand le client retire lui-même.
                        for (final c in ligne.codes ?? const <CodesLigne>[])
                          CodesLivraison(
                            codeLivraison: (commande.est_livrable ?? 1) == 1
                                ? c.codeLivraison
                                : null,
                            // Le bon d'enlèvement ne concerne le client que
                            // s'il retire lui-même chez le fournisseur.
                            codeEnlevement: (commande.est_livrable ?? 1) == 1
                                ? null
                                : c.codeEnlevement,
                            numeroCommande: commande.numero,
                          ),
                      ],
                    ),
                  ),
                  // Le chevron « suivant » laissait croire que la ligne menait
                  // à un autre écran. Sur un article livré, c'est un menu qui
                  // s'ouvre ; sur les autres, rien.
                  if (livre)
                    const Icon(Icons.more_vert,
                        color: kTextSecondaryColor, size: 20),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// Sélection d'un article, puis ouverture du menu d'actions.
  ///
  /// `showMenu` était appelé À L'INTÉRIEUR de `setState` : on empilait une
  /// route au milieu d'une mutation d'état. Les deux sont désormais séparés.
  void _choisirArticle(LigneCommande ligne) {
    if (ligne.etatLivraison != LIVRAISON_LIVREE) {
      afficherInfo("Cet article n'est pas encore livré. Vous pourrez le noter "
          "ou en demander le retour une fois la livraison faite.");
      return;
    }

    final bool dejaChoisi = ids.contains(ligne.id);

    setState(() {
      if (dejaChoisi) {
        ids.remove(ligne.id);
        idsProd.remove(ligne.produitId);
      } else {
        ids.add(ligne.id ?? 0);
        if (!idsProd.contains(ligne.produitId)) {
          idsProd.add(ligne.produitId ?? 0);
        }
      }
    });

    // Retirer un article de la sélection n'ouvre pas le menu.
    if (!dejaChoisi) _ouvrirMenuActions();
  }

  /// Menu d'actions, ancré là où le doigt s'est posé.
  Future<void> _ouvrirMenuActions() async {
    final rendu = Overlay.of(context).context.findRenderObject();
    final Size ecran =
        rendu is RenderBox ? rendu.size : MediaQuery.of(context).size;
    final Offset p = _positionDuDoigt ?? ecran.center(Offset.zero);

    final int? choix = await showMenu<int>(
      context: context,
      position: RelativeRect.fromLTRB(
        p.dx,
        p.dy,
        ecran.width - p.dx,
        ecran.height - p.dy,
      ),
      items: const [
        PopupMenuItem<int>(
          value: 1,
          child: Row(
            children: [
              Icon(Icons.star_outline, color: kPrimaryColor),
              SizedBox(width: kSpaceMd),
              Text('Noter les produits'),
            ],
          ),
        ),
        PopupMenuItem<int>(
          value: 2,
          child: Row(
            children: [
              Icon(Icons.assignment_return_outlined, color: kPrimaryColor),
              SizedBox(width: kSpaceMd),
              Text('Retourner les produits'),
            ],
          ),
        ),
      ],
    );

    if (!mounted) return;

    // Le choix est lu au RETOUR du menu, et non par un rappel `onTap` sur
    // chaque entrée : refermer le menu sans rien choisir conserve alors la
    // sélection, ce qui est exactement ce qui permet d'en cocher plusieurs.
    if (choix == 1) {
      await Get.toNamed(NoteClientScreen.routeName, arguments: List<int>.from(idsProd));
      if (!mounted) return;
      setState(() {
        ids.clear();
        idsProd.clear();
      });
    } else if (choix == 2) {
      _demandeRetourWidget();
    }
  }

  _demandeRetourWidget() {
    // Le champ conservait le motif de la demande precedente : la suivante
    // partait avec un texte qui ne la concernait pas.
    motifController.clear();
    showDialog(
        barrierDismissible: false,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            title: const Text("Motif du retour"),
            elevation: 5.0,
            content: SizedBox(
              height: 200,
              child: Column(
                children: [
                  TextFormField(
                    controller: motifController,
                    keyboardType: TextInputType.text,
                    textInputAction: TextInputAction.done,
                    maxLines: 6,
                    decoration: const InputDecoration(
                      labelText: "Motif *",
                      hintText: "Saisir le motif ici...",
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                  onPressed: () {
                    Get.back();
                  },
                  child: const Text(
                    "Annuler",
                    style: TextStyle(fontSize: 14, color: kTextSecondaryColor),
                  )),
              TextButton(
                  onPressed: () async {
                    if (motifController.text.trim().isNotEmpty) {
                      if (await verifierConnexion()) {
                        try {
                          afficherChargement();

                          var param = {
                            'access': user.token.toString(),
                            'type': user.type.toString(),
                            'motif': motifController.text.trim(),
                            'idLigne': ids,
                          };

                          if (kDebugMode) {
                            print(param);
                          }

                          retourHttp = await http
                              .post(
                                  Uri.parse(
                                      '${lienAPI()}demande-retour-produit'),
                                  headers: {"Content-Type": "application/json"},
                                  body: jsonEncode(param))
                              .timeout(const Duration(minutes: 2));

                          var datas = jsonDecode(retourHttp.body);

                          if (kDebugMode) {
                            print(datas);
                          }

                          if (retourHttp.statusCode == 200) {
                            if (datas['code'] == 200) {
                              // Écran quitté pendant l'appel : la réponse revient sur un écran détruit
                              // et le rafraîchissement échoue (voir devis_screen.dart).
                              if (mounted) setState(() {
                                ids.clear();
                                idsProd.clear();
                              });
                              Get.back();
                              afficherSucces(datas['message']);
                            } else {
                              // Demande deja en cours : le dialogue se referme,
                              // le message dit pourquoi.
                              Get.back();
                              afficherErreur(datas['message']);
                            }
                          } else {
                            // Sans cette branche, une réponse serveur en erreur ne produisait
                            // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
                            Get.back();
                            afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
                          }
                        } catch (e) {
                          user.code = 500;
                          user.message =
                              messageErreurTechnique(e);
                          if (kDebugMode) {
                            print(e.toString());
                          }
                        }

                        fermerChargement();
                      } else {
                        afficherInfo(
                            "Veuillez vérifier votre connexion internet");
                        Get.back();
                      }
                      // UN `Get.back()` DE TROP.
                      //
                      // Il etait place ICI, hors de toute condition, et
                      // s'ajoutait a celui de la branche de succes : une
                      // demande de retour acceptee refermait le dialogue PUIS
                      // l'ecran de detail, et le client se retrouvait ejecte
                      // sur la liste des commandes sans comprendre pourquoi.
                      //
                      // Chaque branche referme desormais ce qu'elle a ouvert,
                      // et rien de plus.
                    } else {
                      afficherErreur(
                          "Veuillez saisir le motif du retour de produit");
                    }
                  },
                  child: const Text(
                    "Envoyer la demande",
                    style: TextStyle(
                        fontSize: 14,
                        color: kPrimaryColor,
                        fontWeight: FontWeight.w700),
                  ))
            ],
          );
        });
  }
}
