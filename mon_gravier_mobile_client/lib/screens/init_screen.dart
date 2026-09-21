import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/screens/home/home_screen.dart';
import 'package:mon_gravier_com/screens/livraison/livraison_screen.dart';
import 'package:mon_gravier_com/screens/products/products_screen.dart';
import 'package:mon_gravier_com/screens/profile/profile_screen.dart';

import 'cart/cart_screen.dart';
import 'commande/commande_screen.dart';
import 'home/components/icon_btn_with_counter.dart';

const Color inActiveIconColor = kTextMutedColor;

class InitScreen extends StatefulWidget {
  const InitScreen({super.key});

  static String routeName = "/";

  @override
  State<InitScreen> createState() => _InitScreenState();
}

class _InitScreenState extends State<InitScreen> {
  int currentSelectedIndex = 0;

  void updateCurrentIndex(int index) {
    setState(() {
      afficheRetour = false;
      currentSelectedIndex = index;
    });
  }

  @override
  void initState() {
    super.initState();
    // Les onglets ont besoin de pouvoir revenir à l'accueil : c'est ici, et
    // seulement ici, que l'index se change.
    allerAOnglet = updateCurrentIndex;
  }

  @override
  void dispose() {
    if (allerAOnglet == updateCurrentIndex) allerAOnglet = null;
    super.dispose();
  }

  final pages = [
    const HomeScreen(),
    const ProductsScreen(),
    const CommandeScreen(),
    const LivraisonScreen(),
    const ProfileScreen()
  ];

  /// Un onglet = un pictogramme + un libellé. Les libellés des onglets NON
  /// sélectionnés étaient masqués (`showUnselectedLabels: false`) : il restait
  /// quatre silhouettes grises presque identiques, dont il fallait deviner le
  /// sens. Ils sont maintenant tous visibles.
  BottomNavigationBarItem _onglet(String svg, String libelle) {
    return BottomNavigationBarItem(
      icon: Padding(
        padding: const EdgeInsets.only(bottom: 2),
        child: SvgPicture.asset(
          svg,
          width: 22,
          height: 22,
          colorFilter:
              const ColorFilter.mode(inActiveIconColor, BlendMode.srcIn),
        ),
      ),
      activeIcon: Padding(
        padding: const EdgeInsets.only(bottom: 2),
        child: SvgPicture.asset(
          svg,
          width: 22,
          height: 22,
          colorFilter: const ColorFilter.mode(kPrimaryColor, BlendMode.srcIn),
        ),
      ),
      label: libelle,
    );
  }


  /// LES ONGLETS DEJA VISITES RESTENT VIVANTS.
  ///
  /// On n'affichait qu'un ecran a la fois : les autres quittaient l'arbre,
  /// leur etat etait detruit, et y revenir les reconstruisait de zero — voile
  /// de chargement et appel a l'API compris, pour des donnees deja chargees.
  ///
  /// Un onglet JAMAIS ouvert n'est pas construit : sans cela, tous les ecrans
  /// appelleraient l'API en meme temps au demarrage.
  final Set<int> _dejaVisites = {0};

  Widget _corpsDesOnglets() {
    _dejaVisites.add(currentSelectedIndex);

    return IndexedStack(
      index: currentSelectedIndex,
      // Comme avant, l'ecran occupe tout le corps de la page.
      sizing: StackFit.expand,
      children: [
        for (var i = 0; i < pages.length; i++)
          _dejaVisites.contains(i) ? pages[i] : const SizedBox.shrink(),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: WillPopScope(
        onWillPop: () async {
          bool backStatus = onWillPop();
          if (backStatus) {
            exit(0);
          }
          return false;
        },
        child: _corpsDesOnglets(),
      ),

      // Le bouton du panier était posé en BAS À GAUCHE, où aucune application
      // ne place son action principale — et son `onPressed` était vide : seul
      // le pictogramme central réagissait, le pourtour du bouton ne faisait
      // rien. Il passe à droite, et toute sa surface ouvre le panier.
      floatingActionButton: FloatingActionButton(
        onPressed: () => Navigator.pushNamed(context, CartScreen.routeName),
        tooltip: "Mon panier",
        heroTag: "Mon Panier",
        backgroundColor: kSurfaceColor,
        elevation: 2,
        shape: const CircleBorder(
          side: BorderSide(color: kBorderColor),
        ),
        child: IgnorePointer(
          child: IconBtnWithCounter(
            svgSrc: "assets/icons/Cart Icon.svg",
            couleurIcone: kPrimaryColor,
            press: () {},
          ),
        ),
      ),
      // DEUX BOUTONS FLOTTANTS SE RECOUVRAIENT.
      //
      // L'onglet « Livraisons » porte son PROPRE bouton flottant (le menu des
      // demandes de livraison), en bas à droite. Le bouton du panier, ramené
      // lui aussi à droite, se posait exactement dessus : celui du dessous
      // devenait inatteignable.
      //
      // Sur cet onglet — et sur lui seul — le panier repasse à gauche. Les
      // deux boutons restent accessibles, aucun n'est retiré.
      floatingActionButtonLocation: currentSelectedIndex == 3
          ? FloatingActionButtonLocation.startFloat
          : FloatingActionButtonLocation.endFloat,

      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: kSurfaceColor,
          border: Border(top: BorderSide(color: kBorderColor)),
        ),
        child: SafeArea(
          top: false,
          child: BottomNavigationBar(
            onTap: updateCurrentIndex,
            currentIndex: currentSelectedIndex,
            items: [
              _onglet("assets/icons/home.svg", "Accueil"),
              _onglet("assets/icons/Shop Icon.svg", "Produits"),
              _onglet("assets/icons/Cart Icon.svg", "Commandes"),
              _onglet("assets/icons/livraison.svg", "Livraisons"),
              _onglet("assets/icons/User Icon.svg", "Compte"),
            ],
          ),
        ),
      ),
    );
  }
}
