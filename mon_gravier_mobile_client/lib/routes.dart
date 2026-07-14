import 'package:flutter/widgets.dart';
import 'package:mon_gravier_com/components/afficher_image_widget.dart';
import 'package:mon_gravier_com/impression/impression_recu_paiement_pdf.dart';
import 'package:mon_gravier_com/screens/afficher_carte/afficher_carte.dart';
import 'package:mon_gravier_com/screens/choix_adresse/choix_adresse_screen.dart';
import 'package:mon_gravier_com/screens/client_a_terme/client_a_terme_screen.dart';
import 'package:mon_gravier_com/screens/commande/commande_screen.dart';
import 'package:mon_gravier_com/screens/commande_error/commande_error_screen.dart';
import 'package:mon_gravier_com/screens/commande_success/commande_success_screen.dart';
import 'package:mon_gravier_com/screens/demande_livraison/demande_livraison_screen.dart';
import 'package:mon_gravier_com/screens/details_commande/details_commande_screen.dart';
import 'package:mon_gravier_com/screens/details_demande_livraison/details_demande_livraison_screen.dart';
import 'package:mon_gravier_com/screens/details_devis/details_devis_screen.dart';
import 'package:mon_gravier_com/screens/details_livraison/details_livraison_screen.dart';
import 'package:mon_gravier_com/screens/details_location/details_location_screen.dart';
import 'package:mon_gravier_com/screens/devis/devis_screen.dart';
import 'package:mon_gravier_com/screens/edit_profil/edit_profile_screen.dart';
import 'package:mon_gravier_com/screens/edition_adresse/edition_adresse_screen.dart';
import 'package:mon_gravier_com/screens/facture/facture_screen.dart';
import 'package:mon_gravier_com/screens/finalisation_demande_livraison/finalisation_demande_livraison_screen.dart';
import 'package:mon_gravier_com/screens/liste_demande_livraison/liste_demande_livraison_screen.dart';
import 'package:mon_gravier_com/screens/modifier_mot_de_passe/modifier_pass_screen.dart';
import 'package:mon_gravier_com/screens/note_client/note_client_screen.dart';
import 'package:mon_gravier_com/screens/paiement/paiement_screen.dart';
import 'package:mon_gravier_com/screens/products/products_categorie_screen.dart';
import 'package:mon_gravier_com/screens/products/products_screen.dart';
import 'package:mon_gravier_com/screens/products/products_search_screen.dart';
import 'package:mon_gravier_com/screens/resume_commande/resume_commande_screen.dart';
import 'package:mon_gravier_com/screens/souhait/souhait_screen.dart';

import 'screens/cart/cart_screen.dart';
import 'screens/complete_profile/complete_profile_screen.dart';
import 'screens/details/details_screen.dart';
import 'screens/details_demande_livraison_affiche/details_demande_livraison_affiche_screen.dart';
import 'screens/forgot_password/forgot_password_screen.dart';
import 'screens/home/home_screen.dart';
import 'screens/init_screen.dart';
import 'screens/otp/otp_screen.dart';
import 'screens/profile/profile_screen.dart';
import 'screens/sign_in/sign_in_screen.dart';
import 'screens/sign_up/sign_up_screen.dart';
import 'screens/splash/splash_screen.dart';

// We use name route
// All our routes will be available here
final Map<String, WidgetBuilder> routes = {
  InitScreen.routeName: (context) => const InitScreen(),
  SplashScreen.routeName: (context) => const SplashScreen(),
  SignInScreen.routeName: (context) => const SignInScreen(),
  ForgotPasswordScreen.routeName: (context) => const ForgotPasswordScreen(),
  CommandeSuccessScreen.routeName: (context) => const CommandeSuccessScreen(),
  SignUpScreen.routeName: (context) => const SignUpScreen(),
  CompleteProfileScreen.routeName: (context) => const CompleteProfileScreen(),
  OtpScreen.routeName: (context) => const OtpScreen(),
  HomeScreen.routeName: (context) => const HomeScreen(),
  ProductsScreen.routeName: (context) => const ProductsScreen(),
  DetailsScreen.routeName: (context) => const DetailsScreen(),
  CartScreen.routeName: (context) => const CartScreen(),
  ProfileScreen.routeName: (context) => const ProfileScreen(),
  ChoixAdresseScreen.routeName: (context) => const ChoixAdresseScreen(),
  EditionAdresseScreen.routeName: (context) => const EditionAdresseScreen(),
  AfficherCarteScreen.routeName: (context) => const AfficherCarteScreen(),
  ProductsCategorieScreen.routeName: (context) => const ProductsCategorieScreen(),
  DetailsCommandeScreen.routeName: (context) => const DetailsCommandeScreen(),
  ProductsSearchScreen.routeName: (context) => const ProductsSearchScreen(),
  ModifierPasseScreen.routeName: (context) => const ModifierPasseScreen(),
  DemandeClientATermeScreen.routeName: (context) => const DemandeClientATermeScreen(),
  EditProfileScreen.routeName: (context) => const EditProfileScreen(),
  NoteClientScreen.routeName: (context) => const NoteClientScreen(),
  SouhaitScreen.routeName: (context) => const SouhaitScreen(),
  DetailsLivraisonScreen.routeName: (context) => const DetailsLivraisonScreen(),
  DemandeLivraisonScreen.routeName: (context) => const DemandeLivraisonScreen(),
  DetailsDemandeLivraisonScreen.routeName: (context) => const DetailsDemandeLivraisonScreen(),
  FinalisationDemandeLivraisonScreen.routeName: (context) => const FinalisationDemandeLivraisonScreen(),
  ListeDemandeLivraisonScreen.routeName: (context) => const ListeDemandeLivraisonScreen(),
  DetailsDemandeLivraisonAfficheScreen.routeName: (context) => const DetailsDemandeLivraisonAfficheScreen(),
  DevisScreen.routeName: (context) => const DevisScreen(),
  CommandeScreen.routeName: (context) => const CommandeScreen(),
  DetailsDevisScreen.routeName: (context) => const DetailsDevisScreen(),
  DetailsLocationScreen.routeName: (context) => const DetailsLocationScreen(),
  CommandeErrorScreen.routeName: (context) => const CommandeErrorScreen(),
  FactureScreen.routeName: (context) => const FactureScreen(),
  PaiementScreen.routeName: (context) => const PaiementScreen(),
  ImpressionRecuPaiementPdf.routeName: (context) => const ImpressionRecuPaiementPdf(),
  AfficherImageWidget.routeName: (context) => const AfficherImageWidget(),
  ResumeCommandeScreen.routeName: (context) => const ResumeCommandeScreen(),
};
