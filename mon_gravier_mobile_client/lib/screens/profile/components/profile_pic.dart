import 'package:flutter/material.dart';
import 'package:mon_gravier_com/globale.dart';

import '../../../components/image_reseau.dart';
import '../../../constants.dart';

/// PHOTO DU CLIENT.
///
/// Trois ordres de priorité, du plus sûr au plus fragile :
///
///  1. LES OCTETS retenus au moment de l'enregistrement. C'est exactement
///     l'image que le client vient de choisir : ni cache, ni adresse, ni
///     réseau ne peuvent s'interposer.
///  2. L'ADRESSE distante, versionnée (`?v=N`) pour que le cache d'images de
///     Flutter ne resserve pas l'ancienne — le serveur écrit toujours au même
///     chemin, `imageUser/{id}.png`.
///  3. Le visuel par défaut.
///
/// La vignette s'abonne à `profilModifie` et se redessine SEULE : elle ne
/// dépend plus de la reconstruction de l'écran qui la contient.
class ProfilePic extends StatelessWidget {
  const ProfilePic({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<int>(
      valueListenable: profilModifie,
      builder: (context, _, __) {
        final adresse = adressePhotoProfil();
        final octets = photoProfilLocale;

        return Container(
          height: 104,
          width: 104,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: kSurfaceMutedColor,
            border: Border.all(color: kBorderColor, width: 2),
          ),
          child: ClipOval(
            child: octets != null
                ? Image.memory(
                    octets,
                    fit: BoxFit.cover,
                    // Les octets ne changent jamais sans que `profilModifie`
                    // ne bouge : inutile de recalculer le rendu à chaque
                    // reconstruction du parent.
                    gaplessPlayback: true,
                  )
                : adresse.isNotEmpty
                    ? ImageReseau(
                        url: adresse,
                        fit: BoxFit.cover,
                        icone: Icons.person_outline,
                      )
                    : Image.asset('assets/images/user.png', fit: BoxFit.cover),
          ),
        );
      },
    );
  }
}
