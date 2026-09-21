import 'package:flutter/material.dart';

import '../constants.dart';

/// ÉCRAN SANS DONNÉES : DIRE CE QU'IL SE PASSE, ET QUOI FAIRE.
///
/// Une liste vide donnait une zone blanche. Rien ne distinguait « vous n'avez
/// encore rien commandé » de « le chargement a échoué » ou de « votre filtre ne
/// laisse rien passer » : dans les trois cas, l'écran ne disait rien, et le
/// client recommençait au hasard.
///
/// Trois éléments suffisent : un pictogramme discret, une phrase qui nomme la
/// situation, et — quand une suite existe — un bouton qui y mène.
class EtatVide extends StatelessWidget {
  const EtatVide({
    super.key,
    required this.titre,
    this.message,
    this.icone = Icons.inbox_outlined,
    this.libelleAction,
    this.action,
    this.compact = false,
  });

  /// Une phrase courte, du point de vue du client.
  final String titre;

  /// Le détail, facultatif : pourquoi, et ce qui changerait la situation.
  final String? message;

  final IconData icone;

  /// Bouton facultatif. Sans `action`, aucun bouton n'est dessiné.
  final String? libelleAction;
  final VoidCallback? action;

  /// Version resserrée, pour un encart à l'intérieur d'un écran déjà rempli.
  final bool compact;

  @override
  Widget build(BuildContext context) {
    // Le défilement sert les petits écrans : avec un message long, un bouton et
    // la police système agrandie, le bloc dépasse la place disponible.
    return Center(
      child: SingleChildScrollView(
        padding: EdgeInsets.symmetric(
          horizontal: kSpaceXl,
          vertical: compact ? kSpaceXl : kSpaceXxl,
        ),
        child: _contenu(),
      ),
    );
  }

  Widget _contenu() {
    final double taillePastille = compact ? 56 : 76;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: taillePastille,
          height: taillePastille,
          decoration: const BoxDecoration(
            color: kSurfaceMutedColor,
            shape: BoxShape.circle,
          ),
          child: Icon(
            icone,
            size: compact ? 26 : 34,
            color: kTextMutedColor,
          ),
        ),
        SizedBox(height: compact ? kSpaceMd : kSpaceLg),
        Text(
          titre,
          textAlign: TextAlign.center,
          style: kTitreSectionStyle.copyWith(fontSize: compact ? 15 : 17),
        ),
        if (message != null && message!.trim().isNotEmpty) ...[
          const SizedBox(height: kSpaceSm),
          // Une ligne de lecture bornée : au-delà, l'œil perd la ligne
          // suivante sur un écran de téléphone.
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 320),
            child: Text(
              message!,
              textAlign: TextAlign.center,
              style: kCorpsSecondaireStyle,
            ),
          ),
        ],
        if (action != null && (libelleAction ?? '').isNotEmpty) ...[
          SizedBox(height: compact ? kSpaceLg : kSpaceXl),
          SizedBox(
            width: 220,
            child: ElevatedButton(
              onPressed: action,
              child: Text(libelleAction!),
            ),
          ),
        ],
      ],
    );
  }
}

/// BLOC GRIS DE CHARGEMENT.
///
/// Sert à occuper la place d'un contenu qui arrive : une liste qui se remplit
/// sur une trame déjà en place paraît nettement plus rapide qu'une page blanche
/// qui bascule d'un coup, à durée d'attente identique.
class Squelette extends StatefulWidget {
  const Squelette({
    super.key,
    this.width,
    this.height = 14,
    this.rayon = kRadiusSm,
  });

  final double? width;
  final double height;
  final double rayon;

  @override
  State<Squelette> createState() => _SqueletteState();
}

class _SqueletteState extends State<Squelette>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controleur;

  @override
  void initState() {
    super.initState();
    // Une respiration lente et de faible amplitude. Un balayage clignotant
    // attire l'œil sur l'attente, ce qui la fait paraître plus longue.
    _controleur = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controleur.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween<double>(begin: 0.45, end: 1).animate(
        CurvedAnimation(parent: _controleur, curve: Curves.easeInOut),
      ),
      child: Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          color: kSurfaceMutedColor,
          borderRadius: BorderRadius.circular(widget.rayon),
        ),
      ),
    );
  }
}

/// Trame d'une carte de produit pendant le chargement du catalogue.
class SqueletteCarteProduit extends StatelessWidget {
  const SqueletteCarteProduit({super.key});

  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: Squelette(height: double.infinity, rayon: kRadiusMd)),
        SizedBox(height: kSpaceMd),
        Squelette(width: 110, height: 12),
        SizedBox(height: kSpaceSm),
        Squelette(width: 70, height: 14),
      ],
    );
  }
}
