import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:mon_gravier_com_livreur/constants.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_livreur/models/retour_home.dart';
import 'package:mon_gravier_com_livreur/models/retour_liste_demande_paiement.dart';

import '../../components/carte_operation.dart';
import '../../components/etat_vide.dart';
import '../../models/User.dart';

class HomeScreen extends StatefulWidget {
  static String routeName = "/home";

  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {

  bool afficheSolde = true;
  RetourHome retHome = RetourHome(
    data: DataRetourHome(
      demandePaiement: [],
      livreur: Livreur(),
      stats: [Stats(attente: 0, livree: 0)]
    )
  );
  Livreur livreur = Livreur();
  List<DemandePaiement> demandes = [];

  chargerHome({bool sansLoader = false}) async {
    if (await verifierConnexion()) {

      if (sansLoader == false) {
        afficherChargement();
      }

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        final http.Response retourHttp = await http
            .post(Uri.parse('${lienAPI()}home-livreur'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (kDebugMode) {
          print(datas);
        }
        if (retourHttp.statusCode == 200) {
          final reponseHome = RetourHome.fromJson(datas);
          if (reponseHome.code == 200) {
            // retHome n'est remplace QU'EN cas de succes : sur une reponse d'erreur,
            // data vaut null et le prochain rebuild plantait sur retHome.data! .
            retHome = reponseHome;
            if (mounted) setState(() {
              demandes = retHome.data?.demandePaiement ?? [];
              livreur = retHome.data?.livreur ?? Livreur();
              user.livreur = livreur;
            });
          } else {
            if (mounted) afficherErreur(retHome.message ?? '');
          }
        } else {
          // Sans cette branche, une reponse serveur en erreur ne produisait
          // AUCUNE reaction a l'ecran.
          if (mounted) afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez reessayer.");
        }
      } catch (e) {
        if (mounted) afficherErreur(
            "Une erreur s'est produite veuillez reesayer plus tard");
        if (kDebugMode) {
          print(e.toString());
        }
      }
      if (sansLoader == false) {
        fermerChargement();
      }
    } else {
      if (mounted) afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    // TODO: implement initState
    super.initState();

    // L'ACCUEIL SE LAISSE RECHARGER DE L'EXTERIEUR.
    //
    // Depuis que les onglets restent vivants, y revenir ne recharge
    // plus : ses chiffres resteraient ceux d'avant l'action faite
    // ailleurs. Silencieux : l'accueil n'est meme pas a l'ecran.
    rafraichirAccueil = () async {
      if (mounted) await chargerHome(sansLoader: true);
    };

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if(user.token != null && user.token != ""){
        chargerHome();
      }
    });
  }

  @override
  void dispose() {
    // On ne laisse pas un point d'entree pointer sur un ecran detruit.
    rafraichirAccueil = null;
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final listeStats = retHome.data?.stats;
    final bool aDesStats = listeStats != null && listeStats.isNotEmpty;
    final int enAttente = aDesStats ? (listeStats.first.attente ?? 0) : 0;
    final int effectuees = aDesStats ? (listeStats.first.livree ?? 0) : 0;

    return Scaffold(
      appBar: AppBar(
        title: const Text("Espace livreur"),
        elevation: 0,
        centerTitle: true,
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: RefreshIndicator(
          color: kPrimaryColor,
          onRefresh: () => chargerHome(sansLoader: true),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
                parent: BouncingScrollPhysics()),
            padding: const EdgeInsets.fromLTRB(
                kSpaceLg, kSpaceLg, kSpaceLg, kSpaceXxl),
            children: [
              _blocSolde(),
              const SizedBox(height: kSpaceLg),
              _tuilesCourses(enAttente, effectuees),
              const SizedBox(height: kSpaceXl),
              _titreSection(),
              const SizedBox(height: kSpaceMd),
              if (demandes.isEmpty)
                _aucuneTransaction()
              else
                for (final d in demandes) _ligneReglement(d),
            ],
          ),
        ),
      ),
    );
  }

  /// SOLDE DISPONIBLE.
  ///
  /// C'était un pavé bleu où le nom du livreur et son solde s'affichaient dans
  /// la MÊME taille, sur deux lignes voisines — et l'œil, qui vient chercher un
  /// montant, ne trouvait rien à quoi s'accrocher.
  ///
  /// Le solde devient l'élément dominant de l'écran, ce qu'il est ; le nom
  /// passe au-dessus en surtitre, et l'œil qui masque le montant devient un
  /// bouton lisible plutôt qu'un pictogramme de 30 px posé au bord.
  Widget _blocSolde() {
    final double solde = user.livreur?.solde?.toDouble() ?? 0;
    final String nom = user.nom?.toString().trim() ?? '';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(kSpaceXl),
      decoration: BoxDecoration(
        gradient: kBandeauGradient,
        borderRadius: BorderRadius.circular(kRadiusLg),
        boxShadow: kShadowCarte,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.local_shipping_outlined,
                  size: 18, color: Color(0xCCFFFFFF)),
              const SizedBox(width: kSpaceSm),
              Expanded(
                child: Text(
                  nom.isEmpty || nom == 'null' ? "Livreur" : nom,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xCCFFFFFF),
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: kSpaceLg),
          Text(
            "SOLDE DISPONIBLE",
            style: kEtiquetteStyle.copyWith(color: const Color(0x99FFFFFF)),
          ),
          const SizedBox(height: kSpaceSm),
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Expanded(
                child: Text(
                  afficheSolde
                      ? formaterMontant(solde)
                      // Masquage : autant de points que de chiffres, pour que
                      // la ligne ne saute pas quand on l'affiche.
                      : '•' * solde.toStringAsFixed(0).length,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.w700,
                    height: 1.1,
                    letterSpacing: -0.5,
                    fontFeatures: [FontFeature.tabularFigures()],
                  ),
                ),
              ),
              const SizedBox(width: kSpaceMd),
              Material(
                color: kChipSurAppBar,
                shape: const CircleBorder(),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: () => setState(() => afficheSolde = !afficheSolde),
                  child: SizedBox(
                    height: 42,
                    width: 42,
                    child: Icon(
                      afficheSolde
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// COURSES EN ATTENTE ET COURSES EFFECTUÉES.
  ///
  /// C'étaient deux `Card` de couleur pleine — l'une ROUGE pâle, l'autre VERTE
  /// pâle. Le rouge laissait croire à une anomalie alors qu'une course en
  /// attente est le cas NORMAL du métier : c'est du travail à faire, pas un
  /// incident.
  ///
  /// Deux tuiles de même forme, distinguées par une teinte d'ÉTAT — ambre pour
  /// ce qui reste à faire, vert pour ce qui est fait — et le nombre en évidence.
  Widget _tuilesCourses(int enAttente, int effectuees) {
    return Row(
      children: [
        Expanded(
          child: _tuile(
            icone: Icons.pending_actions_outlined,
            libelle: "EN ATTENTE",
            valeur: enAttente,
            couleur: kWarningColor,
            fond: kWarningSoftColor,
            onTap: () => ouvrirLivraisons(ONGLET_LIVRAISON_EN_ATTENTE),
          ),
        ),
        const SizedBox(width: kSpaceMd),
        Expanded(
          child: _tuile(
            icone: Icons.task_alt_outlined,
            libelle: "EFFECTUÉES",
            valeur: effectuees,
            couleur: kSuccessColor,
            fond: kSuccessSoftColor,
            onTap: () => ouvrirLivraisons(ONGLET_LIVRAISON_EFFECTUEE),
          ),
        ),
      ],
    );
  }

  Widget _tuile({
    required IconData icone,
    required String libelle,
    required int valeur,
    required Color couleur,
    required Color fond,
    VoidCallback? onTap,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: kSurfaceColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
        border: Border.all(color: kBorderFortColor),
        boxShadow: kShadowCarte,
      ),
      // Le rognage est indispensable : sans lui, l'ondulation du toucher
      // deborderait les coins arrondis de la tuile.
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(kSpaceLg),
            child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Container(
                height: 30,
                width: 30,
                decoration: BoxDecoration(
                  color: fond,
                  borderRadius: BorderRadius.circular(kSpaceSm),
                ),
                alignment: Alignment.center,
                child: Icon(icone, size: 16, color: couleur),
              ),
              const SizedBox(width: kSpaceSm),
              Expanded(
                child: Text(
                  libelle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: kEtiquetteStyle.copyWith(
                      color: kTextMutedColor, fontSize: 10),
                ),
              ),
            ],
          ),
          const SizedBox(height: kSpaceMd),
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                "$valeur",
                style: kMontantFortStyle.copyWith(fontSize: 24),
              ),
              const SizedBox(width: kSpaceXs),
              Text(
                valeur > 1 ? "courses" : "course",
                style: kLegendeStyle,
              ),
            ],
          ),
        ],
            ),
          ),
        ),
      ),
    );
  }

  /// TITRE DE SECTION.
  ///
  /// Il était centré, en ROUGE, dans un corps de 18 px : il ressemblait à une
  /// alerte plutôt qu'à un intitulé. Il s'aligne à gauche comme tous les autres
  /// titres de l'application, et porte le nombre de règlements.
  Widget _titreSection() {
    return Row(
      children: [
        const Expanded(
          child: Text("Paiements effectués", style: kTitreSectionStyle),
        ),
        if (demandes.isNotEmpty)
          Container(
            padding:
                const EdgeInsets.symmetric(horizontal: kSpaceMd, vertical: 4),
            decoration: BoxDecoration(
              color: kPrimarySoftColor,
              borderRadius: BorderRadius.circular(kRadiusPill),
            ),
            child: Text(
              "${demandes.length}",
              style: kEtiquetteStyle.copyWith(color: kPrimaryColor),
            ),
          ),
      ],
    );
  }

  /// UN RÈGLEMENT.
  ///
  /// La carte alignait quatre lignes de la forme « Étiquette: valeur », toutes
  /// dans le même corps et la même couleur, dans un cadre à coins arrondis de
  /// 30 px cerclé de blanc sur du blanc. Le montant — la seule information que
  /// l'on vient chercher — n'y était pas plus visible que la date. « Compte »
  /// s'affichait de plus même quand il n'y avait rien à montrer.
  Widget _ligneReglement(DemandePaiement d) {
    final bool regle = d.paye == true;
    final String compte = (d.numero_compte ?? '').trim();
    final bool aUnCompte =
        compte.isNotEmpty && compte != '-' && compte != 'null';

    return Padding(
      padding: const EdgeInsets.only(bottom: kSpaceMd),
      child: CarteOperation(
        icone: regle
            ? Icons.check_circle_outline
            : Icons.hourglass_bottom_outlined,
        numero: aUnCompte ? "Compte $compte" : "Règlement",
        montant: formaterMontant(d.montant?.toDouble() ?? 0),
        mention: "${d.modePaiement}",
        date: "${d.date_demande}",
        statut: regle ? "Réglé" : "En attente",
        couleurStatut: regle ? kSuccessColor : kWarningColor,
        fondStatut: regle ? kSuccessSoftColor : kWarningSoftColor,
      ),
    );
  }

  Widget _aucuneTransaction() {
    return const EtatVide(
      compact: true,
      icone: Icons.receipt_long_outlined,
      titre: "Aucun règlement",
      message: "Vos courses réglées apparaîtront ici, avec leur date et leur "
          "moyen de paiement.",
    );
  }
}
