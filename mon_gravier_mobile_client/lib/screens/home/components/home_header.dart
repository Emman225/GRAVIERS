import 'package:flutter/material.dart';
import 'package:mon_gravier_com/globale.dart';

import '../../../constants.dart';
import '../../cart/cart_screen.dart';
import 'icon_btn_with_counter.dart';
import 'search_field.dart';

/// BANDEAU DE TÊTE DE L'ACCUEIL, REPLIABLE.
///
/// Il a connu deux formes, toutes deux fautives :
///
///  · d'abord un bloc unique DANS le défilement : le logo, le nom et le panier
///    disparaissaient dès les premiers produits ;
///  · puis une barre posée HORS du défilement, au-dessus d'un bandeau qui
///    défilait : deux surfaces distinctes, et le bandeau passait sous la barre
///    avec une rupture nette au premier geste.
///
/// C'est désormais UNE SEULE surface bleue qui se replie. Au début du geste,
/// la salutation et la recherche remontent avec le reste ; une fois repliée, il
/// ne demeure que la ligne logo / nom / panier, qui reste posée en haut.
///
/// `SliverAppBar` s'en charge : `expandedHeight` donne la hauteur déployée,
/// `pinned` retient la ligne du haut, et le dégradé du `flexibleSpace` couvre
/// les deux états sans jonction visible.
class EnTeteAccueil extends StatelessWidget {
  const EnTeteAccueil({super.key});

  /// Hauteur de la ligne qui reste : logo (42) + marges.
  static const double hauteurLigne = 62;

  /// Salutation, sous-titre et champ de recherche.
  static const double hauteurRepliable = 132;

  /// Dernier mot du nom enregistré. Un client s'appelant « KOUASSI Yao Marc »
  /// n'a pas besoin de se voir saluer par ses trois noms.
  static String _prenom() {
    final nom = user.nom?.toString().trim() ?? '';
    if (nom.isEmpty || nom == 'null') return '';
    return nom.split(RegExp(r'\s+')).last;
  }

  @override
  Widget build(BuildContext context) {
    final double hautBarreSysteme = MediaQuery.of(context).padding.top;
    final prenom = _prenom();

    return SliverAppBar(
      pinned: true,
      automaticallyImplyLeading: false,
      backgroundColor: kPrimaryColor,
      elevation: 0,
      scrolledUnderElevation: 0,
      toolbarHeight: hauteurLigne,
      expandedHeight: hauteurLigne + hauteurRepliable,
      titleSpacing: kSpaceXl,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(kRadiusLg)),
      ),

      // Ce qui RESTE : logo, nom, panier.
      title: Row(
        children: [
          // Le logo est cerclé de blanc : son propre anneau navy se
          // confondrait avec le bandeau.
          Container(
            height: 40,
            width: 40,
            decoration: const BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
            ),
            padding: const EdgeInsets.all(2),
            child: ClipOval(
              child: Image.asset("assets/images/logo.png", fit: BoxFit.cover),
            ),
          ),
          const SizedBox(width: kSpaceMd),
          const Expanded(
            child: Text(
              "MON GRAVIER",
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: Colors.white,
                fontSize: 15,
                fontWeight: FontWeight.w700,
                letterSpacing: 1.2,
              ),
            ),
          ),
          IconBtnWithCounter(
            svgSrc: "assets/icons/Cart Icon.svg",
            surFondSombre: true,
            press: () => Navigator.pushNamed(context, CartScreen.routeName),
          ),
        ],
      ),

      // Ce qui SE REPLIE : salutation, sous-titre, recherche — sur le dégradé
      // qui couvre aussi la ligne du haut.
      flexibleSpace: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [kPrimaryColor, kPrimaryMidColor],
          ),
          borderRadius:
              BorderRadius.vertical(bottom: Radius.circular(kRadiusLg)),
        ),
        child: FlexibleSpaceBar(
          background: Padding(
            // On laisse la place de la barre système et de la ligne du haut :
            // le contenu repliable commence en dessous.
            padding: EdgeInsets.fromLTRB(
              kSpaceXl,
              hautBarreSysteme + hauteurLigne,
              kSpaceXl,
              kSpaceLg,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  prenom.isEmpty ? "Bienvenue" : "Bonjour $prenom",
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    height: 1.25,
                    letterSpacing: -0.3,
                  ),
                ),
                const SizedBox(height: 2),
                const Text(
                  "Sable, gravier et matériel de chantier, livrés chez vous.",
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Color(0xCCFFFFFF),
                    fontSize: 12.5,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: kSpaceMd),
                const SearchField(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
