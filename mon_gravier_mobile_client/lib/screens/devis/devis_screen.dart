import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/empty_user_widget.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/devis.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../components/bouton_retour.dart';
import '../../components/etat_vide.dart';
import '../../components/carte_operation.dart';
import '../../constants.dart';
import '../../helper/constants.dart';
import '../details_devis/details_devis_screen.dart';

class DevisScreen extends StatefulWidget {
  static String routeName = "/devis";

  const DevisScreen({super.key});

  @override
  State<DevisScreen> createState() => DevisScreenState();
}

class DevisScreenState extends State<DevisScreen> {
  // Deux listes séparées : les devis encore ouverts, sur lesquels le client
  // peut agir, et l'historique de ceux déjà transformés en commande, qui ne se
  // consultent plus que pour mémoire.
  List<DataDevis> devisEnAttente = [];
  List<DataDevis> devisPasses = [];
  RetourListeDevis retourDevis = RetourListeDevis();

  static const int statutEnAttente = 1;
  static const int statutPasseEnCommande = 2;

  /// Récupère les devis d'un statut donné. Retourne null si l'appel a échoué,
  /// pour ne pas vider une liste déjà affichée sur une erreur passagère.
  Future<List<DataDevis>?> _recupererDevis(int statut) async {
    var param = {
      "access": user.token.toString(),
      "type": user.type.toString(),
      "statut": statut.toString(),
    };

    if (kDebugMode) {
      print(param);
    }

    try {
      retourHttp = await http
          .post(Uri.parse('${lienAPI()}liste-devis'),
              headers: {"Content-Type": "application/json"},
              body: jsonEncode(param))
          .timeout(const Duration(minutes: 2));
      var datas = jsonDecode(retourHttp.body);
      if (kDebugMode) {
        print(datas);
      }
      if (retourHttp.statusCode == 200) {
        retourDevis = RetourListeDevis.fromJson(datas);
        if (retourDevis.code == 200) {
          return retourDevis.data ?? [];
        }
        afficherErreur(retourDevis.message ?? '');
        return null;
      }
      // Sans cette branche, une réponse serveur en erreur ne produisait
      // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
      afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
      return null;
    } catch (e) {
      afficherErreur(messageErreurTechnique(e));
      if (kDebugMode) {
        print(e.toString());
      }
      return null;
    }
  }

  chargerDevis() async {
    if (await verifierConnexion()) {
      // Un seul voile de chargement pour les deux appels : deux voiles
      // superposés laisseraient le second ouvert après fermeture du premier.
      afficherChargement();

      final ouverts = await _recupererDevis(statutEnAttente);
      final passes = await _recupererDevis(statutPasseEnCommande);

      if (ouverts != null) devisEnAttente = ouverts;
      if (passes != null) devisPasses = passes;

      // Second garde-fou : l'écran peut aussi être quitté pendant le
      // chargement. Les listes restent renseignées ; seul l'affichage est
      // conditionné à sa présence.
      if (mounted) setState(() {});

      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (user.token != null && user.token != '') {
        chargerDevis();
      }
    });
  }

  List<Widget> pages = [];

  /// Carte d'un devis. « actionnable » distingue les devis encore ouverts, qui
  /// mènent au détail, de l'historique : ouvrir un devis déjà transformé
  /// donnerait accès à « Supprimer » et « Charger panier », deux actions qui
  /// n'ont plus de sens à ce stade.
  Widget _carteDevis(DataDevis c, {required bool actionnable}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: kSpaceMd),
      child: CarteOperation(
        icone: Icons.request_quote_outlined,
        numero: "Devis n° ${c.numero}",
        montant: formaterMontant(c.montant?.toDouble() ?? 0),
        mention: "${c.libelle}",
        date: "${c.dateDevis}",
        // Un devis déjà transformé porte son état ; un devis ouvert n'en a pas
        // besoin, il est simplement en attente.
        statut: actionnable ? null : "Passé en commande",
        couleurStatut: actionnable ? kPrimaryColor : kSuccessColor,
        fondStatut: actionnable ? kPrimarySoftColor : kSuccessSoftColor,
        // Supprimer un devis en attente sans ouvrir son détail (10/09/2026) :
        // la corbeille sur la carte, avec confirmation. Le serveur refuse un
        // devis déjà transformé ou rattaché à une commande, et dit pourquoi.
        onSupprimer: !actionnable ? null : () => _supprimerDevis(c),

        // L'ACTION EST PORTÉE PAR LA CARTE.
        //
        // Elle l'était par un `GestureDetector` qui ENVELOPPAIT la carte,
        // laquelle avait reçu un `onTap: () {}` vide en attendant. Un InkWell
        // muni d'une action, même sans effet, absorbe le geste : ouvrir un
        // devis ne faisait plus rien depuis la refonte de cette liste.
        //
        // `null` sur l'historique : un devis déjà transformé ne mène nulle
        // part, et la carte cesse alors de réagir — ce que l'ancien code
        // exprimait en n'enveloppant pas la carte.
        onTap: !actionnable
            ? null
            : () async {
                await Get.toNamed(DetailsDevisScreen.routeName, arguments: c);
                // Cette attente ne se termine pas seulement quand
                // l'utilisateur revient du détail : « Aller à l'accueil »,
                // après la transformation du devis en commande, retire TOUS
                // les écrans de la pile — et celui-ci se réveille alors qu'il
                // n'existe plus.
                //
                // Recharger dans cet état lançait un appel dont la réponse ne
                // pouvait plus rien rafraîchir : l'échec remontait au catch,
                // qui affichait une erreur par-dessus l'accueil tout juste
                // ouvert. C'est l'erreur qui suivait chaque devis passé en
                // commande, et que rien ne rattachait à cet écran-ci.
                if (mounted) {
                  chargerDevis();
                }
              },
      ),
    );
  }

  /// Suppression d'un devis depuis la liste : même appel que le détail
  /// (supprimer-devis), puis rechargement des deux listes.
  Future<void> _supprimerDevis(DataDevis c) async {
    if (!await confirmationAction(
        context, "Attention !", "Voulez-vous supprimer le devis n° ${c.numero} ?")) {
      return;
    }
    if (!await verifierConnexion()) {
      afficherInfo("Veuillez vérifier votre connexion internet");
      return;
    }
    afficherChargement();
    try {
      retourHttp = await http
          .post(Uri.parse('${lienAPI()}supprimer-devis/${c.id}'),
              headers: {"Content-Type": "application/json"},
              body: jsonEncode({
                "access": user.token.toString(),
                "type": user.type.toString(),
              }))
          .timeout(const Duration(minutes: 2));
      var datas = jsonDecode(retourHttp.body);
      if (retourHttp.statusCode == 200 && datas['code'] == 200) {
        afficherSucces(datas['message'] ?? 'Devis supprimé');
      } else if (retourHttp.statusCode == 200) {
        afficherErreur(datas['message'] ?? '');
      } else {
        afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
      }
    } catch (e) {
      afficherErreur(messageErreurTechnique(e));
    }
    fermerChargement();
    if (mounted) chargerDevis();
  }

  /// Liste défilante et cherchable d'un jeu de devis.
  Widget _liste(List<DataDevis> source, {required bool actionnable, required String messageVide}) {
    if (source.isEmpty) {
      return EtatVide(
        compact: true,
        icone: Icons.request_quote_outlined,
        titre: "Aucun devis",
        message: messageVide,
      );
    }

    return Padding(
      padding: const EdgeInsets.all(15.0),
      child: SearchableList<DataDevis>(
        searchFieldEnabled: true,
        shrinkWrap: true,
        autoFocusOnSearch: false,
        sortWidget: const Icon(Icons.sort),
        sortPredicate: (a, b) {
          double mtna = a.montant ?? 0;
          double mtnb = b.montant ?? 0;
          return mtna.compareTo(mtnb);
        },
        physics: const BouncingScrollPhysics(),
        builder: (liste, index, c) => _carteDevis(c, actionnable: actionnable),
        initialList: source,
        filter: (p0) {
          return source
              .where((c) => (c.dateDevis.toString().contains(p0) ||
                  c.libelle.toString().contains(p0) ||
                  c.montant.toString().contains(p0)))
              .toList();
        },
        inputDecoration: const InputDecoration(
          hintText: "Rechercher un devis...",
          floatingLabelBehavior: FloatingLabelBehavior.never,
          prefixIcon: Icon(Icons.search, size: 20),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
      appBar: AppBar(
        title: const Text("Mes devis"),
        elevation: 0,
        // Onglets posés DANS la barre de titre, devenue navy : un libellé au
        // bleu de marque et un indicateur de la même couleur y disparaissaient.
        bottom: TabBar(
          labelColor: Colors.white,
          unselectedLabelColor: const Color(0x99FFFFFF),
          indicatorColor: Colors.white,
          indicatorWeight: 2.5,
          tabs: [
            Tab(text: "En attente (${devisEnAttente.length})"),
            Tab(text: "Historique (${devisPasses.length})"),
          ],
        ),
        leading: const BoutonRetour(),
      ),
      body: SafeArea(
        child: (user.token == null || user.token == "")
            ? const EmptyUserWidget()
            : Container(
                width: double.infinity,
                height: heightOfScreen(context),
                child: TabBarView(
                  children: [
                    _liste(
                      devisEnAttente,
                      actionnable: true,
                      messageVide: "Vous n'avez aucun devis en attente.",
                    ),
                    _liste(
                      devisPasses,
                      actionnable: false,
                      messageVide: "Aucun de vos devis n'a encore été transformé en commande.",
                    ),
                  ],
                ),
              ),
      ),
    ),
    );
  }
}
