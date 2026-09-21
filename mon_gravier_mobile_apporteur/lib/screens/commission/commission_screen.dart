import 'dart:convert';
import '../../helper/libelle_commission.dart';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:mon_gravier_com_apporteur/globale.dart';
import 'package:mon_gravier_com_apporteur/models/retour_liste_commission.dart';
import 'package:searchable_listview/searchable_listview.dart';

import '../../components/carte_operation.dart';
import '../../components/etat_vide.dart';
import '../../../components/bouton_retour.dart';
import '../../constants.dart';

/// UNE COMMISSION.
///
/// La carte alignait trois lignes de trois COULEURS différentes — rouge, bleu,
/// vert — autour d'une image animée de 80 px, décorative, redécodée à chaque
/// défilement, et la commission — la seule chose qu'on vient chercher — n'y
/// était pas plus visible que le reste. Elle prend la présentation des
/// paiements de l'accueil : le montant domine, l'état porte la seule couleur.
Widget carteCommission(UneCommission c) {
  final bool connue = affaireConnue(c);

  return Padding(
    padding: const EdgeInsets.only(bottom: kSpaceMd),
    child: CarteOperation(
      icone: connue ? Icons.savings_outlined : Icons.help_outline,
      numero: libelleClient(c),
      montant: formaterMontant(c.montant ?? 0),
      mention: libelleMontantAffaire(c, formaterMontant,
          formaterDate: (d) => formaterDate(d, format: 'd MMMM y')),
      lignes: [
        if ((c.typeAffaire ?? '').trim().isNotEmpty)
          "Affaire : ${c.typeAffaire}",
      ],
      date: (c.createdAt ?? '').trim().isEmpty
          ? null
          : "Acquise le ${formaterDate(c.createdAt!, format: 'd MMMM y')}",
      // Une commission dont l'affaire n'a pas été retrouvée est DUE quand
      // même : on le signale sans la faire passer pour une anomalie.
      statut: connue ? null : "À vérifier",
      couleurStatut: connue ? kPrimaryColor : kWarningColor,
      fondStatut: connue ? kPrimarySoftColor : kWarningSoftColor,
    ),
  );
}

class CommissionScreen extends StatefulWidget {
  // Cet écran déclarait la même route que FilleuleScreen ("/filleule") : une
  // navigation nommée vers l'un aurait pu ouvrir l'autre.
  static String routeName = "/commission";

  const CommissionScreen({super.key});

  @override
  State<CommissionScreen> createState() => _CommissionScreenState();
}

class _CommissionScreenState extends State<CommissionScreen> {

  RetourListeCommission retCommission = RetourListeCommission();
  List<UneCommission> commissions = [];

  chargerCommission({bool sansLoader = false}) async {
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
            .post(Uri.parse('${lienAPI()}liste-commissions'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 1));
        if (kDebugMode) {
          print('liste-commissions status: ${retourHttp.statusCode}');
        }
        if (retourHttp.statusCode == 200) {
          var datas = jsonDecode(retourHttp.body);
          if (kDebugMode) {
            print(retourHttp.body);
          }
          if (mounted) setState(() {
            retCommission = RetourListeCommission.fromJson(datas);
            commissions = retCommission.data ?? [];

            if (commissions.isNotEmpty) {
              if (kDebugMode) {
                print(commissions.first.toJson());
              }
            }
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
      chargerCommission();
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
        title: const Text("Liste de mes commissions"),
        elevation: 0,
        centerTitle: true,
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(child: _corps()),
    );
  }

  static const _vide = EtatVide(
    compact: true,
    icone: Icons.savings_outlined,
    titre: "Aucune commission",
    message:
        "Vos commissions apparaîtront ici dès qu'un filleul aura commandé.",
  );

  Future<void> _rafraichir() => chargerCommission(sansLoader: true);

  Widget _corps() {
    // LISTE VIDE : LE GESTE DOIT MARCHER QUAND MÊME. `SearchableList` remplace
    // la liste par l'état vide, et son indicateur de rafraîchissement avec —
    // or une liste vide est justement celle qu'on veut recharger.
    if (commissions.isEmpty) {
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
      child: SearchableList<UneCommission>(
        searchFieldEnabled: true,
        shrinkWrap: true,
        // LE CLAVIER NE S'OUVRE PLUS TOUT SEUL. Il masquait la moitié de la
        // liste dès l'arrivée, et il fallait le refermer pour voir ce qu'on
        // était venu consulter.
        autoFocusOnSearch: false,
        sortWidget: const Icon(Icons.sort),
        sortPredicate: (a, b) {
          int mtna = a.id ?? 0;
          int mtnb = b.id ?? 0;
          return mtna.compareTo(mtnb);
        },
        // Sans `AlwaysScrollable`, une liste plus courte que l'écran ne défile
        // pas, et le glisser n'atteint jamais l'indicateur.
        physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics()),
        onRefresh: _rafraichir,
        builder: (list, index, c) => carteCommission(c),
        emptyWidget: _vide,
        initialList: commissions,
        filter: (p0) {
          // La recherche porte sur le texte AFFICHÉ : chercher sur
          // `c.nom` brut faisait remonter toutes les lignes non
          // rattachées dès qu'on tapait « null ».
          final q = p0.toLowerCase();
          return commissions
              .where((c) => (libelleClient(c).toLowerCase().contains(q) ||
                  c.id.toString().contains(q) ||
                  c.montant.toString().contains(q) ||
                  (affaireConnue(c) &&
                      c.montantTotal.toString().contains(q))))
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
