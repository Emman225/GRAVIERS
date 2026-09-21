import 'package:flutter/material.dart';

import '../constants.dart';

/// IMAGE DISTANTE, AVEC UN ÉTAT PENDANT ET UN ÉTAT APRÈS.
///
/// `Image.network` était appelé tel quel à dix-sept endroits : pendant le
/// téléchargement l'emplacement restait VIDE, et si l'image manquait ou si le
/// réseau lâchait, Flutter affichait sa propre icône d'erreur — un pictogramme
/// noir cassé, en anglais, sur fond gris. Sur une connexion mobile ivoirienne,
/// c'est l'état le plus fréquemment vu d'un catalogue.
///
/// Ce composant remplace les deux : un fond neutre qui occupe déjà la bonne
/// place pendant le chargement, puis un pictogramme discret si l'image ne vient
/// jamais. La mise en page ne bouge plus entre les deux.
///
/// `fit` reste volontairement nullable : `Image.network` sans `fit` applique
/// `BoxFit.scaleDown`, et changer cette valeur par défaut déplacerait des
/// images sur des écrans qu'on ne cherche pas à modifier.
class ImageReseau extends StatelessWidget {
  const ImageReseau({
    super.key,
    required this.url,
    this.fit,
    this.width,
    this.height,
    this.rayon = 0,
    this.icone = Icons.image_outlined,
    this.fondPlaceholder = kSurfaceMutedColor,
  });

  final String url;
  final BoxFit? fit;
  final double? width;
  final double? height;
  final double rayon;
  final IconData icone;
  final Color fondPlaceholder;

  bool get _urlUtilisable {
    final u = url.trim();
    return u.isNotEmpty && u != 'null' && Uri.tryParse(u)?.hasScheme == true;
  }

  @override
  Widget build(BuildContext context) {
    final Widget contenu = !_urlUtilisable
        ? _repli()
        : Image.network(
            url,
            fit: fit,
            width: width,
            height: height,
            loadingBuilder: (context, enfant, avancement) {
              if (avancement == null) return enfant;
              return _attente();
            },
            errorBuilder: (context, erreur, trace) => _repli(),
          );

    if (rayon <= 0) return contenu;
    return ClipRRect(
      borderRadius: BorderRadius.circular(rayon),
      child: contenu,
    );
  }

  /// Pendant le chargement : la place est déjà prise, rien ne sautera.
  Widget _attente() {
    return Container(
      width: width,
      height: height,
      color: fondPlaceholder,
      alignment: Alignment.center,
      child: const SizedBox(
        width: 18,
        height: 18,
        child: CircularProgressIndicator(
          strokeWidth: 2,
          valueColor: AlwaysStoppedAnimation<Color>(kTextMutedColor),
        ),
      ),
    );
  }

  /// Image absente ou illisible : un pictogramme sobre, jamais un écran cassé.
  Widget _repli() {
    return Container(
      width: width,
      height: height,
      color: fondPlaceholder,
      alignment: Alignment.center,
      child: Icon(icone, color: kTextMutedColor, size: 22),
    );
  }
}
