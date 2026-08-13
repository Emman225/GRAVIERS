import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com/components/empty_user_widget.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/models/devis.dart';
import 'package:searchable_listview/searchable_listview.dart';

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
    final carte = Padding(
      padding: const EdgeInsets.all(8.0),
      child: Container(
        height: 120,
        decoration: BoxDecoration(
          color: actionnable ? Colors.grey[200] : Colors.grey[100],
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          children: [
            const SizedBox(width: 10),
            Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(30),
                  image: const DecorationImage(
                      image: AssetImage("assets/images/devis.gif"),
                      fit: BoxFit.cover,
                      opacity: 0.6),
                ),
                child: Container()),
            const SizedBox(width: 10),
            Flexible(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    '# ${c.numero}',
                    style: TextStyle(
                      color: actionnable ? Colors.red : Colors.grey[700],
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    formaterMontant(c.montant?.toDouble() ?? 0),
                    style: const TextStyle(
                      color: Colors.blue,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    '${c.libelle}',
                    style: const TextStyle(
                      color: Colors.black,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    '${c.dateDevis}',
                    style: const TextStyle(color: Colors.black),
                  ),
                  if (!actionnable)
                    Container(
                      margin: const EdgeInsets.only(top: 4),
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: Colors.green[600],
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Text(
                        'Passé en commande',
                        style: TextStyle(color: Colors.white, fontSize: 11),
                      ),
                    ),
                ],
              ),
            ),
            if (actionnable)
              const Icon(Icons.navigate_next_sharp, color: blackColor, size: 20),
          ],
        ),
      ),
    );

    if (!actionnable) return carte;

    return GestureDetector(
      onTap: () async {
        await Get.toNamed(DetailsDevisScreen.routeName, arguments: c);
        // Cette attente ne se termine pas seulement quand l'utilisateur revient
        // du détail : « Aller à l'accueil », après la transformation du devis en
        // commande, retire TOUS les écrans de la pile — et celui-ci se réveille
        // alors qu'il n'existe plus.
        //
        // Recharger dans cet état lançait un appel dont la réponse ne pouvait
        // plus rien rafraîchir : l'échec remontait au catch, qui affichait une
        // erreur par-dessus l'accueil tout juste ouvert. C'est l'erreur qui
        // suivait chaque devis passé en commande, et que rien ne rattachait à
        // cet écran-ci.
        if (mounted) {
          chargerDevis();
        }
      },
      child: carte,
    );
  }

  /// Liste défilante et cherchable d'un jeu de devis.
  Widget _liste(List<DataDevis> source, {required bool actionnable, required String messageVide}) {
    if (source.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(30.0),
          child: Text(
            messageVide,
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.grey[700], fontSize: 15),
          ),
        ),
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
        inputDecoration: InputDecoration(
          labelText: "Recherchez...",
          fillColor: Colors.white,
          focusedBorder: OutlineInputBorder(
            borderSide: const BorderSide(
              color: kPrimaryColor,
              width: 1.0,
            ),
            borderRadius: BorderRadius.circular(10.0),
          ),
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
        backgroundColor: Colors.transparent,
        elevation: 0,
        bottom: TabBar(
          labelColor: kPrimaryColor,
          unselectedLabelColor: Colors.grey,
          indicatorColor: kPrimaryColor,
          tabs: [
            Tab(text: "En attente (${devisEnAttente.length})"),
            Tab(text: "Historique (${devisPasses.length})"),
          ],
        ),
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
      body: SafeArea(
        child: (user.token == null || user.token == "")
            ? const EmptyUserWidget()
            : Container(
                width: double.infinity,
                height: heightOfScreen(context),
                decoration: const BoxDecoration(
                  image: DecorationImage(
                    image: AssetImage("assets/images/bg.jpg"),
                    fit: BoxFit.cover,
                    opacity: 0.1,
                  ),
                ),
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
