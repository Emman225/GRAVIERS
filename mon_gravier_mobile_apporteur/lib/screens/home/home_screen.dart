import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:mon_gravier_com_apporteur/constants.dart';
import 'package:mon_gravier_com_apporteur/globale.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_apporteur/models/retour_home.dart';

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
      data: DataPaiement(statsList: [Stats(ceMois: 0, cetteAnnee: 0)]));
  Apporteur apporteur = Apporteur();
  List<Paiements> paiements = [];

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
            .post(Uri.parse('${lienAPI()}home-apporteur'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        if (kDebugMode) {
          print('home-apporteur status: ${retourHttp.statusCode}');
        }
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          if (kDebugMode) {
            print(datas);
          }
          final reponseHome = RetourHome.fromJson(datas);
          if (reponseHome.code == 200) {
            // retHome n'est remplace QU'EN cas de succes : sur une reponse d'erreur,
            // data vaut null et le prochain rebuild plantait sur retHome.data! .
            retHome = reponseHome;
            if (mounted) setState(() {
              paiements = retHome.data?.paiementsList ?? [];
              apporteur = retHome.apporteur ?? Apporteur();
              user.apporteur = apporteur;
            });
          } else {
            if (mounted) afficherErreur(retHome.message ?? '');
          }
        } else {
          if (mounted) afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e, stackTrace) {
        if (mounted) afficherErreur(
            "Une erreur s'est produite veuillez réessayer plus tard");
        if (kDebugMode) {
          print('Erreur home-apporteur: $e');
          print('StackTrace: $stackTrace');
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
      if (user.token != null && user.token != "") {
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
    final stats = retHome.data?.statsList;
    final Stats? premiere =
        (stats == null || stats.isEmpty) ? null : stats.first;

    return Scaffold(
      appBar: AppBar(
        title: const Text("Espace apporteur"),
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
              _tuilesCommissions(premiere),
              const SizedBox(height: kSpaceXl),
              _titreSection(),
              const SizedBox(height: kSpaceMd),
              if (paiements.isEmpty)
                _aucuneTransaction()
              else
                for (final p in paiements) _ligneReglement(p),
            ],
          ),
        ),
      ),
    );
  }

  /// SOLDE DISPONIBLE.
  ///
  /// C'était un pavé bleu où le nom de l'apporteur et son solde s'affichaient
  /// dans la MÊME taille, sur la même ligne, séparés par un simple espace — et
  /// l'œil, qui vient chercher un montant, ne trouvait rien à quoi s'accrocher.
  ///
  /// Le solde devient l'élément dominant de l'écran, ce qu'il est ; le nom
  /// passe au-dessus en surtitre, et l'œil qui masque le montant devient un
  /// bouton lisible plutôt qu'un pictogramme de 30 px posé au bord.
  Widget _blocSolde() {
    final double solde = user.apporteur?.solde?.toDouble() ?? 0;
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
              const Icon(Icons.account_circle_outlined,
                  size: 18, color: Color(0xCCFFFFFF)),
              const SizedBox(width: kSpaceSm),
              Expanded(
                child: Text(
                  nom.isEmpty || nom == 'null' ? "Apporteur d'affaires" : nom,
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
                      // Masquage : autant d'étoiles que de chiffres, pour que
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

  /// COMMISSIONS DU MOIS ET DE L'ANNÉE.
  ///
  /// C'étaient deux `Card` de couleur pleine — l'une ROUGE pâle, l'autre VERTE
  /// pâle — de 100 px de haut. Le rouge laissait croire à une anomalie, le vert
  /// à une réussite, alors que ce sont deux chiffres de même nature : un cumul
  /// sur un mois et un cumul sur une année.
  ///
  /// Deux tuiles identiques désormais, distinguées par leur seul libellé, avec
  /// le montant en évidence.
  Widget _tuilesCommissions(Stats? stats) {
    return Row(
      children: [
        Expanded(
          child: _tuile(
            icone: Icons.calendar_month_outlined,
            libelle: "CE MOIS",
            montant: stats?.ceMois?.toDouble() ?? 0,
          ),
        ),
        const SizedBox(width: kSpaceMd),
        Expanded(
          child: _tuile(
            icone: Icons.stacked_line_chart_outlined,
            libelle: "CETTE ANNÉE",
            montant: stats?.cetteAnnee?.toDouble() ?? 0,
          ),
        ),
      ],
    );
  }

  Widget _tuile({
    required IconData icone,
    required String libelle,
    required double montant,
  }) {
    return Container(
      padding: const EdgeInsets.all(kSpaceLg),
      decoration: BoxDecoration(
        color: kSurfaceColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
        border: Border.all(color: kBorderFortColor),
        boxShadow: kShadowCarte,
      ),
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
                  color: kPrimarySoftColor,
                  borderRadius: BorderRadius.circular(kSpaceSm),
                ),
                alignment: Alignment.center,
                child: Icon(icone, size: 16, color: kPrimaryColor),
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
          Text(
            formaterMontant(montant),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: kMontantStyle.copyWith(fontSize: 18),
          ),
        ],
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
        if (paiements.isNotEmpty)
          Container(
            padding:
                const EdgeInsets.symmetric(horizontal: kSpaceMd, vertical: 4),
            decoration: BoxDecoration(
              color: kPrimarySoftColor,
              borderRadius: BorderRadius.circular(kRadiusPill),
            ),
            child: Text(
              "${paiements.length}",
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
  /// l'on vient chercher — n'y était pas plus visible que la date.
  ///
  /// Elle reprend la carte commune à toutes les applications : montant
  /// dominant, mention, date, et l'état du règlement en pastille.
  Widget _ligneReglement(Paiements d) {
    final bool regle = d.paye == 1;
    // « Compte » n'a de sens que pour une demande de retrait, où l'apporteur
    // indique où il veut être payé. Une commission réglée par un gestionnaire
    // n'en a pas : la ligne affichait « Compte: null » puis « Compte: - ».
    final String reference = (d.numeroCompte ?? '').trim();
    final bool aUneReference = reference.isNotEmpty && reference != '-';

    return Padding(
      padding: const EdgeInsets.only(bottom: kSpaceMd),
      child: CarteOperation(
        icone: regle
            ? Icons.check_circle_outline
            : Icons.hourglass_bottom_outlined,
        numero: aUneReference
            ? "${regle ? 'Référence' : 'Compte'} $reference"
            : "Règlement",
        montant: formaterMontant(d.montant?.toDouble() ?? 0),
        mention: "${d.modePaiement}",
        date: "${d.dateDemande}",
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
      message: "Vos commissions réglées apparaîtront ici, avec leur date et "
          "leur moyen de paiement.",
    );
  }
}
