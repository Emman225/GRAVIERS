import 'package:flutter/widgets.dart';
import 'package:mon_gravier_com_livreur/screens/commande_success/commande_success_screen.dart';
import 'package:mon_gravier_com_livreur/screens/demande_retrait/edit_demande_retrait_screen.dart';
import 'package:mon_gravier_com_livreur/screens/demande_retrait/liste_demande_retrait_screen.dart';
import 'package:mon_gravier_com_livreur/screens/details_commande/details_commande_screen.dart';
import 'package:mon_gravier_com_livreur/screens/details_livraison/details_livraison_screen.dart';
import 'package:mon_gravier_com_livreur/screens/home/home_screen.dart';
import 'package:mon_gravier_com_livreur/screens/livraison/components/livraison_effectuee_screen.dart';
import 'package:mon_gravier_com_livreur/screens/modifier_mot_de_passe/modifier_pass_screen.dart';
import 'package:mon_gravier_com_livreur/screens/profile/profile_screen.dart';
import 'package:mon_gravier_com_livreur/screens/vehicule/edition_vehicule/edition_vehicule_screen.dart';
import 'package:mon_gravier_com_livreur/screens/vehicule/vehicule_screen.dart';

import 'screens/details/details_screen.dart';
import 'screens/forgot_password/forgot_password_screen.dart';
import 'screens/init_screen.dart';
import 'screens/sign_in/sign_in_screen.dart';

// We use name route
// All our routes will be available here
final Map<String, WidgetBuilder> routes = {
  InitScreen.routeName: (context) => const InitScreen(),
  SignInScreen.routeName: (context) => const SignInScreen(),
  ForgotPasswordScreen.routeName: (context) => const ForgotPasswordScreen(),
  CommandeSuccessScreen.routeName: (context) => const CommandeSuccessScreen(),
  ProfileScreen.routeName: (context) => const ProfileScreen(),
  DetailsScreen.routeName: (context) => const DetailsScreen(),
  DetailsCommandeScreen.routeName: (context) => const DetailsCommandeScreen(),
  ModifierPasseScreen.routeName: (context) => const ModifierPasseScreen(),
  HomeScreen.routeName: (context) => const HomeScreen(),
  DetailsLivraisonScreen.routeName: (context) => const DetailsLivraisonScreen(),
  VehiculeScreen.routeName: (context) => const VehiculeScreen(),
  EditionVehiculeScreen.routeName: (context) => const EditionVehiculeScreen(),
  LivraisonEffectueeScreen.routeName: (context) => const LivraisonEffectueeScreen(),
  ListeDemandeRetraitScreen.routeName: (context) => const ListeDemandeRetraitScreen(),
  EditDemandeRetraitScreen.routeName: (context) => const EditDemandeRetraitScreen(),
};
