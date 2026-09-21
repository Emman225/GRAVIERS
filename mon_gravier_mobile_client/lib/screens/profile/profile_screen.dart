import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/models/User.dart';
import 'package:mon_gravier_com/screens/commande/commande_screen.dart';
import 'package:mon_gravier_com/screens/edit_profil/edit_profile_screen.dart';
import 'package:mon_gravier_com/screens/modifier_mot_de_passe/modifier_pass_screen.dart';
import 'package:mon_gravier_com/screens/paiement/paiement_screen.dart';
import 'package:mon_gravier_com/screens/sign_in/sign_in_screen.dart';
import 'package:mon_gravier_com/screens/souhait/souhait_screen.dart';
import 'package:http/http.dart' as http;

import '../../components/bouton_retour.dart';
import '../../components/empty_user_widget.dart';
import '../../constants.dart';
import '../../globale.dart';
import '../client_a_terme/client_a_terme_screen.dart';
import '../devis/devis_screen.dart';
import '../facture/facture_screen.dart';
import '../facture_dgi/facture_dgi_screen.dart';
import 'components/profile_menu.dart';
import 'components/profile_pic.dart';

/// ESPACE CLIENT.
///
/// La page empilait neuf boutons gris identiques, tous de même poids : « Liste
/// des devis » avait exactement l'apparence de « Supprimer mon compte ». Le nom
/// du client, seul repère personnel de l'écran, était écrit dans la taille du
/// corps de texte sous sa photo, sans hiérarchie.
///
/// Les entrées sont désormais regroupées par nature — mon activité, mon compte,
/// puis les actions sensibles, distinguées — et l'identité du client ouvre
/// l'écran. Aucune entrée n'a été retirée ni déplacée dans son comportement.
class ProfileScreen extends StatefulWidget {
  static String routeName = "/profile";

  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  /// LA PHOTO MODIFIEE NE S'AFFICHAIT PAS ICI.
  ///
  /// Deux causes, toutes deux du cote de l'affichage — la photo etait bien
  /// enregistree, et bien renvoyee par le serveur :
  ///
  ///  · cet ecran est l'un des cinq onglets de la barre du bas, construits une
  ///    fois pour toutes. Revenir de « Mes informations » depile la route mais
  ///    ne reconstruit PAS l'onglet en dessous : il continuait d'afficher ce
  ///    qu'il avait dessine avant la modification ;
  ///
  ///  · et meme reconstruit, Flutter garde les images telechargees dans un
  ///    cache indexe PAR ADRESSE. Le serveur renvoyant la meme adresse pour la
  ///    nouvelle photo, c'est l'ancienne image qui ressortait du cache.
  ///
  /// On redessine donc au retour de l'ecran de modification, et l'ancienne
  /// image est retiree du cache au moment de l'enregistrement (voir
  /// edit_profile_form.dart).
  Future<void> _ouvrirModification() async {
    await Get.toNamed(EditProfileScreen.routeName);
    if (!mounted) return;
    setState(() {});
  }

  /// GLISSÉ VERS LE BAS (16/09/2026) : cet onglet est construit une fois pour
  /// toutes, ses montants (avance, à régler en agence, points) dataient du
  /// dernier passage par l'accueil. On les redemande au serveur et on redessine.
  Future<void> _actualiser() async {
    final motif = await rechargerTableauDeBord();
    if (!mounted) return;
    setState(() {});
    if (motif != null) {
      afficherInfo(motif);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bool connecte = user.token != null && user.token != "";

    return Scaffold(
      appBar: AppBar(
        title: const Text("Mon espace"),
        // Onglet de la barre du bas : rien à dépiler, le retour ramène à
        // l'accueil.
        leading: BoutonRetour(
          onTap: retourAccueil,
          tooltip: "Retour à l'accueil",
        ),
        automaticallyImplyLeading: false,
        actions: [
          if (connecte)
            IconButton(
              tooltip: "Modifier mon profil",
              onPressed: _ouvrirModification,
              icon: const Icon(Icons.edit_outlined),
            ),
          const SizedBox(width: kSpaceSm),
        ],
      ),
      body: SafeArea(
        child: !connecte
            ? const EmptyUserWidget()
            : RefreshIndicator(
                onRefresh: _actualiser,
                child: SingleChildScrollView(
                // Toujours défilable, sinon le glissé ne se déclenche pas quand
                // le contenu tient dans l'écran.
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.only(top: kSpaceLg, bottom: 96),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // ------------------------------------------ IDENTITÉ
                    Padding(
                      padding:
                          const EdgeInsets.symmetric(horizontal: kSpaceXl),
                      child: Container(
                        padding: const EdgeInsets.all(kSpaceLg),
                        decoration: BoxDecoration(
                          color: kSurfaceColor,
                          borderRadius: BorderRadius.circular(kRadiusMd),
                          border: Border.all(color: kBorderColor),
                        ),
                        child: Row(
                          children: [
                            // PAS DE `const` ICI, ET UNE CLÉ QUI SUIT
                            // L'ADRESSE DE LA PHOTO.
                            //
                            // C'est la cause que la correction précédente avait
                            // manquée : `const ProfilePic()` renvoie TOUJOURS
                            // la même instance, et Flutter court-circuite la
                            // reconstruction d'un enfant dont le widget est
                            // identique au précédent (Element.updateChild).
                            // L'écran avait beau se redessiner au retour de
                            // « Mes informations », cette vignette-ci, elle,
                            // n'était jamais reconstruite — elle continuait
                            // d'afficher l'ancienne photo.
                            ProfilePic(key: ValueKey(adressePhotoProfil())),
                            const SizedBox(width: kSpaceLg),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    user.nom.toString(),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: kTitreSectionStyle.copyWith(
                                        fontSize: 17),
                                  ),
                                  if (user.clientATerme == true) ...[
                                    const SizedBox(height: kSpaceSm),
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: kSpaceSm, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: kSuccessSoftColor,
                                        borderRadius:
                                            BorderRadius.circular(kRadiusPill),
                                      ),
                                      child: Text(
                                        "CLIENT À TERME",
                                        style: kEtiquetteStyle.copyWith(
                                            color: kSuccessColor),
                                      ),
                                    ),
                                  ],
                                  if (nombrePoint > 0) ...[
                                    const SizedBox(height: kSpaceSm),
                                    Text(
                                      "$nombrePoint point(s) de fidélité",
                                      style: kCorpsSecondaireStyle,
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    // ------------------------------------------ MON ARGENT
                    // Comme sur le site (10/09/2026) : l'avance disponible,
                    // toujours affichée même à 0, et ce qu'il reste à régler en
                    // agence, suivi à chaque versement au guichet.
                    const _TitreGroupe("Mon argent"),
                    Padding(
                      padding:
                          const EdgeInsets.symmetric(horizontal: kSpaceXl),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: _BlocMontant(
                              icone: Icons.savings_outlined,
                              couleur: kSuccessColor,
                              fond: kSuccessSoftColor,
                              valeur: formaterMontant(soldeAvance),
                              libelle: "Avance disponible",
                              detail: "Déduite de vos affaires réglées en agence",
                            ),
                          ),
                          const SizedBox(width: kSpaceMd),
                          Expanded(
                            child: _BlocMontant(
                              icone: Icons.storefront_outlined,
                              couleur: kWarningColor,
                              fond: kWarningSoftColor,
                              valeur: formaterMontant(creditsReste),
                              libelle: "À régler en agence",
                              detail: creditsDu >= 1
                                  ? "À régler ${formaterMontant(creditsDu)} · payé ${formaterMontant(creditsPaye)} · reste ${formaterMontant(creditsReste)}"
                                      "${creditsEnAttente >= 1 ? "\ndont ${formaterMontant(creditsEnAttente)} en attente de validation" : ""}"
                                  : "Rien à régler",
                              onTap: () => Get.toNamed(PaiementScreen.routeName),
                            ),
                          ),
                        ],
                      ),
                    ),

                    // ------------------------------------------ ACTIVITÉ
                    const _TitreGroupe("Mon activité"),

                    // Le compte à terme est réservé aux ENTREPRISES, comme sur le
                    // site (ClientController : « reserveEntreprise »). L'application
                    // proposait la démarche à tout le monde : un particulier
                    // remplissait le formulaire pour se voir refuser ensuite.
                    //
                    // La condition retient l'entreprise plutôt que d'écarter le
                    // particulier : toute autre valeur, ou une valeur absente,
                    // masque le bouton au lieu de l'afficher par défaut.
                    //
                    // Le type de client arrive dans « code_parrain » : le champ
                    // porte mal son nom côté API, mais c'est bien lui que le reste
                    // de l'application interroge déjà (panier, choix d'adresse).
                    if (user.clientATerme == false &&
                        user.code_parrain == ENTREPRISE)
                      ProfileMenu(
                        text: "Devenir client à terme",
                        icon: "assets/icons/home.svg",
                        press: () =>
                            Get.toNamed(DemandeClientATermeScreen.routeName),
                      ),
                    ProfileMenu(
                      text: "Mes commandes et locations",
                      icon: "assets/icons/commande.svg",
                      press: () {
                        afficheRetour = true;
                        Get.toNamed(CommandeScreen.routeName);
                      },
                    ),
                    ProfileMenu(
                      text: "Mes devis",
                      icon: "assets/icons/devis.svg",
                      press: () => Get.toNamed(DevisScreen.routeName),
                    ),
                    ProfileMenu(
                      text: "Mes paiements",
                      icon: "assets/icons/Cash.svg",
                      press: () => Get.toNamed(PaiementScreen.routeName),
                    ),
                    ProfileMenu(
                      // Les factures certifiées par la DGI et les avoirs (lot 95).
                      text: "Mes factures DGI",
                      icon: "assets/icons/facture.svg",
                      press: () => Get.toNamed(FactureDgiScreen.routeName),
                    ),
                    ProfileMenu(
                      // Écran des paiements en attente / effectués du site (lot 89).
                      text: "Paiements en attente / effectués",
                      icon: "assets/icons/facture.svg",
                      press: () => Get.toNamed(FactureScreen.routeName),
                    ),
                    ProfileMenu(
                      text: "Ma liste de souhaits",
                      icon: "assets/icons/Heart Icon.svg",
                      press: () => Get.toNamed(SouhaitScreen.routeName),
                    ),

                    // -------------------------------------------- COMPTE
                    const _TitreGroupe("Mon compte"),
                    ProfileMenu(
                      text: "Modifier mon mot de passe",
                      icon: "assets/icons/Settings.svg",
                      press: () => Get.toNamed(ModifierPasseScreen.routeName,
                          arguments: 1),
                    ),
                    ProfileMenu(
                      text: "Me déconnecter",
                      icon: "assets/icons/Log out.svg",
                      press: () => _deconnexion(),
                    ),
                    ProfileMenu(
                      text: "Supprimer mon compte",
                      icon: "assets/icons/Trash.svg",
                      destructif: true,
                      press: () async {
                        if (await confirmationAction(
                            context,
                            "Supprimer mon compte",
                            "Cette action est définitive. Voulez-vous vraiment supprimer votre compte ?")) {
                          _supprimerCompte();
                        }
                      },
                    ),

                    // Version affichée : quatre APK se sont succédé en une journée
                    // sans qu'on puisse savoir lequel tournait sur le téléphone, et
                    // chaque doute a coûté un aller-retour. Elle se lit maintenant
                    // à l'écran, sans passer par les réglages d'Android.
                    const Padding(
                      padding: EdgeInsets.only(top: kSpaceXl),
                      child: Text(
                        "Mon Gravier — version $versionApplication",
                        textAlign: TextAlign.center,
                        style: kLegendeStyle,
                      ),
                    ),
                  ],
                ),
                ),
              ),
      ),
    );
  }

  _deconnexion() {
    lireOuEcrireDonnee("token", '', 1);
    lireOuEcrireDonnee("type", '', 1);
    user = User();
    // Sans cela, le client suivant verrait la photo du précédent : elle est
    // retenue en mémoire, pas rattachée au compte.
    oublierPhotoProfilLocale();
    Get.offAllNamed(SignInScreen.routeName);
  }

  _supprimerCompte() async {
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
            .post(Uri.parse('${lienAPI()}suppression-compte-client'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        if (kDebugMode) {
          print(retourHttp.body);
        }
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            _deconnexion();
            Get.offAllNamed(SignInScreen.routeName);
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

/// Intitulé de groupe : sépare l'activité du client des réglages de son compte.
/// Une tuile de montant du tableau de bord (avance, crédits en agence).
class _BlocMontant extends StatelessWidget {
  const _BlocMontant({
    required this.icone,
    required this.couleur,
    required this.fond,
    required this.valeur,
    required this.libelle,
    required this.detail,
    this.onTap,
  });

  final IconData icone;
  final Color couleur;
  final Color fond;
  final String valeur;
  final String libelle;
  final String detail;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(kRadiusMd),
      child: Container(
        padding: const EdgeInsets.all(kSpaceMd),
        decoration: BoxDecoration(
          color: kSurfaceColor,
          borderRadius: BorderRadius.circular(kRadiusMd),
          border: Border.all(color: kBorderColor),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: fond,
                borderRadius: BorderRadius.circular(kRadiusMd),
              ),
              child: Icon(icone, color: couleur, size: 20),
            ),
            const SizedBox(height: kSpaceSm),
            Text(valeur,
                style: kTitreSectionStyle.copyWith(fontSize: 16, color: couleur),
                maxLines: 1,
                overflow: TextOverflow.ellipsis),
            Text(libelle, style: kCorpsSecondaireStyle),
            const SizedBox(height: 4),
            Text(detail,
                style: kCorpsSecondaireStyle.copyWith(fontSize: 11),
                maxLines: 4),
          ],
        ),
      ),
    );
  }
}

class _TitreGroupe extends StatelessWidget {
  const _TitreGroupe(this.texte);

  final String texte;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
          kSpaceXl, kSpaceXl, kSpaceXl, kSpaceSm),
      child: Text(
        texte.toUpperCase(),
        style: kEtiquetteStyle.copyWith(color: kTextMutedColor),
      ),
    );
  }
}
