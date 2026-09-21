import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/products/products_search_screen.dart';

import '../../../constants.dart';

/// CHAMP DE RECHERCHE DE L'ACCUEIL.
///
/// Le comportement ne change pas : la recherche part à la validation du
/// clavier, avec le texte saisi en argument. Ce qui change, c'est que le champ
/// dit maintenant ce qu'on y cherche (« sable, gravier, ciment... ») au lieu
/// d'un « Recherchez ici... » qui n'apprend rien, et que le clavier affiche une
/// touche « Rechercher » plutôt qu'un retour à la ligne.
class SearchField extends StatelessWidget {
  const SearchField({super.key});

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      textInputAction: TextInputAction.search,
      onFieldSubmitted: (value) {
        final terme = value.trim();
        if (terme.isEmpty) return;
        Get.toNamed(ProductsSearchScreen.routeName, arguments: terme);
      },
      style: const TextStyle(fontSize: 14, color: kTextColor),
      decoration: InputDecoration(
        filled: true,
        fillColor: kSurfaceColor,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(
            horizontal: kSpaceLg, vertical: kSpaceMd),
        border: searchOutlineInputBorder,
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(kRadiusPill),
          borderSide: const BorderSide(color: kPrimaryColor, width: 1.4),
        ),
        enabledBorder: searchOutlineInputBorder,
        hintText: "Sable, gravier, ciment...",
        hintStyle: const TextStyle(fontSize: 14, color: kTextMutedColor),
        floatingLabelBehavior: FloatingLabelBehavior.never,
        prefixIcon: const Icon(Icons.search, size: 20, color: kTextMutedColor),
        prefixIconConstraints:
            const BoxConstraints(minWidth: 42, minHeight: 42),
      ),
    );
  }
}

final OutlineInputBorder searchOutlineInputBorder = OutlineInputBorder(
  borderRadius: BorderRadius.circular(kRadiusPill),
  borderSide: BorderSide.none,
);
