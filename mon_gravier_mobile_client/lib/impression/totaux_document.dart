/// Les totaux d'un document fiscal, calculés depuis ses lignes.
///
/// POURQUOI CETTE CLASSE EXISTE
///
/// Trois documents — bon de commande, devis, bon de location — calculaient
/// leurs totaux chacun de leur côté, et tous les trois de la même façon
/// fautive :
///
///   1. le TOTAL HT ne comptait que les articles, alors que la ligne de
///      livraison figure dans le tableau juste au-dessus. La colonne
///      « Montant HT » ne s'additionnait donc pas à son propre total ;
///
///   2. le TOTAL A PAYER faisait confiance à `montant_total`, dont la
///      signification CHANGE selon le canal : le site y range le HT, le mobile
///      y range le net. Le même bon annonçait 179 230 F s'il venait du mobile
///      et 7 480 F s'il venait du site — pour une commande que la liste de
///      l'application affichait, elle, à 12 826 F.
///
/// La règle vit désormais ici, en un seul endroit. Elle reproduit exactement
/// ce que le récapitulatif du site affiche déjà, qui fait référence :
///
///   TOTAL HT   = articles + livraison        (la somme du tableau)
///   TOTAL TTC  = TOTAL HT + TVA
///   A PAYER    = TOTAL TTC − remise
///
/// La TVA porte sur les articles et, quand le paramétrage « Appliquer la TVA
/// au transport » l'a décidé au moment de l'affaire, sur la ligne de livraison
/// (mention « TVA (18%) » en colonne Taxes). Depuis le 09/09/2026, les documents
/// n'ont qu'UNE ligne « TVA » (articles + transport) et qu'un sous-total dans le
/// résumé fiscal : `assietteTva` et `tvaDocument`, comme sur le site.
class TotauxDocument {
  /// Somme des lignes d'articles. C'est l'assiette de la TVA.
  final double htArticles;

  /// Coût de livraison, non taxé, mais bien une ligne du tableau.
  final double livraison;

  /// TVA telle que le serveur l'a calculée — on ne la recalcule pas ici :
  /// elle tient compte de la remise et du régime du client.
  final double tva;

  /// Remise accordée, retranchée du total à payer.
  final double remise;

  /// TVA sur le transport (point 5), quand le paramétrage l'applique.
  /// Figée sur l'affaire par le serveur, comme la TVA des articles.
  final double tvaTransport;

  const TotauxDocument({
    required this.htArticles,
    this.livraison = 0,
    this.tva = 0,
    this.remise = 0,
    this.tvaTransport = 0,
  });

  /// Le TOTAL HT du document : tout ce que porte le tableau.
  double get htDocument => htArticles + livraison;

  /// La TVA du document : celle des articles plus celle du transport (09/09/2026,
  /// une seule ligne « TVA », comme sur le site).
  double get tvaDocument => tva + tvaTransport;

  /// L'assiette taxable du résumé fiscal : les articles, plus le transport
  /// lorsqu'il est taxé. Un transport non taxé reste hors de l'assiette.
  double get assietteTva => htArticles + (tvaTransport > 0 ? livraison : 0);

  /// Le TOTAL TTC : le document hors taxes, plus les TVA (articles et transport).
  double get ttc => htDocument + tvaDocument;

  /// Transport TAXÉ : ligne du tableau, comptée dans le TOTAL HT et le TTC.
  /// NON taxé : présentation d'avant — sous les totaux, hors du TTC (décision
  /// du client, 09/09/2026, la même que sur le site). Le total à payer, lui,
  /// compte toujours le transport.
  bool get transportEnLigne => tvaTransport > 0;
  double get htAffiche => transportEnLigne ? htDocument : htArticles;
  double get ttcAffiche => transportEnLigne ? ttc : htArticles + tva;
  double get livraisonHorsTableau => transportEnLigne ? 0 : livraison;

  /// Ce que le client doit réellement régler.
  double get aPayer => ttc - remise;
}
