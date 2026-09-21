import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../constants.dart';
import '../globale.dart';

/// LES CODES D'UNE LIVRAISON, POUR LE CLIENT SEUL.
///
/// Le client remet le code de livraison au livreur (il valide la course) et
/// le bon d'enlèvement au fournisseur (il libère la marchandise). Chacun se
/// copie d'un geste et s'envoie par WhatsApp — à un chauffeur, un chef de
/// chantier, un collègue — sans le retaper.
class CodesLivraison extends StatelessWidget {
  final String? codeLivraison;
  final String? codeEnlevement;
  final String? numeroCommande;

  const CodesLivraison({
    super.key,
    this.codeLivraison,
    this.codeEnlevement,
    this.numeroCommande,
  });

  @override
  Widget build(BuildContext context) {
    final lignes = <Widget>[];
    if ((codeLivraison ?? '').trim().isNotEmpty) {
      lignes.add(_UnCode(libelle: 'Code de livraison', valeur: codeLivraison!.trim(), numeroCommande: numeroCommande));
    }
    if ((codeEnlevement ?? '').trim().isNotEmpty) {
      lignes.add(_UnCode(libelle: "Bon d'enlèvement", valeur: codeEnlevement!.trim(), numeroCommande: numeroCommande));
    }
    if (lignes.isEmpty) return const SizedBox.shrink();
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: lignes);
  }
}

class _UnCode extends StatelessWidget {
  final String libelle;
  final String valeur;
  final String? numeroCommande;

  const _UnCode({required this.libelle, required this.valeur, this.numeroCommande});

  String get _message =>
      '$libelle MON GRAVIER'
      '${(numeroCommande ?? '').isNotEmpty ? ' (commande n° $numeroCommande)' : ''}'
      ' : $valeur';

  Future<void> _copier() async {
    await Clipboard.setData(ClipboardData(text: valeur));
    afficherSucces('$libelle copié : $valeur');
  }

  Future<void> _partager() async {
    try {
      await lancerUrl('https://wa.me/?text=${Uri.encodeComponent(_message)}');
    } catch (_) {
      afficherErreur("Impossible d'ouvrir WhatsApp sur ce téléphone.");
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: kSpaceXs),
      child: Row(
        children: [
          Expanded(
            child: RichText(
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              text: TextSpan(
                style: const TextStyle(fontSize: 12, color: kTextSecondaryColor),
                children: [
                  TextSpan(text: '$libelle : '),
                  // En rouge : c'est la valeur à remettre, elle doit se voir.
                  TextSpan(
                    text: valeur,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: kErrorColor,
                      letterSpacing: 1.2,
                    ),
                  ),
                ],
              ),
            ),
          ),
          IconButton(
            visualDensity: VisualDensity.compact,
            constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
            padding: EdgeInsets.zero,
            tooltip: 'Copier',
            icon: const Icon(Icons.copy_rounded, size: 18, color: kTextSecondaryColor),
            onPressed: _copier,
          ),
          IconButton(
            visualDensity: VisualDensity.compact,
            constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
            padding: EdgeInsets.zero,
            tooltip: 'Partager par WhatsApp',
            icon: const Icon(Icons.share_rounded, size: 18, color: Color(0xFF25D366)),
            onPressed: _partager,
          ),
        ],
      ),
    );
  }
}
