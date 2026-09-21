import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com_livreur/screens/sign_in/sign_in_screen.dart';
import 'package:flutter_localizations/flutter_localizations.dart';


import 'components/logo_tournant.dart';
import 'constants.dart';
import 'routes.dart';
import 'theme.dart';

/// Indicateurs et messages (chargement, succès, erreur, information).
///
/// L'ancien réglage affichait du JAUNE sur du VERT derrière un voile BLEU —
/// trois couleurs qui n'existent nulle part ailleurs dans l'application, et
/// aucune ne disait quoi que ce soit : le message d'erreur et le message de
/// succès avaient exactement la même apparence.
///
/// Désormais : une pastille sombre et sobre, texte blanc, voile discret. La
/// nature du message se lit à son pictogramme et à son texte.
void configLoading() {
  EasyLoading.instance
    ..displayDuration = const Duration(milliseconds: 2200)
    // LE LOGO TOURNE À LA PLACE DE L'ANNEAU.
    //
    // `indicatorType` n'est pas touché : il ne sert qu'à choisir parmi les
    // animations FOURNIES par le paquet, et il est ignoré dès qu'un
    // `indicatorWidget` est donné — EasyLoading.show retient
    // `indicator ?? (indicatorWidget ?? LoadingIndicator())`.
    ..indicatorWidget = const LogoTournant()
    ..loadingStyle = EasyLoadingStyle.custom
    // Pastille au BLEU DE LA MARQUE, et non plus au gris presque noir.
    ..backgroundColor = kPrimaryColor
    ..indicatorColor = Colors.white
    ..textColor = Colors.white
    ..progressColor = Colors.white
    ..indicatorSize = 34.0
    ..radius = kRadiusMd
    ..contentPadding =
        const EdgeInsets.symmetric(horizontal: kSpaceXl, vertical: kSpaceLg)
    ..textStyle = const TextStyle(
      fontSize: 14,
      fontWeight: FontWeight.w600,
      color: Colors.white,
      height: 1.35,
    )
    ..maskColor = kPrimaryDarkColor.withValues(alpha: 0.35)
    ..animationStyle = EasyLoadingAnimationStyle.opacity
    ..animationDuration = kAnimationDuration
    ..userInteractions = true
    ..dismissOnTap = false;
}

void main() {
  // Réglé AVANT le premier écran : autrement, un chargement déclenché au
  // démarrage s'affichait encore avec les couleurs par défaut.
  configLoading();
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  // This widget is the root of your application.
  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Mon Gravier App',
      theme: AppTheme.lightTheme(context),
      initialRoute: SignInScreen.routeName,
      routes: routes,
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ],
      supportedLocales: const [
        Locale('fr'),
      ],
      navigatorKey: Get.key,
      locale: const Locale('fr'),
      builder: (context, child) {
        // Les réglages système de taille de police peuvent aller jusqu'à ×2 et
        // font alors déborder les cartes et les boutons. On accompagne
        // l'agrandissement jusqu'à ×1,3 — un vrai confort de lecture — sans
        // casser la mise en page au-delà.
        final echelle =
            MediaQuery.of(context).textScaler.clamp(maxScaleFactor: 1.3);
        return MediaQuery(
          data: MediaQuery.of(context).copyWith(textScaler: echelle),
          child: AnnotatedRegion<SystemUiOverlayStyle>(
            value: const SystemUiOverlayStyle(
              statusBarColor: Colors.transparent,
              // La barre de titre est au bleu de la marque : des icônes
              // sombres y seraient illisibles.
              statusBarIconBrightness: Brightness.light,
              statusBarBrightness: Brightness.dark,
              systemNavigationBarColor: kSurfaceColor,
              systemNavigationBarIconBrightness: Brightness.dark,
            ),
            child: EasyLoading.init()(context, child),
          ),
        );
      },
    );
  }
}
