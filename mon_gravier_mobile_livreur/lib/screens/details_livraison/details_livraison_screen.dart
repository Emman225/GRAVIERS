import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/helper/constants.dart';
import 'package:mon_gravier_com_livreur/models/details_livraison.dart';
import 'package:mon_gravier_com_livreur/models/retour_livraison.dart';
import 'package:mon_gravier_com_livreur/screens/livraison/components/livraison_effectuee_screen.dart';

import '../../../components/bouton_retour.dart';
import '../../../components/code_partageable.dart';
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

  /// Vrai pendant un appel réseau d'acceptation ou de refus. Empêche la DOUBLE
  /// ACTION : sur le terrain, un livreur qui ne voyait pas de confirmation
  /// retapait le bouton et une seconde requête partait.
  bool _actionEnCours = false;

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

      // try/catch RÉACTIVÉ : sans lui, une erreur API (corps non-JSON, 500…) faisait
      // échouer jsonDecode sans capture -> fermerChargement() jamais atteint ->
      // "Patientez" infini. On capture, on affiche l'erreur et on ferme le loader.
      try {
        final http.Response retourHttp = await http
            .post(Uri.parse('${lienAPI()}details-livraison/${livraison.id}'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          if (kDebugMode) {
            print(datas);
          }
          retLiv = DetailsLivraison.fromJson(datas);
          if (retLiv.code == 200) {
            setState(() {
              ligneCommande = retLiv.data?.ligneCommande ?? LigneCommande();
              ligneLivraison = retLiv.data?.ligneLivraison ?? LigneLivraison();
            });
          } else {
            afficherErreur(retLiv.message ?? '');
          }
        } else {
          afficherErreur(
              "Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer plus tard.");
        }
      } catch (e) {
        afficherErreur(
            "Une erreur s'est produite veuillez réessayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      if (mounted) setState(() => _actionEnCours = false);
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
      determinePosition();
    });
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Détails livraison et décision livraison",
        ),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      bottomNavigationBar: livraison.etatLivraison == LIVRAISON_LIVREE ? null : Padding(
        padding: const EdgeInsets.all(15.0),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
        Row(
          children: [
            Expanded(
              child: ElevatedButton(
                  style: ButtonStyle(
                      backgroundColor:
                          MaterialStateProperty.all(Colors.blueAccent)),
                  onPressed: () {
                    // Position indisponible (GPS coupé ou refusé, cas fréquent en
                    // extérieur) : l'ancien code envoyait « origin=null,null » à
                    // Google Maps. On part alors de la position actuelle du téléphone
                    // en n'indiquant que la destination.
                    final fin = '${livraison.latitude},${livraison.longitude}';
                    final String url = position == null
                        ? "https://www.google.com/maps/dir/?api=1&destination=$fin"
                        : "https://www.google.com/maps/dir/?api=1&origin=${position!.latitude},${position!.longitude}&destination=$fin";
                    if (kDebugMode) {
                      print(url);
                    }
                    lancerIfram(context,url);
                  },
                  child: const Text("Itinéraire")),
            ),
            addHorizontalSpace(10),
            if (livraison.etatLivraison == LIVRAISON_EN_ATTENTE) ...[
              Expanded(
                child: ElevatedButton(
                    style: ButtonStyle(
                        backgroundColor:
                        MaterialStateProperty.all(Colors.blueAccent)),
                    onPressed: _actionEnCours ? null : () => _accordLivraison(),
                    child: const Text("Accepter livraison")),
              ),
            ],
            if (livraison.etatLivraison == LIVRAISON_EN_TRAITEMENT ||
                livraison.etatLivraison == LIVRAISON_EN_COURS) ...[
              Expanded(
                child: ElevatedButton(
                    style: ButtonStyle(
                        backgroundColor:
                        MaterialStateProperty.all(Colors.blueAccent)),
                    onPressed: () => Get.toNamed(LivraisonEffectueeScreen.routeName, arguments: livraison),
                    child: const Text("Finaliser la livraison")),
              ),
            ],
          ],
        ),
        // « Refuser » vient EN DESSOUS des autres actions, sur toute la
        // largeur : il flottait auparavant au milieu de l'écran, par-dessus le
        // contenu, et se confondait avec les boutons du bas.
        if (livraison.etatLivraison == LIVRAISON_EN_ATTENTE) ...[
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: redColor,
                side: const BorderSide(color: redColor),
              ),
              onPressed: _actionEnCours ? null : () => _refusLivraison(),
              icon: const Icon(Icons.close),
              label: const Text('Refuser la livraison'),
            ),
          ),
        ],
          ],
        ),
      ),
      body: ListView(children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 50),
          decoration: BoxDecoration(
            color: kSurfaceMutedColor,
            borderRadius: BorderRadius.circular(15),
          ),
          child: Image.network(ligneCommande.image ??
              'https://img.freepik.com/vecteurs-premium/fond-conception-banniere-large-3d-abstrait-bleu-diamant-brillant_181182-21825.jpg'),
        ),
        Padding(
          padding: const EdgeInsets.all(15.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // LE BON D'ENLÈVEMENT D'ABORD : c'est ce que le livreur remet au
              // fournisseur. Copiable, partageable par WhatsApp (08/09/2026).
              if (livraison.accepte == 1 &&
                  ('${livraison.code_enlevement ?? ''}').trim().isNotEmpty) ...[
                CodePartageable(
                  libelle: 'Numéro bon enlèvement',
                  valeur: '${livraison.code_enlevement}'.trim(),
                ),
                const SizedBox(height: kSpaceMd),
              ],
              // LIBELLÉ ET VALEUR SE DISTINGUENT : le libellé en petites
              // capitales grises, la valeur en gras, une ligne par information.
              _Fiche(lignes: [
                _Info('Article', '${ligneCommande.nom ?? ''}'),
                _Info(
                  livraison.etatLivraison == LIVRAISON_LIVREE
                      ? 'Quantité livrée'
                      : 'Quantité à livrer',
                  '$qteLivree ${ligneCommande.unite ?? ''}',
                  couleur: kPrimaryColor,
                ),
                _Info('Date de livraison',
                    formaterDate(livraison.dateLivraison ?? 'dd/MM/yyyy'),
                    couleur: kErrorColor),
                _Info('Coût de la livraison',
                    formaterMontant(livraison.coutLivraison?.toDouble() ?? 0),
                    couleur: kPrimaryMidColor),
                if (livraison.accepte == 1)
                  _Info('Client',
                      '${livraison.nomClient ?? ''} - ${livraison.contactClient ?? ''}',
                      couleur: kSuccessColor),
                if (livraison.accepte == 1 &&
                    ('${livraison.nom_fournisseur ?? ''}').trim().isNotEmpty)
                  _Info('Fournisseur', '${livraison.nom_fournisseur}'),
                if (livraison.accepte == 1 &&
                    ('${livraison.tel_fournisseur ?? ''}').trim().isNotEmpty)
                  _Info('Téléphone Fournisseur', '${livraison.tel_fournisseur}'),
                if (('${livraison.adresse_fournisseur ?? ''}').trim().isNotEmpty)
                  _Info('Adresse Fournisseur', '${livraison.adresse_fournisseur}'),
                _Info('Adresse de livraison', '${livraison.lieuAffiche}'),
              ]),
              const SizedBox(height: 70),
            ],
          ),
        ),
      ]),
    );
  }

  _refusLivraison() async {
    return showDialog(
        barrierDismissible: true,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            title: const Text("Refuser la livraison"),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            elevation: 5.0,
            content: SizedBox(
              height: 200,
              child: Column(
                children: [
                  const SizedBox(height: 10),
                  Text(
                    "Attention !!! \nVous êtes sur le point de refuser la livraison \nVoulez-vous continuer ?",
                    style: red14MediumTextStyle,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () {
                      Get.back();
                      refuserLivraison();
                    },
                    child: const Text("Refuser la livraison"),
                  ),
                ],
              ),
            ),
          );
        });
  }

  _accordLivraison() async {
    return showDialog(
        barrierDismissible: true,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            title: const Text("Accepter la livraison"),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            elevation: 5.0,
            content: SizedBox(
              height: 250,
              child: Column(
                children: [
                  const SizedBox(height: 10),
                  Text(
                    "Attention !!! \nVous êtes sur le point d'accepter la livraison \nVoulez-vous continuer ?",
                    style: red14MediumTextStyle,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () {
                      Get.back();
                      effectuerLivraison();
                    },
                    child: const Text("Accepter la livraison"),
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () {
                      Get.back();
                    },
                    child: const Text("Fermer le fenêtre"),
                  ),
                ],
              ),
            ),
          );
        });
  }

  effectuerLivraison() async {
    // Verrou anti double action : une seconde tape pendant l'appel reseau
    // envoyait une deuxieme requete (double acceptation / double refus).
    if (_actionEnCours) return;
    if (await verifierConnexion()) {
      setState(() => _actionEnCours = true);
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "idLivraison": livraison.id,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        final http.Response retourHttp = await http
            .post(Uri.parse('${lienAPI()}accepter-livraison'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            setState(() {
              livraison = UneLivraison.fromJson(datas['data']);
            });
            afficherSucces(datas['message']);
            // La course est acceptée : on revient à la liste, qui se
            // recharge au retour. Rester ici laissait le livreur sur un
            // écran dont les boutons venaient de changer.
            _retourALaListe();
            return;
          } else {
            afficherErreur(datas['message']);
          }
        } else {
          // Sans cette branche, une reponse serveur en erreur ne produisait
          // AUCUNE reaction a l'ecran.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez reessayer.");
        }
      } catch (e) {
        afficherErreur(
            "Une erreur s'est produite veuillez reesayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      if (mounted) setState(() => _actionEnCours = false);
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  /// Ferme l'indicateur de chargement puis quitte l'écran ; la liste
  /// précédente se recharge d'elle-même au retour.
  void _retourALaListe() {
    fermerChargement();
    if (mounted) setState(() => _actionEnCours = false);
    if (mounted) Get.back(result: true);
  }

  refuserLivraison() async {
    // Verrou anti double action : une seconde tape pendant l'appel reseau
    // envoyait une deuxieme requete (double acceptation / double refus).
    if (_actionEnCours) return;
    if (await verifierConnexion()) {
      setState(() => _actionEnCours = true);
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "idLivraison": livraison.id,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        final http.Response retourHttp = await http
            .post(Uri.parse('${lienAPI()}refuser-livraison'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            setState(() {
              livraison = UneLivraison.fromJson(datas['data']);
            });
            afficherSucces(datas['message']);
            // Course refusée : elle n'est plus à proposer, on revient à la liste.
            _retourALaListe();
            return;
          } else {
            afficherErreur(datas['message']);
          }
        } else {
          // Sans cette branche, une reponse serveur en erreur ne produisait
          // AUCUNE reaction a l'ecran.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez reessayer.");
        }
      } catch (e) {
        afficherErreur(
            "Une erreur s'est produite veuillez reesayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      if (mounted) setState(() => _actionEnCours = false);
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }
}


/// Une information de la fiche : un libellé et sa valeur.
class _Info {
  final String libelle;
  final String valeur;
  final Color couleur;

  const _Info(this.libelle, this.valeur, {this.couleur = kTextColor});
}

/// La fiche : une carte, une ligne par information, le libellé au-dessus de
/// la valeur pour que les deux ne se confondent jamais.
class _Fiche extends StatelessWidget {
  final List<_Info> lignes;

  const _Fiche({required this.lignes});

  @override
  Widget build(BuildContext context) {
    final visibles = lignes.where((l) => l.valeur.trim().isNotEmpty).toList();
    return Container(
      decoration: BoxDecoration(
        color: kSurfaceColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
        border: Border.all(color: kBorderColor),
        boxShadow: kShadowSoft,
      ),
      child: Column(
        children: [
          for (int i = 0; i < visibles.length; i++) ...[
            if (i > 0)
              const Divider(height: 1, thickness: 1, color: kBorderColor),
            Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: kSpaceMd, vertical: kSpaceSm + 2),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    visibles[i].libelle.toUpperCase(),
                    style: const TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 0.6,
                      color: kTextMutedColor,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    visibles[i].valeur,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: visibles[i].couleur,
                      height: 1.25,
                    ),
                    softWrap: true,
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
