import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'constants.dart';

/// ------------------------------------------------------------------
/// THÈME DE L'APPLICATION
/// ------------------------------------------------------------------
///
/// Le thème d'origine ne réglait presque rien : trois couleurs de texte, un
/// champ de saisie en gélule (rayon 28, 42 px de marge à gauche) et un bouton.
/// Tout le reste — cartes, onglets, boîtes de dialogue, barre de navigation —
/// tombait sur les valeurs par défaut de Material, chaque écran corrigeant
/// ensuite dans son coin. D'où des boutons verts ici, bleus là, des champs
/// ronds sur un écran et carrés sur le suivant.
///
/// Tout est désormais décidé ICI. Un écran qui n'impose rien hérite du bon
/// style : c'est ce qui rend l'ensemble cohérent sans réécrire les écrans.
class AppTheme {
  static ThemeData lightTheme(BuildContext context) {
    final base = ThemeData.light(useMaterial3: false);

    return base.copyWith(
      scaffoldBackgroundColor: kScaffoldColor,
      canvasColor: kSurfaceColor,
      primaryColor: kPrimaryColor,
      dividerColor: kBorderColor,
      splashFactory: InkRipple.splashFactory,
      visualDensity: VisualDensity.adaptivePlatformDensity,

      colorScheme: const ColorScheme.light(
        primary: kPrimaryColor,
        onPrimary: Colors.white,
        secondary: kAccentColor,
        surfaceTint: Colors.transparent,
        onSecondary: Colors.white,
        surface: kSurfaceColor,
        onSurface: kTextColor,
        error: kErrorColor,
        onError: Colors.white,
      ),

      textTheme: _texte(base.textTheme),
      primaryTextTheme: _texte(base.primaryTextTheme),

      // ---------------------------------------------------------------
      // BARRE DE TITRE
      // ---------------------------------------------------------------
      // NAVY, la couleur de l'anneau du logo.
      //
      // Elle était blanche, sur un fond d'écran lui-même quasi blanc, au-dessus
      // de cartes blanches : l'application n'affichait pratiquement aucune
      // couleur de marque, et rien ne séparait la barre du contenu. Le navy
      // ancre le haut de chaque écran et fait exister le logo ailleurs que sur
      // l'écran de connexion.
      appBarTheme: const AppBarTheme(
        // LE MÊME BLEU QUE LE BANDEAU D'ACCUEIL.
        //
        // La barre de titre était au navy le plus sombre du logo, tandis que la
        // section qui la suit sur l'accueil part de l'indigo : deux bleus se
        // succédaient sans raison, et la barre paraissait plus lourde que le
        // reste. C'est désormais le bleu du bandeau, partout.
        backgroundColor: kPrimaryColor,
        foregroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        iconTheme: IconThemeData(color: Colors.white, size: 22),
        actionsIconTheme: IconThemeData(color: Colors.white, size: 22),
        titleTextStyle: TextStyle(
          fontFamily: "Muli",
          fontSize: 18,
          fontWeight: FontWeight.w700,
          color: Colors.white,
          height: 1.3,
          letterSpacing: -0.2,
        ),
        systemOverlayStyle: SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.light,
          statusBarBrightness: Brightness.dark,
        ),
      ),

      // ---------------------------------------------------------------
      // CHAMPS DE SAISIE
      // ---------------------------------------------------------------
      // Rayon 14 au lieu de 28, marge 16 au lieu de 42 : le texte saisi
      // commençait à quatre centimètres du bord, et un libellé un peu long
      // était tronqué. Le champ est rempli d'un gris très clair — le contour
      // seul obligeait à un trait sombre pour rester visible.
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: kSurfaceMutedColor,
        floatingLabelBehavior: FloatingLabelBehavior.always,
        isDense: true,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: kSpaceLg, vertical: 16),
        border: outlineInputBorder(),
        enabledBorder: outlineInputBorder(),
        focusedBorder:
            outlineInputBorder(couleur: kPrimaryColor, epaisseur: 1.6),
        errorBorder: outlineInputBorder(couleur: kErrorColor),
        focusedErrorBorder:
            outlineInputBorder(couleur: kErrorColor, epaisseur: 1.6),
        disabledBorder: outlineInputBorder(couleur: kBorderColor),
        labelStyle: const TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          color: kTextSecondaryColor,
        ),
        floatingLabelStyle: const TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w700,
          color: kPrimaryColor,
        ),
        hintStyle: const TextStyle(
          fontSize: 14,
          color: kTextMutedColor,
          fontWeight: FontWeight.w400,
        ),
        errorStyle: const TextStyle(
          fontSize: 12,
          height: 1.2,
          color: kErrorColor,
          fontWeight: FontWeight.w600,
        ),
        prefixIconColor: kTextMutedColor,
        suffixIconColor: kTextMutedColor,
      ),

      // ---------------------------------------------------------------
      // BOUTONS
      // ---------------------------------------------------------------
      // Action PRINCIPALE : plein, bleu de marque, 52 px de haut (le minimum
      // confortable au doigt est 48).
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          elevation: 0,
          backgroundColor: kPrimaryColor,
          foregroundColor: Colors.white,
          disabledBackgroundColor: kSurfaceMutedColor,
          disabledForegroundColor: kTextMutedColor,
          minimumSize: const Size(double.infinity, 52),
          padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(kRadiusMd),
          ),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.1,
          ),
        ),
      ),

      // Action SECONDAIRE : contour, même gabarit, même rayon.
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: kPrimaryColor,
          backgroundColor: Colors.transparent,
          minimumSize: const Size(0, 48),
          padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
          side: const BorderSide(color: kBorderColor, width: 1.4),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(kRadiusMd),
          ),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        ),
      ),

      // Action TERTIAIRE : texte seul, sans fond.
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: kPrimaryColor,
          minimumSize: const Size(0, 44),
          padding: const EdgeInsets.symmetric(
              horizontal: kSpaceMd, vertical: kSpaceSm),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(kRadiusSm),
          ),
          textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
        ),
      ),

      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: kPrimaryColor,
        foregroundColor: Colors.white,
        elevation: 3,
        highlightElevation: 4,
        splashColor: kPrimaryDarkColor,
      ),

      iconTheme: const IconThemeData(color: kTextSecondaryColor, size: 22),

      // ---------------------------------------------------------------
      // SURFACES
      // ---------------------------------------------------------------
      cardTheme: CardThemeData(
        color: kSurfaceColor,
        elevation: 0,
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(kRadiusMd),
          side: const BorderSide(color: kBorderColor),
        ),
      ),

      dividerTheme: const DividerThemeData(
        color: kBorderColor,
        thickness: 1,
        space: kSpaceXl,
      ),

      listTileTheme: const ListTileThemeData(
        iconColor: kTextSecondaryColor,
        textColor: kTextColor,
        contentPadding: EdgeInsets.symmetric(horizontal: kSpaceLg),
      ),

      chipTheme: ChipThemeData(
        backgroundColor: kSurfaceMutedColor,
        selectedColor: kPrimarySoftColor,
        side: const BorderSide(color: kBorderColor),
        labelStyle: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: kTextSecondaryColor,
        ),
        padding: const EdgeInsets.symmetric(
            horizontal: kSpaceMd, vertical: kSpaceXs),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(kRadiusPill),
        ),
      ),

      // ---------------------------------------------------------------
      // NAVIGATION
      // ---------------------------------------------------------------
      // Les libellés des onglets NON sélectionnés étaient masqués : quatre
      // pictogrammes gris identiques, sans un mot pour les distinguer.
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: kSurfaceColor,
        selectedItemColor: kPrimaryColor,
        unselectedItemColor: kTextMutedColor,
        type: BottomNavigationBarType.fixed,
        elevation: 0,
        showSelectedLabels: true,
        showUnselectedLabels: true,
        selectedLabelStyle: TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
        unselectedLabelStyle:
            TextStyle(fontSize: 11, fontWeight: FontWeight.w500),
      ),

      tabBarTheme: const TabBarThemeData(
        labelColor: kPrimaryColor,
        unselectedLabelColor: kTextMutedColor,
        indicatorColor: kPrimaryColor,
        indicatorSize: TabBarIndicatorSize.tab,
        dividerColor: Colors.transparent,
        labelStyle: TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
        unselectedLabelStyle:
            TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
      ),

      // ---------------------------------------------------------------
      // DIALOGUES ET FEUILLES
      // ---------------------------------------------------------------
      dialogTheme: DialogThemeData(
        backgroundColor: kSurfaceColor,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(kRadiusLg),
        ),
        titleTextStyle: kTitreSectionStyle.copyWith(fontSize: 17),
        contentTextStyle: kCorpsStyle.copyWith(color: kTextSecondaryColor),
      ),

      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: kSurfaceColor,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(kRadiusLg)),
        ),
        clipBehavior: Clip.antiAlias,
      ),

      snackBarTheme: SnackBarThemeData(
        backgroundColor: kTextColor,
        contentTextStyle: const TextStyle(fontSize: 14, color: Colors.white),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(kRadiusMd),
        ),
      ),

      // ---------------------------------------------------------------
      // CONTRÔLES
      // ---------------------------------------------------------------
      checkboxTheme: CheckboxThemeData(
        fillColor: WidgetStateProperty.resolveWith(
          (etats) => etats.contains(WidgetState.selected)
              ? kPrimaryColor
              : Colors.transparent,
        ),
        checkColor: WidgetStateProperty.all(Colors.white),
        side: const BorderSide(color: kSecondaryColor, width: 1.6),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(kSpaceXs),
        ),
        materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
      ),

      radioTheme: RadioThemeData(
        fillColor: WidgetStateProperty.resolveWith(
          (etats) => etats.contains(WidgetState.selected)
              ? kPrimaryColor
              : kSecondaryColor,
        ),
      ),

      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith(
          (etats) => etats.contains(WidgetState.selected)
              ? kPrimaryColor
              : Colors.white,
        ),
        trackColor: WidgetStateProperty.resolveWith(
          (etats) => etats.contains(WidgetState.selected)
              ? kPrimarySoftColor
              : kSurfaceMutedColor,
        ),
      ),

      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: kPrimaryColor,
        linearTrackColor: kSurfaceMutedColor,
        circularTrackColor: kSurfaceMutedColor,
      ),

      // Transition d'écran : glissement latéral sur Android comme sur iOS.
      // La transition Android par défaut (montée verticale) donne le sentiment
      // d'ouvrir une fenêtre, pas d'avancer dans un parcours.
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: CupertinoPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
        },
      ),
    );
  }

  /// Échelle typographique. Muli reste la police de la marque ; ce sont les
  /// tailles, les graisses et les interlignes qui manquaient.
  static TextTheme _texte(TextTheme base) {
    return base
        .copyWith(
          displayLarge: headingStyle.copyWith(fontSize: 30),
          displayMedium: headingStyle.copyWith(fontSize: 26),
          displaySmall: headingStyle,
          headlineMedium: kTitreEcranStyle,
          headlineSmall: kTitreSectionStyle.copyWith(fontSize: 18),
          titleLarge: kTitreSectionStyle.copyWith(fontSize: 17),
          titleMedium: kTitreSectionStyle,
          titleSmall: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: kTextColor,
            height: 1.35,
          ),
          bodyLarge: kCorpsStyle.copyWith(fontSize: 15),
          bodyMedium: kCorpsStyle,
          bodySmall: kCorpsSecondaireStyle,
          labelLarge: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w700,
            color: kTextColor,
          ),
          labelMedium: kLegendeStyle.copyWith(fontWeight: FontWeight.w600),
          labelSmall: kEtiquetteStyle.copyWith(color: kTextMutedColor),
        )
        .apply(fontFamily: "Muli");
  }
}
