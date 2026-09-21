import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/helper/constants.dart';
import '../components/bouton_retour.dart';

/// AFFICHAGE PLEIN ÉCRAN D'UN DOCUMENT IMAGE (bon de commande joint à une commande).
///
/// Lot 105 bis (17/09/2026) : l'écran restait VIDE quand l'image ne venait pas
/// (fichier absent du stockage du site, serveur injoignable) — `Image.network`
/// nu, sans état pendant le chargement ni après un échec. Désormais : une
/// attente visible, un message clair avec « Réessayer », l'image sans
/// déformation (contain) et zoomable, un message dédié sans document joint.
class AfficherImageWidget extends StatefulWidget {
  static String routeName = "/afficherImage";

  const AfficherImageWidget({super.key});

  @override
  State<AfficherImageWidget> createState() => _AfficherImageWidgetState();
}

class _AfficherImageWidgetState extends State<AfficherImageWidget> {
  String urlImage = "";
  String titre = "Affichage de l'image";
  String sousTitre = "";
  int essai = 0;

  @override
  void initState() {
    // Lot 105 ter : les arguments disent QUELLE image on regarde — soit une
    // simple adresse (anciens appels), soit {url, titre, sousTitre}.
    final args = Get.arguments;
    if (args is Map) {
      urlImage = (args['url'] ?? '').toString().trim();
      final t = (args['titre'] ?? '').toString().trim();
      if (t.isNotEmpty) titre = t;
      sousTitre = (args['sousTitre'] ?? '').toString().trim();
    } else {
      urlImage = (args ?? '').toString().trim();
    }
    super.initState();
  }

  bool get _urlUtilisable =>
      urlImage.isNotEmpty && urlImage != 'null' && Uri.tryParse(urlImage)?.hasScheme == true;

  Future<void> _reessayer() async {
    // Flutter garde en cache l'échec d'une image : on l'oublie avant de recharger.
    await NetworkImage(urlImage).evict();
    if (!mounted) return;
    setState(() => essai++);
  }

  Widget _message(String texte, {bool avecReessai = true}) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.image_not_supported_outlined, size: 48, color: Colors.grey),
            const SizedBox(height: 12),
            Text(texte, textAlign: TextAlign.center),
            if (avecReessai) ...[
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: _reessayer,
                icon: const Icon(Icons.refresh),
                label: const Text('Réessayer'),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _attente(ImageChunkEvent avancement) {
    final total = avancement.expectedTotalBytes;
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircularProgressIndicator(
            value: (total != null && total > 0) ? avancement.cumulativeBytesLoaded / total : null,
          ),
          const SizedBox(height: 12),
          const Text('Chargement du document…'),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          mainAxisAlignment: MainAxisAlignment.start,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(titre, overflow: TextOverflow.ellipsis),
            if (sousTitre.isNotEmpty)
              Text(sousTitre, style: Theme.of(context).textTheme.bodySmall, overflow: TextOverflow.ellipsis),
          ],
        ),
        leading: const BoutonRetour(),
      ),
      body: !_urlUtilisable
          ? _message("Aucun bon de commande n'est joint à cette commande.", avecReessai: false)
          : InteractiveViewer(
              minScale: 1,
              maxScale: 4,
              child: Center(
                child: Image.network(
                  urlImage,
                  key: ValueKey<int>(essai),
                  width: widthOfScreen(context),
                  height: heightOfScreen(context),
                  fit: BoxFit.contain,
                  loadingBuilder: (context, enfant, avancement) {
                    if (avancement == null) return enfant;
                    return _attente(avancement);
                  },
                  errorBuilder: (context, erreur, trace) => _message(
                      "Le bon de commande n'a pas pu être affiché : le fichier n'est pas disponible sur le serveur ou la connexion a échoué."),
                ),
              ),
            ),
    );
  }
}
