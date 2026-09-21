import '../models/retour_liste_commission.dart';

/// COMMENT S'ÉCRIT UNE LIGNE DE COMMISSION.
///
/// Constaté le 01/09/2026 sur l'application apporteur : une ligne affichait
/// « null null (# null) — Total : 0 F ». Le libellé était construit par
/// interpolation directe de trois champs — `'${c.nom} ${c.prenom} (# ${c.clientId})'` —
/// et le mot « null » de Dart passait tel quel à l'écran.
///
/// La chaîne commission -> affaire -> client se rompt côté serveur quand la
/// commission n'est rattachée à aucune affaire. La commission, elle, est DUE et
/// son montant est juste : il vit sur la ligne elle-même. On ne masque donc pas
/// la ligne — on dit ce qu'on ne sait pas, et on invite à le signaler.
///
/// Ces deux fonctions vivent hors de l'écran pour être éprouvées.

/// Nom et prénom, sans le « null » de Dart ni l'espace en trop.
///
/// `client.prenom` est NULLABLE en base : l'interpolation directe
/// `'\${nom} \${prenom}'` écrivait « KOUASSI null » pour ces clients-là.
String nomComplet(String? nom, String? prenom) => [nom, prenom]
    .where((m) => (m ?? '').trim().isNotEmpty)
    .join(' ')
    .trim();

/// L'affaire à l'origine de la commission a-t-elle été retrouvée ?
bool affaireConnue(UneCommission c) =>
    c.clientId != null || (c.nom ?? '').trim().isNotEmpty;

/// Le libellé affiché en tête de ligne. Ne rend JAMAIS « null ».
String libelleClient(UneCommission c) {
  if (!affaireConnue(c)) {
    // « Affaire non rattachée » était MON vocabulaire, pas celui de
    // l'apporteur : la ligne était illisible pour lui. Ce qui lui manque
    // ici, c'est le nom de son filleul — on le dit avec ce mot-là.
    return 'Filleul non identifié';
  }

  final nom = nomComplet(c.nom, c.prenom);

  final ref = c.clientId != null ? ' (# ${c.clientId})' : '';

  return (nom.isEmpty ? 'Client' : nom) + ref;
}

/// La deuxième ligne : le montant de l'affaire quand on le connaît.
///
/// Annoncer « 0 F » un montant inconnu serait un chiffre FAUX, pas une
/// absence. Mais « Montant de l'affaire indisponible » ne disait rien à
/// l'apporteur non plus. À défaut du montant, on lui donne la seule chose
/// qui l'aide à situer la ligne : la DATE à laquelle elle lui a été acquise.
String libelleMontantAffaire(
  UneCommission c,
  String Function(double) formater, {
  String Function(String)? formaterDate,
}) {
  if (affaireConnue(c)) {
    return 'Total: ${formater(c.montantTotal ?? 0)}';
  }

  final quand = (formaterDate != null && (c.createdAt ?? '').isNotEmpty)
      ? formaterDate(c.createdAt!)
      : '';

  return quand.isEmpty
      ? 'Demandez le détail à votre agence'
      : 'Acquise le $quand — détail auprès de votre agence';
}
