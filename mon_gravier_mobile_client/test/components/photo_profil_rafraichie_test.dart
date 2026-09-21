import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com/components/image_reseau.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/screens/profile/components/profile_pic.dart';

/// LA PHOTO DE PROFIL DOIT SUIVRE LA MODIFICATION.
///
/// Le client changeait sa photo depuis « Mes informations » ; elle était bien
/// enregistrée, bien renvoyée par le serveur, bien écrite dans `user.photo` —
/// et « Mon espace » continuait d'afficher l'ancienne.
///
/// Une première correction (redessiner l'écran au retour) N'A RIEN CHANGÉ, et
/// c'est ce qui a mis sur la piste : le défaut n'était pas dans l'écran mais
/// dans la vignette elle-même, écrite `const ProfilePic()`. Un widget `const`
/// renvoie toujours la MÊME instance ; `Element.updateChild` compare le nouveau
/// widget à l'ancien, les trouve identiques, et n'appelle pas `build`. La
/// vignette n'était donc jamais reconstruite, quoi que fasse l'écran parent.
///
/// Cet essai reproduit la situation : on change `user.photo`, on redessine, et
/// on vérifie que l'affichage a suivi. Il échoue si l'on remet le `const`.
void main() {
  /// Un porteur qui redessine sur commande, comme le fait l'écran « Mon
  /// espace » au retour de la modification.
  Widget porteur(GlobalKey<_HoteState> cle) => MaterialApp(home: _Hote(key: cle));

  setUp(() {
    user.photo = '';
    versionPhotoProfil = 0;
    photoProfilLocale = null;
  });

  tearDown(() {
    user.photo = '';
    versionPhotoProfil = 0;
    photoProfilLocale = null;
  });

  testWidgets('sans photo, la vignette montre le visuel par défaut',
      (tester) async {
    final cle = GlobalKey<_HoteState>();
    await tester.pumpWidget(porteur(cle));
    await tester.pump();

    expect(find.byType(ImageReseau), findsNothing);
    expect(find.byType(Image), findsOneWidget);
  });

  testWidgets('une photo enregistrée s\'affiche après redessin', (tester) async {
    final cle = GlobalKey<_HoteState>();
    await tester.pumpWidget(porteur(cle));
    await tester.pump();

    expect(find.byType(ImageReseau), findsNothing,
        reason: 'Au départ, aucune photo.');

    // Ce que fait l'enregistrement du profil : la valeur globale change...
    user.photo = 'https://exemple.invalid/photos/client-12.jpg';
    // ...puis l'écran se redessine au retour de « Mes informations ».
    cle.currentState!.redessiner();
    await tester.pump();

    expect(find.byType(ImageReseau), findsOneWidget,
        reason: 'La vignette doit relire user.photo au redessin. Si elle est '
            'construite en `const`, Flutter court-circuite sa reconstruction '
            'et elle reste sur le visuel par défaut.');
  });

  testWidgets('changer de photo change ce qui est affiché', (tester) async {
    user.photo = 'https://exemple.invalid/photos/ancienne.jpg';

    final cle = GlobalKey<_HoteState>();
    await tester.pumpWidget(porteur(cle));
    await tester.pump();

    ImageReseau vignette() =>
        tester.widget<ImageReseau>(find.byType(ImageReseau));
    expect(vignette().url, contains('ancienne.jpg'));

    user.photo = 'https://exemple.invalid/photos/nouvelle.jpg';
    cle.currentState!.redessiner();
    await tester.pump();

    expect(vignette().url, contains('nouvelle.jpg'),
        reason: "L'adresse affichée doit être la nouvelle.");
  });

  /// LE CAS RÉEL, celui qui a fait échouer les deux corrections précédentes.
  ///
  /// Le serveur enregistre la photo au MÊME chemin quoi qu'il arrive
  /// (`imageUser/{id}.png`, UtilisateurController::editProfil) : après un
  /// changement, `user.photo` vaut exactement ce qu'il valait avant. Aucune
  /// reconstruction de widget ne peut donc rien y faire — c'est l'ADRESSE
  /// demandée qui doit changer, sans quoi le cache d'images de Flutter répond
  /// avec l'ancienne.
  testWidgets('une photo remplacée à la MÊME adresse est bien rechargée',
      (tester) async {
    const memeAdresse = 'https://exemple.invalid/imageUser/12.png';
    user.photo = memeAdresse;

    final cle = GlobalKey<_HoteState>();
    await tester.pumpWidget(porteur(cle));
    await tester.pump();

    ImageReseau vignette() =>
        tester.widget<ImageReseau>(find.byType(ImageReseau));
    final avant = vignette().url;

    // Ce que fait l'enregistrement : l'adresse ne bouge PAS, seule la version
    // est incrémentée.
    versionPhotoProfil++;
    cle.currentState!.redessiner();
    await tester.pump();

    expect(vignette().url, isNot(avant),
        reason: "L'adresse demandée doit changer, sinon Flutter ressert "
            "l'image qu'il a déjà en cache et le client revoit l'ancienne "
            "photo.");
    expect(vignette().url, contains(memeAdresse),
        reason: 'Elle doit rester la même image, seulement versionnée.');
  });

  /// LE CHEMIN QUI NE DÉPEND DE RIEN.
  ///
  /// Ni du cache d'images, ni de l'adresse, ni du réseau, ni du serveur : la
  /// vignette affiche les OCTETS que le client vient de choisir. C'est la
  /// réponse aux trois corrections précédentes, qui visaient toutes le
  /// téléchargement et échouaient toutes.
  testWidgets("les octets retenus priment sur l'adresse distante",
      (tester) async {
    user.photo = 'https://exemple.invalid/imageUser/12.png';

    final cle = GlobalKey<_HoteState>();
    await tester.pumpWidget(porteur(cle));
    await tester.pump();

    expect(find.byType(ImageReseau), findsOneWidget,
        reason: "Sans octets retenus, c'est l'adresse distante qui sert.");

    // Ce que fait l'enregistrement d'une nouvelle photo.
    photoProfilLocale = _pngMinimal();
    profilModifie.value++;
    await tester.pump();

    expect(find.byType(Image), findsWidgets);
    expect(find.byType(ImageReseau), findsNothing,
        reason: "Les octets choisis doivent primer : plus aucun "
            "téléchargement n'entre en jeu.");
  });

  /// La vignette ne doit PAS dépendre de la reconstruction de son parent.
  ///
  /// C'est la dépendance qui a fait échouer la toute première correction :
  /// « Mon espace » est un onglet construit une fois pour toutes, et revenir
  /// de « Mes informations » ne le reconstruit pas nécessairement.
  testWidgets('se redessine seule, sans que le parent ne le demande',
      (tester) async {
    user.photo = 'https://exemple.invalid/imageUser/12.png';

    final cle = GlobalKey<_HoteState>();
    await tester.pumpWidget(porteur(cle));
    await tester.pump();

    expect(find.byType(ImageReseau), findsOneWidget);

    // On NE rappelle PAS `redessiner()` : seul le signal est émis.
    photoProfilLocale = _pngMinimal();
    profilModifie.value++;
    await tester.pump();

    expect(find.byType(ImageReseau), findsNothing,
        reason: "Le signal seul doit suffire à rafraîchir la vignette.");
  });
}

/// Le plus petit PNG valide : un pixel transparent. Suffit à `Image.memory`,
/// qui refuserait des octets quelconques.
Uint8List _pngMinimal() => Uint8List.fromList(const [
      0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A, //
      0x00, 0x00, 0x00, 0x0D, 0x49, 0x48, 0x44, 0x52,
      0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x01,
      0x08, 0x06, 0x00, 0x00, 0x00, 0x1F, 0x15, 0xC4,
      0x89, 0x00, 0x00, 0x00, 0x0A, 0x49, 0x44, 0x41,
      0x54, 0x78, 0x9C, 0x63, 0x00, 0x01, 0x00, 0x00,
      0x05, 0x00, 0x01, 0x0D, 0x0A, 0x2D, 0xB4, 0x00,
      0x00, 0x00, 0x00, 0x49, 0x45, 0x4E, 0x44, 0xAE,
      0x42, 0x60, 0x82,
    ]);

/// Reproduit l'écran « Mon espace » : il construit la vignette exactement comme
/// lui — sans `const`, avec une clé qui suit l'adresse de la photo.
class _Hote extends StatefulWidget {
  const _Hote({super.key});

  @override
  State<_Hote> createState() => _HoteState();
}

class _HoteState extends State<_Hote> {
  void redessiner() => setState(() {});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: ProfilePic(key: ValueKey(adressePhotoProfil())),
      ),
    );
  }
}