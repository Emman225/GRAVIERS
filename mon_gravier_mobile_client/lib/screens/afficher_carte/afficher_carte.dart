import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import 'package:http/http.dart' as http;
import 'package:location_picker_flutter_map/location_picker_flutter_map.dart';

class AfficherCarteScreen extends StatefulWidget {
  static String routeName = "/afficherCarte";
  const AfficherCarteScreen({super.key});

  @override
  State<AfficherCarteScreen> createState() => AfficherCarteScreenState();
}

class AfficherCarteScreenState extends State<AfficherCarteScreen> {
  final MapController _mapController = MapController();
  LatLng _currentCenter = const LatLng(5.3453, -4.0244); // Abidjan
  String _currentAddress = '';
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _getCurrentLocation();
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
    setState(() => _isLoading = true);
    try {
      final url =
          'https://nominatim.openstreetmap.org/reverse?format=json&lat=${pos.latitude}&lon=${pos.longitude}&zoom=18&addressdetails=1&accept-language=fr';
      if (kDebugMode) print('Nominatim URL: $url');
      final response = await http.get(
        Uri.parse(url),
        headers: {'User-Agent': 'MonGravierApp/1.0 (contact@mongravier.com)'},
      ).timeout(const Duration(seconds: 10));
      if (kDebugMode) print('Nominatim status: ${response.statusCode}');
      if (kDebugMode) print('Nominatim body: ${response.body.substring(0, response.body.length > 200 ? 200 : response.body.length)}');
      if (response.statusCode == 200) {
        final data = jsonDecode(utf8.decode(response.bodyBytes));
        setState(() {
          _currentAddress = data['display_name'] ?? 'Adresse inconnue';
        });
      } else {
        setState(() {
          _currentAddress = '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
        });
      }
    } catch (e) {
      if (kDebugMode) print('Erreur reverse geocode: $e');
      setState(() {
        _currentAddress = '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
      });
    }
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Choisir la position'.toUpperCase()),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.all(8.0),
          child: ElevatedButton(
            onPressed: () => Navigator.pop(context),
            style: ElevatedButton.styleFrom(
              shape: const CircleBorder(),
              padding: EdgeInsets.zero,
              elevation: 0,
              backgroundColor: Colors.white,
            ),
            child: const Icon(
              Icons.arrow_back_ios_new,
              color: Colors.black,
              size: 20,
            ),
          ),
        ),
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
              TileLayer(
                urlTemplate:
                    'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png',
                subdomains: const ['a', 'b', 'c', 'd'],
                userAgentPackageName: 'com.mon_gravier',
              ),
            ],
          ),
          // Marqueur central fixe
          const Center(
            child: Padding(
              padding: EdgeInsets.only(bottom: 40),
              child: Icon(
                Icons.location_on,
                color: Colors.red,
                size: 48,
              ),
            ),
          ),
          // Adresse en bas
          if (_isLoading || _currentAddress.isNotEmpty)
            Positioned(
              bottom: 16,
              left: 16,
              right: 16,
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(8),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.1),
                      blurRadius: 8,
                    ),
                  ],
                ),
                child: _isLoading
                    ? const Center(
                        child: SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      )
                    : Text(
                        _currentAddress,
                        style: const TextStyle(fontSize: 13),
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
              ),
            ),
          // Bouton localisation
          Positioned(
            right: 16,
            bottom: _isLoading || _currentAddress.isNotEmpty ? 100 : 16,
            child: FloatingActionButton(
              mini: true,
              heroTag: 'location',
              backgroundColor: Colors.white,
              onPressed: _getCurrentLocation,
              child: const Icon(Icons.my_location, color: Colors.blue),
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(12.0),
          child: ElevatedButton(
            onPressed: () async {
              // Récupérer les coordonnées du centre actuel de la carte
              final center = _mapController.camera.center;
              _currentCenter = center;

              // Tenter le reverse geocode, mais ne pas bloquer si ça échoue
              await _reverseGeocode(_currentCenter);

              final pickedData = PickedData(
                LatLong(_currentCenter.latitude, _currentCenter.longitude),
                _currentAddress,
                {},
              );
              if (kDebugMode) {
                print('Position choisie: ${pickedData.latLong.latitude}, ${pickedData.latLong.longitude}');
                print('Adresse: ${pickedData.address}');
              }
              Get.back(result: pickedData);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.blue,
              minimumSize: const Size(double.infinity, 50),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            child: const Text(
              'Choisir comme adresse',
              style: TextStyle(fontSize: 16, color: Colors.white),
            ),
          ),
        ),
      ),
    );
  }
}
