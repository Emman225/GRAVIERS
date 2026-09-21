import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../constants.dart';

/// COMPTEUR DE QUANTITÉ « − [saisie] + ».
///
/// Remplace `ItemCount` du paquet `item_count_number_button`, dont les signes
/// « − » et « + » sont écrits EN DUR en noir et dont la valeur n'est PAS
/// saisissable : pour passer de 1 à 40 tonnes, il fallait appuyer 390 fois sur
/// « + » (pas de 0,1).
///
/// La valeur se tape désormais au clavier, entre les deux boutons. Les bornes,
/// le pas, le nombre de décimales et le rappel `onChanged` sont ceux d'avant.
/// Aucune dépendance n'est ajoutée — celle-ci n'est plus utilisée.
class CompteurQuantite extends StatefulWidget {
  const CompteurQuantite({
    super.key,
    required this.valeurInitiale,
    required this.onChanged,
    this.minimum = 1,
    this.maximum = 1000,
    this.pas = 0.1,
    this.decimales = 1,
    this.couleur = kPrimaryColor,
    this.saisissable = true,
  });

  final double valeurInitiale;
  final ValueChanged<double> onChanged;
  final double minimum;
  final double maximum;
  final double pas;
  final int decimales;
  final Color couleur;

  /// Certains écrans n'affichent la quantité qu'en lecture.
  final bool saisissable;

  @override
  State<CompteurQuantite> createState() => _CompteurQuantiteState();
}

class _CompteurQuantiteState extends State<CompteurQuantite> {
  late double _valeur;
  late final TextEditingController _champ;
  late final FocusNode _focus;

  @override
  void initState() {
    super.initState();
    _valeur = _borne(widget.valeurInitiale);
    _champ = TextEditingController(text: _texte(_valeur));
    _focus = FocusNode();
    // À la sortie du champ, on remet la valeur au propre : une saisie vide ou
    // hors bornes ne doit pas rester affichée telle quelle.
    _focus.addListener(() {
      if (!_focus.hasFocus) _normaliser();
    });
  }

  @override
  void dispose() {
    _champ.dispose();
    _focus.dispose();
    super.dispose();
  }

  /// L'arrondi se fait sur la MÊME base que l'ancien composant : la valeur
  /// rendue est celle qui s'affiche, jamais une valeur flottante approchée.
  double _arrondi(double v) =>
      double.parse(v.toStringAsFixed(widget.decimales));

  double _borne(double v) {
    if (v < widget.minimum) return widget.minimum;
    if (v > widget.maximum) return widget.maximum;
    return _arrondi(v);
  }

  String _texte(double v) => '${num.parse(v.toStringAsFixed(widget.decimales))}';

  void _appliquer(double cible, {bool remettreLeTexte = true}) {
    final double v = _borne(cible);
    if (v == _valeur) {
      if (remettreLeTexte && _champ.text != _texte(v)) {
        _champ.text = _texte(v);
      }
      return;
    }
    setState(() => _valeur = v);
    if (remettreLeTexte) _champ.text = _texte(v);
    widget.onChanged(v);
  }

  void _modifier(double delta) {
    _focus.unfocus();
    _appliquer(_arrondi(_valeur + delta));
  }

  /// Saisie en cours : on accepte, mais sans reformater sous les doigts du
  /// client — corriger « 1 » en « 1.0 » pendant la frappe rendrait le champ
  /// inutilisable.
  void _saisie(String texte) {
    // Clavier français : la virgule est le séparateur décimal par défaut.
    final double? v = double.tryParse(texte.trim().replaceAll(',', '.'));
    if (v == null) return;
    if (v < widget.minimum || v > widget.maximum) return;
    _appliquer(v, remettreLeTexte: false);
  }

  void _normaliser() {
    final double? v =
        double.tryParse(_champ.text.trim().replaceAll(',', '.'));
    _appliquer(v ?? _valeur);
  }

  @override
  Widget build(BuildContext context) {
    final bool peutRetirer = _arrondi(_valeur - widget.pas) >= widget.minimum;
    final bool peutAjouter = _arrondi(_valeur + widget.pas) <= widget.maximum;

    return Container(
      decoration: BoxDecoration(
        color: kSurfaceColor,
        borderRadius: BorderRadius.circular(kRadiusSm),
        border: Border.all(color: kBorderFortColor),
      ),
      clipBehavior: Clip.antiAlias,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _Bouton(
            icone: Icons.remove,
            couleur: widget.couleur,
            actif: peutRetirer,
            onTap: () => _modifier(-widget.pas),
          ),
          SizedBox(
            width: 62,
            height: 38,
            child: widget.saisissable
                ? TextField(
                    controller: _champ,
                    focusNode: _focus,
                    onChanged: _saisie,
                    onEditingComplete: () {
                      _normaliser();
                      _focus.unfocus();
                    },
                    textAlign: TextAlign.center,
                    keyboardType: TextInputType.numberWithOptions(
                        decimal: widget.decimales > 0),
                    textInputAction: TextInputAction.done,
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(
                          RegExp(widget.decimales > 0 ? r'[0-9.,]' : r'[0-9]')),
                    ],
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: kTextColor,
                      fontFeatures: [FontFeature.tabularFigures()],
                    ),
                    decoration: const InputDecoration(
                      isDense: true,
                      filled: true,
                      fillColor: kSurfaceColor,
                      contentPadding: EdgeInsets.symmetric(horizontal: 4),
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                      floatingLabelBehavior: FloatingLabelBehavior.never,
                    ),
                  )
                : Center(
                    child: Text(
                      _texte(_valeur),
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: kTextColor,
                        fontFeatures: [FontFeature.tabularFigures()],
                      ),
                    ),
                  ),
          ),
          _Bouton(
            icone: Icons.add,
            couleur: widget.couleur,
            actif: peutAjouter,
            onTap: () => _modifier(widget.pas),
          ),
        ],
      ),
    );
  }
}

/// Un des deux boutons. Signe BLANC sur fond de marque — et grisé, sans être
/// invisible, quand la borne est atteinte : l'ancien composant ne signalait
/// jamais qu'on ne pouvait pas descendre plus bas.
class _Bouton extends StatelessWidget {
  const _Bouton({
    required this.icone,
    required this.couleur,
    required this.actif,
    required this.onTap,
  });

  final IconData icone;
  final Color couleur;
  final bool actif;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: actif ? couleur : couleur.withValues(alpha: 0.35),
      child: InkWell(
        onTap: actif ? onTap : null,
        child: SizedBox(
          // 38 px : au-dessus de la cible tactile minimale, sans écraser la
          // ligne d'article.
          height: 38,
          width: 38,
          child: Icon(icone, size: 18, color: Colors.white),
        ),
      ),
    );
  }
}
