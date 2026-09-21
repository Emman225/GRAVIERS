import 'package:flutter/material.dart';

/// UNE OPTION À COCHER : ce que le client lit, et la clé envoyée au serveur.
class OptionFiltre {
  final String cle;
  final String libelle;

  const OptionFiltre({required this.cle, required this.libelle});
}

/// UN CRITÈRE : « Catégorie », « Produit », « Montant ».
class GroupeFiltre {
  final String cle;
  final String titre;
  final List<OptionFiltre> options;

  const GroupeFiltre({
    required this.cle,
    required this.titre,
    required this.options,
  });
}

/// LE PANNEAU DE FILTRES DU CATALOGUE.
///
/// Il remplace le panneau du paquet `amazon_like_filter`, retenu pour trois
/// raisons :
///
///  · ses boutons « Reset » et « Apply » sont écrits en dur dans le paquet —
///    aucun réglage ne permet de les traduire ;
///  · il rouvrait toujours vierge, sans montrer ce qui était déjà coché : le
///    client ne pouvait pas savoir quel filtre était actif, ni en retirer un ;
///  · les options lui étaient fournies depuis la liste AFFICHÉE. Une fois un
///    filtre posé, la liste rétrécit — et avec elle le choix proposé : on
///    pouvait restreindre encore, jamais revenir en arrière.
///
/// Celui-ci reçoit le catalogue COMPLET et la sélection en cours.
class FiltreProduits extends StatefulWidget {
  final String titre;
  final List<GroupeFiltre> groupes;

  /// Les clés déjà cochées, par critère. Le panneau s'ouvre dessus.
  final Map<String, List<String>> selection;

  /// Rendu à la validation : la nouvelle sélection, critère par critère.
  final void Function(Map<String, List<String>>) onValider;

  const FiltreProduits({
    super.key,
    required this.groupes,
    required this.selection,
    required this.onValider,
    this.titre = 'Recherchez un produit',
  });

  @override
  State<FiltreProduits> createState() => _FiltreProduitsState();
}

class _FiltreProduitsState extends State<FiltreProduits> {
  late Map<String, List<String>> _choix;
  String _recherche = '';

  @override
  void initState() {
    super.initState();
    // Copie : tant que le client n'a pas validé, la liste derrière le panneau
    // ne doit pas bouger. Fermer sans valider ne change donc rien.
    _choix = {
      for (final g in widget.groupes)
        g.cle: List<String>.from(widget.selection[g.cle] ?? const []),
    };
  }

  int get _nombreCoche =>
      _choix.values.fold(0, (somme, liste) => somme + liste.length);

  List<OptionFiltre> _optionsVisibles(GroupeFiltre groupe) {
    if (_recherche.trim().isEmpty) return groupe.options;

    final terme = _recherche.trim().toLowerCase();
    return groupe.options
        .where((o) => o.libelle.toLowerCase().contains(terme))
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final couleur = Theme.of(context).colorScheme;

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      widget.titre,
                      style: const TextStyle(
                          fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    tooltip: 'Fermer',
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: TextField(
                decoration: const InputDecoration(
                  hintText: 'Rechercher...',
                  prefixIcon: Icon(Icons.search),
                  border: OutlineInputBorder(),
                  isDense: true,
                ),
                onChanged: (v) => setState(() => _recherche = v),
              ),
            ),
            const SizedBox(height: 8),
            Flexible(
              child: ListView(
                shrinkWrap: true,
                children: widget.groupes.map((groupe) {
                  final visibles = _optionsVisibles(groupe);
                  final coches = _choix[groupe.cle] ?? const [];

                  return ExpansionTile(
                    key: PageStorageKey(groupe.cle),
                    // Le nombre de coches est écrit sur le titre : le critère
                    // actif se voit sans déplier.
                    title: Text(coches.isEmpty
                        ? groupe.titre
                        : '${groupe.titre} (${coches.length})'),
                    initiallyExpanded: coches.isNotEmpty,
                    children: visibles.isEmpty
                        ? [
                            const ListTile(
                              dense: true,
                              title: Text('Aucun résultat pour cette recherche'),
                            )
                          ]
                        : visibles.map((option) {
                            return CheckboxListTile(
                              dense: true,
                              controlAffinity: ListTileControlAffinity.leading,
                              value: coches.contains(option.cle),
                              title: Text(option.libelle),
                              onChanged: (choisi) {
                                setState(() {
                                  final liste = _choix[groupe.cle]!;
                                  if (choisi == true) {
                                    if (!liste.contains(option.cle)) {
                                      liste.add(option.cle);
                                    }
                                  } else {
                                    liste.remove(option.cle);
                                  }
                                });
                              },
                            );
                          }).toList(),
                  );
                }).toList(),
              ),
            ),
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
              // DEUX BOUTONS CÔTE À CÔTE, DE LARGEUR BORNÉE.
              //
              // Le thème de l'application impose à TOUT ElevatedButton une
              // largeur minimale de `double.infinity` — il est fait pour des
              // boutons pleine largeur, seuls sur leur ligne. Placé dans une
              // Row, un tel bouton réclame une largeur infinie, la mise en page
              // échoue et le bouton ne s'affiche pas du tout.
              //
              // `Expanded` borne la largeur et `minimumSize` neutralise
              // l'infini hérité du thème. Sans les deux, « Appliquer »
              // disparaît de l'écran.
              child: Row(
                children: [
                  Expanded(
                    child: TextButton(
                      style: TextButton.styleFrom(
                        minimumSize: const Size(0, 48),
                      ),
                      onPressed: _nombreCoche == 0
                          ? null
                          : () {
                              setState(() {
                                for (final g in widget.groupes) {
                                  _choix[g.cle] = [];
                                }
                              });
                            },
                      child: const Text('Réinitialiser'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: couleur.secondary,
                        minimumSize: const Size(0, 48),
                      ),
                      onPressed: () {
                        widget.onValider(_choix);
                        Navigator.of(context).pop();
                      },
                      child: Text(_nombreCoche == 0
                          ? 'Appliquer'
                          : 'Appliquer ($_nombreCoche)'),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
