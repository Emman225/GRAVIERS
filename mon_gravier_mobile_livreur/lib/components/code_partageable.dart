import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../constants.dart';
import '../globale.dart';

/// UN CODE À REMETTRE, COPIABLE ET PARTAGEABLE PAR WHATSAPP.
///
/// Le livreur présente le numéro du bon d'enlèvement au fournisseur pour
/// retirer la marchandise. Il le recopiait à la main ou le lisait au
/// téléphone : un geste le copie, un autre l'envoie par WhatsApp — au
/// fournisseur, à un collègue, au client.
class CodePartageable extends StatelessWidget {
  final String libelle;
  final String valeur;
  /// Précision ajoutée au message envoyé (« livraison n° … »), facultative.
  final String? contexte;

  const CodePartageable({
    super.key,
    required this.libelle,
    required this.valeur,
    this.contexte,
  });

  String get _message =>
      '$libelle MON GRAVIER'
      '${(contexte ?? '').trim().isNotEmpty ? ' (${contexte!.trim()})' : ''}'
      ' : $valeur';

  Future<void> _copier() async {
    await Clipboard.setData(ClipboardData(text: valeur));
    afficherSucces('$libelle copié : $valeur');
  }

  Future<void> _partager() async {
    final uri = Uri.parse('https://wa.me/?text=${Uri.encodeComponent(_message)}');
    try {
      final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!ok) afficherErreur("Impossible d'ouvrir WhatsApp sur ce téléphone.");
    } catch (_) {
      afficherErreur("Impossible d'ouvrir WhatsApp sur ce téléphone.");
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(kSpaceMd, kSpaceSm, kSpaceSm, kSpaceSm),
      decoration: BoxDecoration(
        color: kPrimarySoftColor,
        borderRadius: BorderRadius.circular(kRadiusMd),
        border: Border.all(color: kBorderFortColor),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  libelle.toUpperCase(),
                  style: const TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.6,
                    color: kTextSecondaryColor,
                  ),
                ),
                const SizedBox(height: 2),
                // En rouge : c'est LA valeur que le livreur doit lire au
                // fournisseur, elle doit sauter aux yeux.
                Text(
                  valeur,
                  style: const TextStyle(
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.4,
                    color: kErrorColor,
                  ),
                ),
              ],
            ),
          ),
          Tooltip(
            message: 'Copier',
            child: Material(
              color: kSurfaceColor,
              borderRadius: BorderRadius.circular(kRadiusSm),
              child: InkWell(
                borderRadius: BorderRadius.circular(kRadiusSm),
                onTap: _copier,
                child: const Padding(
                  padding: EdgeInsets.all(9),
                  child: Icon(Icons.copy_rounded, size: 20, color: kPrimaryColor),
                ),
              ),
            ),
          ),
          const SizedBox(width: kSpaceSm),
          Tooltip(
            message: 'Partager par WhatsApp',
            child: Material(
              color: const Color(0xFF25D366),
              borderRadius: BorderRadius.circular(kRadiusSm),
              child: InkWell(
                borderRadius: BorderRadius.circular(kRadiusSm),
                onTap: _partager,
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 11, vertical: 9),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.share_rounded, size: 18, color: Colors.white),
                      SizedBox(width: 6),
                      Text('WhatsApp',
                          style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
