/// Intitulé complet d'un régime d'imposition, à partir du code stocké.
///
/// Le serveur conserve un code court (RNI, RSI, RME, RE) ; les fiches plus
/// anciennes portent encore l'ancien libellé complet. Les deux formes sont
/// comprises, comme sur le site (App\Support\RegimeImposition).
const Map<String, String> regimesImposition = {
  'RNI': "Réel normal d'imposition",
  'RSI': "Réel simplifié d'imposition",
  'RME': 'Régime des micro-entreprises',
  'RE': "Taxe d'État de l'Entreprenant (TEE)",
};

String libelleRegimeImposition(String? valeur) {
  final v = (valeur ?? '').trim();
  if (v.isEmpty) return '';
  final majuscules = v.toUpperCase();
  for (final code in regimesImposition.keys) {
    if (majuscules == code ||
        RegExp('^' + code + r'\s*[—\-–]').hasMatch(majuscules)) {
      return regimesImposition[code]!;
    }
  }
  return v;
}
