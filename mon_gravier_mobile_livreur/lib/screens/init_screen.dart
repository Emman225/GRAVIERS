import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:mon_gravier_com_livreur/constants.dart';
import 'package:mon_gravier_com_livreur/globale.dart';
import 'package:mon_gravier_com_livreur/screens/home/home_screen.dart';
import 'package:mon_gravier_com_livreur/screens/livraison/livraison_screen.dart';
import 'package:mon_gravier_com_livreur/screens/profile/profile_screen.dart';
import 'package:mon_gravier_com_livreur/screens/vehicule/vehicule_screen.dart';


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
    const LivraisonScreen(),
    const VehiculeScreen(),
    const ProfileScreen(),
  ];


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
            // Les libellés des onglets NON sélectionnés étaient masqués : il
            // restait quatre silhouettes grises presque identiques, sans un mot
            // pour les distinguer. Le thème les affiche tous.
            items: [
          BottomNavigationBarItem(
            icon: SvgPicture.asset(
              "assets/icons/home.svg",
              colorFilter: const ColorFilter.mode(
                inActiveIconColor,
                BlendMode.srcIn,
              ),
            ),
            activeIcon: SvgPicture.asset(
              "assets/icons/home.svg",
              colorFilter: const ColorFilter.mode(
                kPrimaryColor,
                BlendMode.srcIn,
              ),
            ),
            label: "Accueil",
          ),
          BottomNavigationBarItem(
            icon: SvgPicture.asset(
              "assets/icons/livraison.svg",
              colorFilter: const ColorFilter.mode(
                inActiveIconColor,
                BlendMode.srcIn,
              ),
            ),
            activeIcon: SvgPicture.asset(
              "assets/icons/livraison.svg",
              colorFilter: const ColorFilter.mode(
                kPrimaryColor,
                BlendMode.srcIn,
              ),
            ),
            label: "Livraison",
          ),
          BottomNavigationBarItem(
            icon: SvgPicture.asset(
              "assets/icons/Car.svg",
              colorFilter: const ColorFilter.mode(
                inActiveIconColor,
                BlendMode.srcIn,
              ),
              width: 25,
              height: 25,
            ),
            activeIcon: SvgPicture.asset(
              "assets/icons/Car.svg",
              colorFilter: const ColorFilter.mode(
                kPrimaryColor,
                BlendMode.srcIn,
              ),
              width: 25,
              height: 25,
            ),
            label: "Vehicule",
          ),
          BottomNavigationBarItem(
            icon: SvgPicture.asset(
              "assets/icons/User.svg",
              colorFilter: const ColorFilter.mode(
                inActiveIconColor,
                BlendMode.srcIn,
              ),
            ),
            activeIcon: SvgPicture.asset(
              "assets/icons/User.svg",
              colorFilter: const ColorFilter.mode(
                kPrimaryColor,
                BlendMode.srcIn,
              ),
            ),
            label: "Compte",
          ),
            ],
          ),
        ),
      ),
    );
  }
}
