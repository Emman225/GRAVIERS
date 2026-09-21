import 'dart:convert';
import '../../helper/libelle_commission.dart';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_apporteur/globale.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_filleule.dart';
import 'package:mon_gravier_com_apporteur/screens/filleule/paiements/paiement_screen.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../components/carte_operation.dart';
import '../../components/etat_vide.dart';
import '../../../components/bouton_retour.dart';
import '../../constants.dart';

/// UN FILLEUL.
///
/// La carte tenait en un numéro BLEU et un nom ROUGE — le rouge laissant
/// croire à une anomalie — autour d'un portrait animé de 80 px, décoratif et
/// redécodé à chaque défilement. Elle prend la présentation des paiements de
/// l'accueil.
///
/// Il n'y a PAS de montant : un filleul n'en porte pas, et « 0 F » serait un
/// chiffre faux plutôt qu'une absence.
Widget carteFilleul(Filleule c, {VoidCallback? onTap}) {
  final bool aTerme = c.clientATerme == true;
  final String nom = nomComplet(c.nom, c.prenom);

  return Padding(
    padding: const EdgeInsets.only(bottom: kSpaceMd),
    child: CarteOperation(
      icone: Icons.person_outline,
      numero: nom.isEmpty ? "Filleul # ${c.id}" : nom,
      mention: (c.contact1 ?? '').trim().isEmpty ? null : "${c.contact1}",
      lignes: [
        if ((c.email ?? '').trim().isNotEmpty) "${c.email}",
        if ((c.typeClient ?? '').trim().isNotEmpty) "Type : ${c.typeClient}",
      ],
      statut: aTerme ? "Client à terme" : null,
      couleurStatut: kPrimaryColor,
      fondStatut: kPrimarySoftColor,
      // L'action est portée ICI : un InkWell muni d'une action absorbe le
      // geste, un GestureDetector autour de la carte ne serait jamais appelé.
      onTap: onTap ??
          () => Get.toNamed(PaiementFilleuleScreen.routeName, arguments: c),
    ),
  );
}

class FilleuleScreen extends StatefulWidget {
  static String routeName = "/filleule";

  const FilleuleScreen({super.key});

  @override
  State<FilleuleScreen> createState() => _FilleuleScreenState();
}

class _FilleuleScreenState extends State<FilleuleScreen> {

  RetourListeFilleule retFilleule = RetourListeFilleule();
  List<Filleule> filleules = [];

  chargerFilleule({bool sansLoader = false}) async {
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
            .post(Uri.parse('${lienAPI()}liste-filleule'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        if (kDebugMode) {
          print('liste-filleule status: ${retourHttp.statusCode}');
        }
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          if (mounted) setState(() {
            retFilleule = RetourListeFilleule.fromJson(datas);
            filleules = retFilleule.data ?? [];
          });
        } else {
          if (mounted) afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
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
      chargerFilleule();
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
        title: const Text("Liste de mes filleules"),
        elevation: 0,
        centerTitle: true,
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(child: _corps()),
    );
  }

  static const _vide = EtatVide(
    compact: true,
    icone: Icons.group_outlined,
    titre: "Aucun filleul",
    message: "Partagez votre code parrain : vos filleuls apparaîtront ici.",
  );

  Future<void> _rafraichir() => chargerFilleule(sansLoader: true);

  Widget _corps() {
    // LISTE VIDE : LE GESTE DOIT MARCHER QUAND MÊME. `SearchableList` remplace
    // la liste par l'état vide, et son indicateur de rafraîchissement avec.
    if (filleules.isEmpty) {
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
      child: SearchableList<Filleule>(
        searchFieldEnabled: true,
        shrinkWrap: true,
        // LE CLAVIER NE S'OUVRE PLUS TOUT SEUL : il masquait la moitié de la
        // liste dès l'arrivée.
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
        builder: (list, index, c) => carteFilleul(c),
        emptyWidget: _vide,
        initialList: filleules,
        filter: (p0) {
          return filleules
              .where((c) => (c.nom.toString().contains(p0) ||
                  c.prenom.toString().contains(p0) ||
                  c.contact1.toString().contains(p0) ||
                  c.email.toString().contains(p0) ||
                  c.typeClient.toString().contains(p0)))
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
