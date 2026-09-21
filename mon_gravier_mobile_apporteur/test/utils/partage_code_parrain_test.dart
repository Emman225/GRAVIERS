import 'package:flutter_test/flutter_test.dart';

/// LE MESSAGE DE PARTAGE DU CODE PARRAIN.
///
/// La fonction reelle vit dans globale.dart, qui charge tout l'attirail Flutter
/// et des greffons impossibles a instancier ici. On rejoue donc la MEME
/// construction, pour tenir ce qui compte : le message porte le code, et le
/// lien porte le code aussi — sans quoi le filleul devrait le retaper.
String construireMessage(String code, String site) {
  final lien = '${site}client/register?code_promo=$code';

  return "Bonjour ! Commandez sable, gravier, ciment et location "
      "d'engins sur GRAVIER.COM.\n\n"
      "Utilisez mon code parrain *$code* a l'inscription :\n$lien";
}

String construireAdresseWhatsapp(String message) {
  return 'https://wa.me/?text=${Uri.encodeComponent(message)}';
}

void main() {
  const site = 'https://graviers.fneconnect.net/';

  test('le message nomme le code parrain', () {
    final message = construireMessage('APP-1234', site);

    expect(message.contains('APP-1234'), isTrue);
  });

  test("le lien porte le code, pour que le filleul n'ait rien a retaper", () {
    final message = construireMessage('APP-1234', site);

    expect(
      message.contains('https://graviers.fneconnect.net/client/register?code_promo=APP-1234'),
      isTrue,
      reason: "Sans le code dans le lien, le partage ne vaut pas mieux qu'un copier-coller.",
    );
  });

  test("l'adresse WhatsApp encode le message", () {
    // Un message brut casserait l'adresse au premier espace ou saut de ligne :
    // WhatsApp recevrait un texte tronque.
    final adresse = construireAdresseWhatsapp(construireMessage('APP-1234', site));

    expect(adresse.startsWith('https://wa.me/?text='), isTrue);
    expect(adresse.contains(' '), isFalse, reason: "Aucun espace ne doit rester dans l'adresse.");
    expect(adresse.contains('\n'), isFalse, reason: "Aucun saut de ligne ne doit rester.");
    expect(Uri.tryParse(adresse), isNotNull);
  });

  test('un code contenant des caracteres a encoder ne casse pas l adresse', () {
    final adresse = construireAdresseWhatsapp(construireMessage('APP 12&34', site));

    expect(Uri.tryParse(adresse), isNotNull);
    expect(adresse.contains('APP 12&34'), isFalse,
        reason: 'Le code doit etre encode, sinon « & » couperait le parametre.');
  });
}
