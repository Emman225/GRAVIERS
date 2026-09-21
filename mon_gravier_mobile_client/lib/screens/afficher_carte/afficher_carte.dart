import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:latlong2/latlong.dart';
import 'package:location_picker_flutter_map/location_picker_flutter_map.dart';

import '../../components/bouton_retour.dart';
import '../../constants.dart';
import 'recherche_de_lieu.dart';

/// CHOIX D'UN LIEU SUR LA CARTE.
///
/// L'écran ne proposait QUE le glissement : on déplaçait le fond de carte sous
/// un repère fixe, et le bouton reprenait le centre. Aucun champ de saisie.
/// Pour livrer à Yopougon depuis Cocody, il fallait faire glisser la carte sur
/// toute la distance — alors que le SITE, lui, offrait une recherche depuis le
/// début (`leaflet-control-geocoder`).
///
/// La recherche est ajoutée ici avec EXACTEMENT les mêmes règles que le web
/// (voir `graviers/public/frontend/assets/leaflet/recherche-lieu.js`) :
///
///   · résultats limités à la CÔTE D'IVOIRE — sans ce filtre, « Riviera »
///     renvoie le Texas, la Suisse et la Provence, et « Gare routière » renvoie
///     Auxerre et Ghardaïa ;
///   · réponses en français, avec le détail de l'adresse ;
///   · interrogation à partir de trois caractères, au plus une toutes les
///     400 ms — ce qui rend la saisie fluide et respecte la politique d'usage
///     de Nominatim, qui bride le débit et bloque les applications trop
///     bavardes.
///
/// Le reste de l'écran est INCHANGÉ : bouton de position actuelle, géocodage
/// inverse à la validation, et même valeur de retour (`PickedData`) — les
/// écrans appelants n'ont rien à savoir de ce changement.
class AfficherCarteScreen extends StatefulWidget {
  static String routeName = "/afficherCarte";

  const AfficherCarteScreen({super.key});

  @override
  State<AfficherCarteScreen> createState() => AfficherCarteScreenState();
}

/// Un lieu proposé par la recherche.
class _Suggestion {
  const _Suggestion(this.libelle, this.position);

  /// L'adresse complète telle que renvoyée par le service. C'est ELLE qui est
  /// retenue à la validation : le site enregistre exactement la même chaîne,
  /// et les deux canaux doivent stocker la même adresse pour une même livraison.
  final String libelle;

  final LatLng position;

  /// Découpage pour l'affichage seul — le lieu d'abord, sa localisation
  /// ensuite. Ce qui est ENREGISTRÉ reste `libelle`, inchangé.
  LibelleLieu get affichage => decouperLibelle(libelle);
}

class AfficherCarteScreenState extends State<AfficherCarteScreen> {
  final MapController _mapController = MapController();
  final TextEditingController _recherche = TextEditingController();
  final FocusNode _focusRecherche = FocusNode();

  LatLng _currentCenter = const LatLng(5.3453, -4.0244); // Abidjan
  String _currentAddress = '';
  bool _isLoading = false;

  /// Position du lieu retenu dans la liste des suggestions, et adresse qui
  /// l'accompagne. Tant que le client ne déplace pas la carte, c'est CETTE
  /// adresse qui vaut — voir le bouton de validation.
  LatLng? _positionChoisie;

  List<_Suggestion> _suggestions = [];
  bool _rechercheEnCours = false;
  String? _messageRecherche;
  Timer? _minuteurSaisie;

  @override
  void initState() {
    super.initState();
    _getCurrentLocation();
  }

  @override
  void dispose() {
    _minuteurSaisie?.cancel();
    _recherche.dispose();
    _focusRecherche.dispose();
    super.dispose();
  }

  Future<void> _getCurrentLocation() async {
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.whileInUse ||
          permission == LocationPermission.always) {
        Position position = await Geolocator.getCurrentPosition();
        if (!mounted) return;
        setState(() {
          _currentCenter = LatLng(position.latitude, position.longitude);
        });
        _mapController.move(_currentCenter, 14);
        _reverseGeocode(_currentCenter);
      }
    } catch (e) {
      if (kDebugMode) print('Erreur géolocalisation: $e');
    }
  }

  Future<void> _reverseGeocode(LatLng pos) async {
    if (mounted) setState(() => _isLoading = true);
    try {
      final url =
          'https://nominatim.openstreetmap.org/reverse?format=json&lat=${pos.latitude}&lon=${pos.longitude}&zoom=18&addressdetails=1&accept-language=fr';
      final response = await http.get(
        Uri.parse(url),
        headers: {'User-Agent': identifiantAppelNominatim},
      ).timeout(const Duration(seconds: 10));
      if (!mounted) return;
      if (response.statusCode == 200) {
        final data = jsonDecode(utf8.decode(response.bodyBytes));
        setState(() {
          _currentAddress = data['display_name'] ?? 'Adresse inconnue';
        });
      } else {
        setState(() {
          _currentAddress =
              '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
        });
      }
    } catch (e) {
      if (kDebugMode) print('Erreur reverse geocode: $e');
      if (!mounted) return;
      setState(() {
        _currentAddress =
            '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
      });
    }
    if (mounted) setState(() => _isLoading = false);
  }

  /// Saisie en cours : on attend 400 ms de silence avant d'interroger le
  /// service. Sans cette attente, chaque touche frappée partirait en requête —
  /// Nominatim bloquerait, et le client verrait la recherche cesser de
  /// fonctionner sans explication.
  void _saisieChangee(String texte) {
    _minuteurSaisie?.cancel();

    final terme = texte.trim();
    if (terme.length < minimumCaracteresRecherche) {
      setState(() {
        _suggestions = [];
        _messageRecherche = null;
        _rechercheEnCours = false;
      });
      return;
    }

    _minuteurSaisie = Timer(delaiAvantAppelRecherche, () => _chercherLieu(terme));
  }

  Future<void> _chercherLieu(String terme) async {
    if (!mounted) return;
    setState(() {
      _rechercheEnCours = true;
      _messageRecherche = null;
    });

    try {
      final reponse = await http.get(
        uriRechercheLieu(terme),
        headers: {'User-Agent': identifiantAppelNominatim},
      ).timeout(const Duration(seconds: 10));

      if (!mounted) return;

      if (reponse.statusCode != 200) {
        setState(() {
          _suggestions = [];
          _rechercheEnCours = false;
          _messageRecherche =
              "La recherche est momentanément indisponible. Vous pouvez "
              "déplacer la carte pour choisir le lieu.";
        });
        return;
      }

      final donnees = jsonDecode(utf8.decode(reponse.bodyBytes));
      if (donnees is! List) {
        setState(() {
          _suggestions = [];
          _rechercheEnCours = false;
          _messageRecherche = "Réponse inattendue du service de recherche.";
        });
        return;
      }

      final trouves = <_Suggestion>[];
      for (final element in donnees) {
        final lat = double.tryParse('${element['lat']}');
        final lon = double.tryParse('${element['lon']}');
        final nom = '${element['display_name'] ?? ''}'.trim();
        if (lat == null || lon == null || nom.isEmpty) continue;
        trouves.add(_Suggestion(nom, LatLng(lat, lon)));
      }

      setState(() {
        _suggestions = trouves;
        _rechercheEnCours = false;
        _messageRecherche = trouves.isEmpty
            ? "Aucun lieu trouvé. Essayez un quartier ou un repère proche."
            : null;
      });
    } catch (e) {
      if (kDebugMode) print('Erreur recherche de lieu: $e');
      if (!mounted) return;
      setState(() {
        _suggestions = [];
        _rechercheEnCours = false;
        _messageRecherche =
            "Impossible d'interroger la recherche. Vérifiez votre connexion, "
            "ou déplacez la carte pour choisir le lieu.";
      });
    }
  }

  void _choisirSuggestion(_Suggestion suggestion) {
    _focusRecherche.unfocus();
    _minuteurSaisie?.cancel();

    setState(() {
      _currentCenter = suggestion.position;
      _currentAddress = suggestion.libelle;
      _positionChoisie = suggestion.position;
      _suggestions = [];
      _messageRecherche = null;
      _recherche.text = suggestion.libelle;
    });

    _mapController.move(suggestion.position, 16);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Choisir le lieu'),
        elevation: 0,
        leading: BoutonRetour(onTap: () => Navigator.pop(context)),
      ),
      body: Stack(
        children: [
          FlutterMap(
            mapController: _mapController,
            options: MapOptions(
              initialCenter: _currentCenter,
              initialZoom: 14,
              minZoom: 5,
              maxZoom: 18,
              onPositionChanged: (position, hasGesture) {
                if (hasGesture) {
                  _currentCenter = position.center ?? _currentCenter;
                }
              },
            ),
            children: [
              // FOND DE CARTE : OpenStreetMap, et non plus CARTO.
              //
              // CARTO exige désormais une clé pour ses fonds de carte : sans
              // elle, chaque tuile revenait barrée de « API KEY REQUIRED », et
              // le client voyait un écran cassé au moment précis où il indique
              // où livrer. Les tuiles d'OpenStreetMap sont libres et sans clé —
              // c'est déjà ce projet qui fournit la recherche d'adresse de cet
              // écran (Nominatim), les deux vont donc ensemble.
              //
              // OSM demande en contrepartie un agent utilisateur identifiable :
              // c'est la condition de sa politique d'usage.
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: 'com.mon_gravier',
              ),
            ],
          ),

          // Repère central fixe : c'est lui que le bouton du bas retient.
          const Center(
            child: Padding(
              padding: EdgeInsets.only(bottom: 40),
              child: Icon(Icons.location_on, color: kErrorColor, size: 46),
            ),
          ),

          // ------------------------------------------------- RECHERCHE
          Positioned(
            top: kSpaceMd,
            left: kSpaceLg,
            right: kSpaceLg,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Material(
                  color: kSurfaceColor,
                  borderRadius: BorderRadius.circular(kRadiusMd),
                  elevation: 3,
                  child: TextField(
                    controller: _recherche,
                    focusNode: _focusRecherche,
                    onChanged: _saisieChangee,
                    textInputAction: TextInputAction.search,
                    onSubmitted: (v) {
                      final terme = v.trim();
                      if (terme.length >= minimumCaracteresRecherche) {
                        _minuteurSaisie?.cancel();
                        _chercherLieu(terme);
                      }
                    },
                    style: const TextStyle(fontSize: 14, color: kTextColor),
                    decoration: InputDecoration(
                      hintText: 'Rechercher un lieu (quartier, rue, repère)…',
                      hintStyle: const TextStyle(
                          fontSize: 14, color: kTextMutedColor),
                      filled: true,
                      fillColor: kSurfaceColor,
                      isDense: true,
                      contentPadding: const EdgeInsets.symmetric(
                          horizontal: kSpaceLg, vertical: kSpaceMd),
                      border: _contourRecherche,
                      enabledBorder: _contourRecherche,
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(kRadiusMd),
                        borderSide:
                            const BorderSide(color: kPrimaryColor, width: 1.4),
                      ),
                      floatingLabelBehavior: FloatingLabelBehavior.never,
                      prefixIcon: const Icon(Icons.search,
                          size: 20, color: kTextMutedColor),
                      suffixIcon: _rechercheEnCours
                          ? const Padding(
                              padding: EdgeInsets.all(12),
                              child: SizedBox(
                                height: 16,
                                width: 16,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2, color: kPrimaryColor),
                              ),
                            )
                          : (_recherche.text.isEmpty
                              ? null
                              : IconButton(
                                  tooltip: 'Effacer',
                                  icon: const Icon(Icons.close, size: 18),
                                  onPressed: () {
                                    _recherche.clear();
                                    _saisieChangee('');
                                  },
                                )),
                    ),
                  ),
                ),

                // Résultats. Bornés en hauteur : sur un petit téléphone, huit
                // adresses complètes couvriraient toute la carte.
                if (_suggestions.isNotEmpty || _messageRecherche != null)
                  Container(
                    margin: const EdgeInsets.only(top: kSpaceSm),
                    constraints: const BoxConstraints(maxHeight: 260),
                    decoration: BoxDecoration(
                      color: kSurfaceColor,
                      borderRadius: BorderRadius.circular(kRadiusMd),
                      border: Border.all(color: kBorderColor),
                      boxShadow: kShadowCarte,
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: _messageRecherche != null
                        ? Padding(
                            padding: const EdgeInsets.all(kSpaceLg),
                            child: Text(_messageRecherche!,
                                style: kCorpsSecondaireStyle),
                          )
                        : ListView.separated(
                            shrinkWrap: true,
                            padding: EdgeInsets.zero,
                            itemCount: _suggestions.length,
                            separatorBuilder: (_, __) => const Divider(
                                height: 1, color: kBorderColor),
                            itemBuilder: (context, index) {
                              final s = _suggestions[index];
                              return ListTile(
                                dense: true,
                                leading: const Icon(Icons.place_outlined,
                                    size: 18, color: kPrimaryColor),
                                // Le LIEU d'abord, sa localisation ensuite.
                                // Affichée d'un bloc, l'adresse complète se
                                // faisait tronquer sur un petit téléphone —
                                // et c'est le début, le nom du lieu, qui
                                // survivait le moins bien à la mise en ligne
                                // avec les entrées voisines.
                                title: Text(
                                  s.affichage.nom,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 14,
                                    height: 1.3,
                                    fontWeight: FontWeight.w600,
                                    color: kTextColor,
                                  ),
                                ),
                                subtitle: s.affichage.contexte.isEmpty
                                    ? null
                                    : Text(
                                        s.affichage.contexte,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                          fontSize: 12,
                                          height: 1.3,
                                          color: kTextSecondaryColor,
                                        ),
                                      ),
                                onTap: () => _choisirSuggestion(s),
                              );
                            },
                          ),
                  ),
              ],
            ),
          ),

          // ------------------------------------- ADRESSE DU POINT CHOISI
          if (_isLoading || _currentAddress.isNotEmpty)
            Positioned(
              bottom: kSpaceLg,
              left: kSpaceLg,
              right: kSpaceLg,
              child: Container(
                padding: const EdgeInsets.all(kSpaceMd),
                decoration: BoxDecoration(
                  color: kSurfaceColor,
                  borderRadius: BorderRadius.circular(kRadiusMd),
                  border: Border.all(color: kBorderColor),
                  boxShadow: kShadowCarte,
                ),
                child: _isLoading
                    ? const Center(
                        child: SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: kPrimaryColor),
                        ),
                      )
                    : Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.place, size: 18, color: kPrimaryColor),
                          const SizedBox(width: kSpaceSm),
                          Expanded(
                            child: Text(
                              _currentAddress,
                              style: kCorpsSecondaireStyle,
                              maxLines: 3,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
              ),
            ),

          // Retour à la position actuelle.
          Positioned(
            right: kSpaceLg,
            bottom: _isLoading || _currentAddress.isNotEmpty ? 110 : kSpaceLg,
            child: FloatingActionButton(
              mini: true,
              heroTag: 'location',
              backgroundColor: kSurfaceColor,
              tooltip: 'Ma position',
              onPressed: _getCurrentLocation,
              child: const Icon(Icons.my_location, color: kPrimaryColor),
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(kSpaceMd),
          child: ElevatedButton(
            onPressed: () async {
              // Coordonnées du centre actuel de la carte.
              final center = _mapController.camera.center;
              _currentCenter = center;

              // LE LIEU CHOISI DANS LA LISTE PRIME.
              //
              // La validation relançait TOUJOURS un géocodage inverse, qui
              // écrasait l'adresse retenue. Deux conséquences, toutes deux
              // mauvaises :
              //
              //  · le client choisissait « Riviéra 3, Cocody, Abidjan » et
              //    l'adresse enregistrée devenait celle de la voie la plus
              //    proche du point — pas ce qu'il avait désigné ;
              //
              //  · si le service ne répondait pas (réseau coupé, débit bridé
              //    par Nominatim), le repli remplaçait cette adresse par les
              //    COORDONNÉES BRUTES. Le livreur recevait alors
              //    « 5.357773, -3.888559 » au lieu d'un nom de quartier.
              //
              // Tant que la carte n'a pas bougé depuis le choix, l'adresse
              // retenue reste la bonne : on n'interroge rien.
              final choisi = _positionChoisie;
              final carteNonDeplacee = choisi != null &&
                  memePoint(center.latitude, center.longitude,
                      choisi.latitude, choisi.longitude);

              if (!carteNonDeplacee) {
                // Géocodage inverse, sans bloquer si le service ne répond
                // pas : le repli affiche les coordonnées, et la validation
                // reste possible.
                await _reverseGeocode(_currentCenter);
              }

              final pickedData = PickedData(
                LatLong(_currentCenter.latitude, _currentCenter.longitude),
                _currentAddress,
                {},
              );
              Get.back(result: pickedData);
            },
            child: const Text('Choisir comme adresse'),
          ),
        ),
      ),
    );
  }
}

final OutlineInputBorder _contourRecherche = OutlineInputBorder(
  borderRadius: BorderRadius.circular(kRadiusMd),
  borderSide: const BorderSide(color: kBorderColor),
);
