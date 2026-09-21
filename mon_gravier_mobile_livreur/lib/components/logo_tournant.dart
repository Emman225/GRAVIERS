import 'package:flutter/material.dart';

import '../constants.dart';

/// INDICATEUR DE CHARGEMENT : LE LOGO QUI TOURNE.
///
/// L'attente était signalée par un anneau blanc générique, celui de n'importe
/// quelle application. C'est pourtant l'élément que le client voit le plus
/// souvent — à chaque chargement de catalogue, de commande, de facture — et il
/// ne portait rien de la marque.
///
/// Le logo tourne à sa place, sur la pastille passée au bleu de marque. Le
/// disque blanc autour de lui est indispensable : l'anneau du logo est lui-même
/// bleu nuit, et se confondrait avec le fond.
class LogoTournant extends StatefulWidget {
  const LogoTournant({super.key, this.taille = 46});

  final double taille;

  @override
  State<LogoTournant> createState() => _LogoTournantState();
}

class _LogoTournantState extends State<LogoTournant>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controleur;

  @override
  void initState() {
    super.initState();
    // Un tour en 1,4 s : assez vif pour dire « ça travaille », assez lent pour
    // que le logo reste identifiable et ne devienne pas une tache.
    _controleur = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat();
  }

  @override
  void dispose() {
    _controleur.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return RotationTransition(
      turns: _controleur,
      child: Container(
        height: widget.taille,
        width: widget.taille,
        padding: const EdgeInsets.all(2),
        decoration: const BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
        ),
        child: ClipOval(
          child: Image.asset(
            'assets/images/logo.png',
            fit: BoxFit.cover,
            // Le logo manquant ne doit pas faire échouer un écran de
            // chargement : on retombe sur un anneau ordinaire.
            errorBuilder: (context, erreur, trace) => const Padding(
              padding: EdgeInsets.all(6),
              child: CircularProgressIndicator(
                strokeWidth: 3,
                valueColor: AlwaysStoppedAnimation<Color>(kPrimaryColor),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
