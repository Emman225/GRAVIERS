import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:flutter_exit_app/flutter_exit_app.dart';
import 'package:get/get.dart';
import 'package:image_cropper/image_cropper.dart';
import 'package:in_app_review/in_app_review.dart';
import 'package:location_picker_flutter_map/location_picker_flutter_map.dart';
import 'package:mon_gravier_com/constants.dart';
import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/User.dart';
import 'package:mon_gravier_com/models/demande_livraison.dart';
import 'package:money_formatter/money_formatter.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:geolocator/geolocator.dart';
import 'dart:math';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import 'models/Cart.dart';
import 'models/code_promo.dart';

Position? position;

http.Response retourHttp = http.Response('', 0);

const env = 'prod'; //local ou prod ou optimise

String chaineTransition = "", appSignature = "";
String firebaseDeviceToken = "";
String urlCarteVisa = "";
String devise = "";
String ENTREPRISE = "ENTREPRISE";
int entierTransition = 0;
int tva = 0;
double coutReduction = 0;
double montantPoint = 0;
double nombrePoint = 0;
double montantTva = 0;
bool utiliserPoint = false;
bool afficheRetour = false;
bool meFaireLivre = true;
int idNotification = 1;
User user = User();
Reduction reduction = Reduction();
DateTime? currentBackPressTime;
List<Cart> paniers = [];
DemandeLivraison demandeLivraison = DemandeLivraison();

const BANNIERE_TOP = "TOP";
const BANNIERE_FLASH = "FLASH";
const BANNIERE_BOTTOM = "BOTTOM";

const COMMANDE_EN_ATTENTE = "EN ATTENTE";
const COMMANDE_EN_TRAITEMENT = "EN TRAITEMENT";
const COMMANDE_TERMINE = "TERMINEE";

const LOCATION_EN_ATTENTE = "EN ATTENTE";
const LOCATION_EN_COURS = "EN COURS";
const LOCATION_TERMINE = "TERMINE";

const LIVRAISON_EN_ATTENTE = "EN ATTENTE";
const LIVRAISON_EN_TRAITEMENT = "EN TRAITEMENT";
const LIVRAISON_LIVREE = "LIVREE";

const LOCATION = "LOCATION";
const VENTE = "VENTE";

const COMMANDE = "COMMANDE";
const LIVRAISON = "LIVRAISON";
///action == 1 -> écriture
///action == 2 -> lecture
Future<String> lireOuEcrireDonnee(String key, String donnee, int action) async {
  final prefs = await SharedPreferences.getInstance();
  if (action == 1) {
    prefs.remove(key);
    prefs.setString(key, donnee);
  } else {
    donnee = prefs.getString(key) ?? '';
    // print(donnee);
  }
  return donnee;
}


Future<File> rognerImage(context, path) async {
  final croppedFile = await ImageCropper().cropImage(
    sourcePath: path,
    compressFormat: ImageCompressFormat.jpg,
    compressQuality: 100,
    uiSettings: [
      AndroidUiSettings(
          toolbarTitle: 'Rognez l\'image',
          toolbarColor: kPrimaryColor,
          toolbarWidgetColor: Colors.white,
          initAspectRatio: CropAspectRatioPreset.original,
          lockAspectRatio: false),
      IOSUiSettings(
        title: 'Rognez l\'image',
      ),
      // image_cropper v8 : enableZoom n'existe plus dans WebUiSettings
      // (sans incidence : l'app est distribuée en APK Android).
      WebUiSettings(
        context: context,
      ),
    ],
  );
  if (croppedFile == null) {
    return File('');
  }
  return File(croppedFile.path);
}


Future<bool> verifierConnexion({String adresse = 'google.com'}) async {
  bool retour = false;

  try {
    final result = await InternetAddress.lookup(adresse);
    if (result.isNotEmpty && result[0].rawAddress.isNotEmpty) {
      retour = true;
      // if (adresse != 'google.com') {
      //   defUrl = 'https://$adresse/tresormoney_V4/'; //Production
      // }
    }
  } on SocketException catch (_) {
    //print('not connected');
  }

  return retour;
}

String lienAPI() {
  String url = '';
  if (env == 'local') {
    url =
        'http://10.10.10.184:8002/mon_gravier/'; //Local (dev PC sur LAN)
  } else {
    url =
        'https://apigravier.fneconnect.net/mon_gravier/'; //Production
  }
  if (kDebugMode) {
    print(url);
  }
  return url;
}

traitementBase64(bool encode, String chaine) {
  if (encode == true) {
    List<int> bytes = utf8.encode(chaine);
    return base64.encode(bytes);
  } else {
    Uint8List decoded = base64.decode(chaine);
    return utf8.decode(decoded);
  }
}

const _chars = 'AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz1234567890';
const _charsN = '1234567890';
Random _rnd = Random();

String getRandomString(int length) => String.fromCharCodes(Iterable.generate(
    length, (_) => _chars.codeUnitAt(_rnd.nextInt(_chars.length))));

String getRandomNumber(int length) => String.fromCharCodes(Iterable.generate(
    length, (_) => _charsN.codeUnitAt(_rnd.nextInt(_charsN.length))));

String melangeChaine(String ch, {bool avecChaine = true}) {
  if (avecChaine) {
    return "***${getRandomString(29)}$ch${getRandomString(30)}9654===";
  } else {
    return "***${getRandomNumber(29)}$ch${getRandomNumber(30)}9654===";
  }
}

String valeurQrCode = '', msgErr = '', token = "", kt = "";

afficherChargement() {
  EasyLoading.show(
    dismissOnTap: false,
    status: "Patientez...",
  );
}

fermerChargement() {
  EasyLoading.dismiss();
}

String formaterMontant(double montant) {
  MoneyFormatterOutput mnt = MoneyFormatter(
          amount: montant,
          settings: MoneyFormatterSettings(
              symbol: 'F',
              thousandSeparator: ' ',
              decimalSeparator: ',',
              symbolAndNumberSeparator: ' ',
              fractionDigits: 0,
              compactFormatType: CompactFormatType.short))
      .output;
  return mnt.symbolOnRight;
}

double getTotalAmount(){
  double total = 0;
  try{
    if (paniers.isEmpty) {
      return 0.0;
    }
    if (paniers.first.product.type_affaire == VENTE) {
      total = paniers.fold(0.0, (sum, p) => sum + (p.product.prixEffectif.toDouble() * p.numOfItem));
    }else if(paniers.first.product.type_affaire == LOCATION){
      total = paniers.fold(0.0, (sum, p) => sum + (p.product.prixEffectif.toDouble() * p.numOfItem * p.nbreJours!.toDouble()));
    }
    coutReduction = 0;
    if (reduction.id != null && reduction.id! > 0) {
      coutReduction = total * reduction.tauxReduction! / 100;
    }
    if (utiliserPoint == true) {
      total -= montantPoint * nombrePoint;
    }
    if (tva > 0) {
      montantTva = total * (tva / 100);
      total += montantTva;
    }
  }catch(e){
    if (kDebugMode) {
      print(e.toString());
    }
    coutReduction=0;
    montantTva=0;
  }
  return total - coutReduction;
}

Future<bool> fermerApplication(BuildContext context) async {
  return (await showDialog(
          barrierDismissible: false,
          context: context,
          builder: (BuildContext context) {
            return AlertDialog(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(30),
              ),
              title: const Text(
                "Attention",
              ),
              elevation: 5.0,
              content: const Text("Voulez-vous quitter l'application ?"),
              actions: [
                TextButton(
                    onPressed: () {
                      Navigator.of(context).pop(false);
                    },
                    child: const Text(
                      "NON",
                      style: TextStyle(fontSize: 14, color: Colors.black),
                    )),
                TextButton(
                    onPressed: () {
                      (Platform.isAndroid)
                          ? FlutterExitApp.exitApp()
                          : FlutterExitApp.exitApp(iosForceExit: true);
                    },
                    child: const Text(
                      "OUI",
                      style: TextStyle(fontSize: 14, color: greenColor),
                    ))
              ],
            );
          })) ??
      false;
}

Future<void> lancerUrl(_url) async {
  if (!await launchUrl(Uri.parse(_url))) {
    throw Exception('Could not launch $_url');
  }
}

Future<bool> confirmationAction(BuildContext context, titre, message) async {
  return (await showDialog(
          barrierDismissible: false,
          context: context,
          builder: (BuildContext context) {
            return AlertDialog(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(30),
              ),
              title: Text(titre),
              elevation: 5.0,
              content: Text(message),
              actions: [
                TextButton(
                    onPressed: () {
                      Get.back(result: false);
                    },
                    child: const Text(
                      "NON",
                      style: TextStyle(fontSize: 14, color: redColor),
                    )),
                TextButton(
                    onPressed: () {
                      Get.back(result: true);
                    },
                    child: const Text(
                      "OUI",
                      style: TextStyle(fontSize: 14, color: greenColor),
                    ))
              ],
            );
          })) ??
      false;
}

noterApplication() async {
  final InAppReview inAppReview = InAppReview.instance;
  if (await inAppReview.isAvailable()) {
    inAppReview.requestReview();
  }
  lireOuEcrireDonnee("note_client", "OUI", 1);
}

/// When the location services are not enabled or permissions
/// are denied the `Future` will return an error.
determinePosition() async {
  bool serviceEnabled;
  LocationPermission permission;

  // Test if location services are enabled.
  serviceEnabled = await Geolocator.isLocationServiceEnabled();
  if (!serviceEnabled) {
    // Location services are not enabled don't continue
    // accessing the position and request users of the
    // App to enable the location services.
    return Future.error('Location services are disabled.');
  }

  permission = await Geolocator.checkPermission();
  if (permission == LocationPermission.denied) {
    permission = await Geolocator.requestPermission();
    if (permission == LocationPermission.denied) {
      // Permissions are denied, next time you could try
      // requesting permissions again (this is also where
      // Android's shouldShowRequestPermissionRationale
      // returned true. According to Android guidelines
      // your App should show an explanatory UI now.
      return Future.error('Location permissions are denied');
    }
  }

  if (permission == LocationPermission.deniedForever) {
    // Permissions are denied forever, handle appropriately.
    return Future.error(
        'Location permissions are permanently denied, we cannot request permissions.');
  }

  // When we reach here, permissions are granted and we can
  // continue accessing the position of the device.
  position = await Geolocator.getCurrentPosition();
}

bool verifierComplexiteMotDePasse(String mdp) {
  bool bPass = true;
  List<String> tabPassWd = [
    "12345",
    "1234",
    "0000",
    "1111",
    "2222",
    "3333",
    "4444",
    "5555",
    "6666",
    "7777",
    "8888",
    "9999",
    "0101",
    "0202",
    "0303",
    "0404",
    "0505",
    "0606",
    "0707",
    "0808",
    "0909",
    "1010",
    "2020",
    "3030",
    "4040",
    "5050",
    "6060",
    "7070",
    "8080",
    "909c0",
    "11111",
    "00000",
    "22222",
    "33333",
    "44444",
    "55555",
    "66666",
    "77777",
    "88888",
    "99999"
  ];
  if (tabPassWd.contains(mdp) || mdp.length < 4) bPass = false;
  return bPass;
}

onWillPop() {
  DateTime now = DateTime.now();
  if (currentBackPressTime == null ||
      now.difference(currentBackPressTime!) > const Duration(seconds: 2)) {
    currentBackPressTime = now;
    EasyLoading.showInfo("Cliquez à nouveau pour fermer l'application.");
    return false;
  } else {
    return true;
  }
}

formaterDate(String dateString, {String format = 'd MMMM y à HH\'h\'mm'}){
  // Convertir la chaîne en objet DateTime
  DateTime dateTime = DateTime.parse(dateString);
  // Formater la date
  return DateFormat(format, 'fr_FR').format(dateTime);
}

nombreDeJoursEntre2Dates(String date1, String date2){
  // Convertir les chaînes en objets DateTime
  DateTime parsedDate1 = DateFormat("yyyy-MM-dd").parse(date1);
  DateTime parsedDate2 = DateFormat("yyyy-MM-dd").parse(date2);
  // Calculer la différence entre les deux dates
  return parsedDate2.difference(parsedDate1).inDays + 1;
}

double calculerDistanceEnKM(LatLong debut, LatLong fin){
  // Calculer la distance entre les deux positions
  double distanceInMeters = Geolocator.distanceBetween(
    debut.latitude,
    debut.longitude,
    fin.latitude,
    fin.longitude,
  );

  // Convertir la distance en kilomètres
  return distanceInMeters / 1000;
}