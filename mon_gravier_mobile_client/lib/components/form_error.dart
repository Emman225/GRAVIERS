import 'package:flutter/material.dart';

import '../constants.dart';

/// ERREURS DE FORMULAIRE.
///
/// Elles s'affichaient en noir, dans la taille du corps de texte, précédées
/// d'un pictogramme SVG de 16 px : rien ne les distinguait d'une consigne
/// ordinaire. Un client qui remplissait mal un champ voyait apparaître une
/// ligne de plus — sans comprendre qu'on lui signalait un problème.
///
/// Elles sont maintenant regroupées dans un encart rouge pâle, avec un
/// pictogramme et un texte de la couleur d'erreur. Le mécanisme ne change pas :
/// la liste `errors` reste alimentée par les validateurs existants.
class FormError extends StatelessWidget {
  const FormError({
    super.key,
    required this.errors,
  });

  final List<String?> errors;

  @override
  Widget build(BuildContext context) {
    final messages = errors
        .where((e) => e != null && e.trim().isNotEmpty)
        .cast<String>()
        .toList();

    if (messages.isEmpty) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: kSpaceMd),
      padding:
          const EdgeInsets.symmetric(horizontal: kSpaceMd, vertical: kSpaceMd),
      decoration: BoxDecoration(
        color: kErrorSoftColor,
        borderRadius: BorderRadius.circular(kRadiusSm),
        border: Border.all(color: kErrorColor.withValues(alpha: 0.25)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          for (int i = 0; i < messages.length; i++)
            Padding(
              padding: EdgeInsets.only(
                  bottom: i == messages.length - 1 ? 0 : kSpaceSm),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.error_outline, size: 16, color: kErrorColor),
                  const SizedBox(width: kSpaceSm),
                  Expanded(
                    child: Text(
                      messages[i],
                      style: const TextStyle(
                        fontSize: 13,
                        height: 1.35,
                        color: kErrorColor,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
