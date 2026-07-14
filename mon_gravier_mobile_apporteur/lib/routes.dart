import 'package:flutter/widgets.dart';
import 'package:mon_gravier_com_apporteur/screens/demande_retrait/edit_demande_retrait_screen.dart';
import 'package:mon_gravier_com_apporteur/screens/demande_retrait/liste_demande_retrait_screen.dart';
import 'package:mon_gravier_com_apporteur/screens/filleule/filleule_screen.dart';
import 'package:mon_gravier_com_apporteur/screens/filleule/paiements/paiement_screen.dart';
import 'package:mon_gravier_com_apporteur/screens/home/home_screen.dart';
import 'package:mon_gravier_com_apporteur/screens/modifier_mot_de_passe/modifier_pass_screen.dart';
import 'package:mon_gravier_com_apporteur/screens/otp/otp_screen.dart';
import 'package:mon_gravier_com_apporteur/screens/profile/profile_screen.dart';
import 'package:mon_gravier_com_apporteur/screens/sign_up/sign_up_screen.dart';

import 'screens/forgot_password/forgot_password_screen.dart';
import 'screens/init_screen.dart';
import 'screens/sign_in/sign_in_screen.dart';

// We use name route
// All our routes will be available here
final Map<String, WidgetBuilder> routes = {
  InitScreen.routeName: (context) => const InitScreen(),
  SignInScreen.routeName: (context) => const SignInScreen(),
  ForgotPasswordScreen.routeName: (context) => const ForgotPasswordScreen(),
  PaiementFilleuleScreen.routeName: (context) => const PaiementFilleuleScreen(),
  ProfileScreen.routeName: (context) => const ProfileScreen(),
  ModifierPasseScreen.routeName: (context) => const ModifierPasseScreen(),
  HomeScreen.routeName: (context) => const HomeScreen(),
  FilleuleScreen.routeName: (context) => const FilleuleScreen(),
  ListeDemandeRetraitScreen.routeName: (context) => const ListeDemandeRetraitScreen(),
  EditDemandeRetraitScreen.routeName: (context) => const EditDemandeRetraitScreen(),
  SignUpScreen.routeName: (context) => const SignUpScreen(),
  OtpScreen.routeName: (context) => const OtpScreen(),
};
