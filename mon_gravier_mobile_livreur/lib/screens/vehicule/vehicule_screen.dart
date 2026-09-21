import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/models/RetourVehicule.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../components/carte_operation.dart';
import '../../components/etat_vide.dart';
import '../../../components/bouton_retour.dart';
import '../../constants.dart';
import '../../globale.dart';
import 'package:http/http.dart' as http;

import 'edition_vehicule/edition_vehicule_screen.dart';

class VehiculeScreen extends StatefulWidget {
  static String routeName = "/vehicule";

  const VehiculeScreen({super.key});

  @override
  State<VehiculeScreen> createState() => _VehiculeScreenState();
}

class _VehiculeScreenState extends State<VehiculeScreen> {

  RetourVehicule retVehicule = RetourVehicule();
  List<Vehicules> vehicules = [];
  List<TypeVehicule> typeVehicules = [];

  chargerVehicule({bool sansLoader = false}) async {
    if (await verifierConnexion()) {
      // Au glisser, l'indicateur du geste suffit : le voile par-dessus
      // masquerait justement ce qu'on vient de tirer pour voir.
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
            .post(Uri.parse('${lienAPI()}liste-vehicule'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          retVehicule = RetourVehicule.fromJson(datas);
          // Le code metier renvoye par l'API n'etait PAS controle : une session
          // expiree ou un refus serveur (reponse HTTP 200 mais code != 200)
          // laissait un ecran vide, sans aucune explication.
          if (retVehicule.code == 200) {
            if (mounted) setState(() {
              vehicules = retVehicule.data?.vehicules ?? [];
              typeVehicules = retVehicule.data?.types ?? [];
            });
          } else {
            if (mounted) setState(() {
              vehicules = [];
              typeVehicules = [];
            });
            if (mounted) afficherErreur(retVehicule.message ??
                "Impossible de charger vos vehicules.");
          }
        } else {
          // Sans cette branche, une reponse serveur en erreur ne produisait
          // AUCUNE reaction a l'ecran.
          if (mounted) afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez reessayer.");
        }
      } catch (e) {
        user.code = 500;
        user.message = "Une erreur s'est produite veuillez reesayer plus tard";
        // Ce bloc de secours n.affichait RIEN : l.ecran restait muet en cas de
        // coupure reseau ou de reponse illisible.
        if (mounted) afficherErreur("Impossible de contacter le serveur. Verifiez votre connexion et reessayez.");
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
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chargerVehicule();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        // Onglet de la barre du bas : rien à dépiler, le retour
        // ramène à l'accueil.
        leading: BoutonRetour(
          onTap: retourAccueil,
          tooltip: "Retour à l'accueil",
        ),
        title: const Text("Gestion des vehicules"),
        elevation: 0,
        centerTitle: true,
        automaticallyImplyLeading: false,
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xff03dac6),
        foregroundColor: Colors.black,
        onPressed: () async {
          await Get.toNamed(EditionVehiculeScreen.routeName,
              arguments: [Vehicules(), typeVehicules]);
          chargerVehicule();
        },
        icon: const Icon(Icons.add),
        label: const Text('Ajouter Vehicule'),
      ),
      body: SafeArea(child: _corps()),
    );
  }

  static const _vide = EtatVide(
    compact: true,
    icone: Icons.local_shipping_outlined,
    titre: "Aucun véhicule",
    message: "Aucun véhicule ne vous est rattaché pour le moment.",
  );

  Future<void> _rafraichir() => chargerVehicule(sansLoader: true);

  /// UN VÉHICULE.
  ///
  /// La carte alignait cinq lignes de cinq COULEURS différentes — rouge, bleu,
  /// vert, orange, noir — autour d'un camion animé de 80 px, décoratif, redécodé
  /// à chaque défilement. Elle prend la présentation des autres listes : une
  /// seule couleur, celle de l'état, et l'immatriculation en tête puisque c'est
  /// ce que l'on cherche.
  Widget _uneCarte(Vehicules c) {
    final bool dispo = c.disponible == true;

    return Padding(
      padding: const EdgeInsets.only(bottom: kSpaceMd),
      child: CarteOperation(
        icone: Icons.local_shipping_outlined,
        numero: c.immatriculation ?? '-',
        // La place du montant porte ici la charge utile : c'est le chiffre que
        // le livreur compare d'un véhicule à l'autre.
        montant: "${c.capacite ?? 0} t",
        mention: c.nom ?? '',
        lignes: [
          if (("${c.marque ?? ''}${c.modele ?? ''}").trim().isNotEmpty)
            "Modèle : ${c.marque ?? ''} ${c.modele ?? ''}".trim(),
          if ((c.typeVehicule ?? '').trim().isNotEmpty)
            "Type : ${c.typeVehicule}",
        ],
        statut: dispo ? "Disponible" : "Indisponible",
        couleurStatut: dispo ? kSuccessColor : kTextMutedColor,
        fondStatut: dispo ? kSuccessSoftColor : kBorderColor,
        // L'action est portée ICI : un InkWell muni d'une action absorbe le
        // geste, un GestureDetector autour de la carte ne serait jamais appelé.
        onTap: () async {
          await Get.toNamed(EditionVehiculeScreen.routeName,
              arguments: [c, typeVehicules]);
          chargerVehicule(sansLoader: true);
        },
      ),
    );
  }

  Widget _corps() {
    // LISTE VIDE : LE GESTE DOIT MARCHER QUAND MÊME. `SearchableList` remplace
    // la liste par l'état vide, et son indicateur de rafraîchissement avec.
    if (vehicules.isEmpty) {
      return RefreshIndicator(
        color: kPrimaryColor,
        onRefresh: _rafraichir,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics()),
          padding: const EdgeInsets.all(kSpaceLg),
          children: const [SizedBox(height: kSpaceXxl), _vide],
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.all(kSpaceLg),
      child: SearchableList<Vehicules>(
        searchFieldEnabled: true,
        shrinkWrap: true,
        autoFocusOnSearch: false,
        sortWidget: const Icon(Icons.sort),
        sortPredicate: (a, b) {
          String mtna = a.nom ?? '';
          String mtnb = b.nom ?? '';
          return mtna.compareTo(mtnb);
        },
        // Sans `AlwaysScrollable`, une liste plus courte que l'écran ne défile
        // pas, et le glisser n'atteint jamais l'indicateur.
        physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics()),
        onRefresh: _rafraichir,
        builder: (vehicules, index, c) => _uneCarte(c),
        emptyWidget: _vide,
        initialList: vehicules,
        filter: (p0) {
          return vehicules
              .where((c) => (c.nom.toString().contains(p0) ||
                  c.description.toString().contains(p0) ||
                  c.marque.toString().contains(p0) ||
                  c.modele.toString().contains(p0) ||
                  c.capacite.toString().contains(p0)))
              .toList();
        },
        inputDecoration: const InputDecoration(
          hintText: "Rechercher...",
          floatingLabelBehavior: FloatingLabelBehavior.never,
          prefixIcon: Icon(Icons.search, size: 20),
        ),
      ),
    );
  }
}
