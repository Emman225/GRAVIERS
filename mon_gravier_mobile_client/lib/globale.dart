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
/// Version de l'application, affichée sur l'écran profil.
///
/// À incrémenter à CHAQUE build livré, en même temps que `version:` dans
/// pubspec.yaml. Sans repère visible, plusieurs APK successifs sont
/// indiscernables une fois installés : on ne sait plus lequel s'exécute, et
/// tout diagnostic devient une conjecture.
const String versionApplication = '1.0.19 (20)';

/// Traduit une exception technique en une phrase qui dit ce qui s'est passé.
///
/// Les écrans affichaient tous « Une erreur s'est produite veuillez reesayer
/// plus tard », quelle qu'en soit l'origine : délai dépassé, serveur
/// injoignable ou réponse illisible se ressemblaient donc à l'écran. Ni
/// l'utilisateur ni nous ne pouvions savoir laquelle — il a fallu interroger la
/// base et le serveur pour éliminer les hypothèses une par une.
String messageErreurTechnique(Object e) {
  if (e is TimeoutException) {
    return "Le serveur met trop de temps à répondre. Vérifiez votre connexion, puis réessayez.";
  }
  if (e is SocketException) {
    return "Impossible de joindre le serveur. Vérifiez votre accès à internet.";
  }
  if (e is FormatException) {
    return "Le serveur a renvoyé une réponse inattendue. Réessayez dans un instant ; si cela se répète, signalez-le.";
  }
  if (e is http.ClientException) {
    // Connexion interrompue en cours de transfert : fréquent sur un réseau
    // mobile instable, et ce n'est PAS une SocketException.
    return "La connexion a été interrompue pendant l'échange. Réessayez.";
  }
  if (e is HandshakeException) {
    return "La liaison sécurisée avec le serveur a échoué. Réessayez ; si cela persiste, signalez-le.";
  }

  // Repli, pour les causes qui ne sont pas reconnues ci-dessus.
  //
  // Pendant la recette, ce message portait en plus la version de
  // l'application, le type et le texte de l'exception. C'est ce qui a permis
  // de nommer la panne de l'écran d'accueil au lieu de la déduire. Ce détail
  // est retiré maintenant que la cause est corrigée : il n'a aucun sens pour
  // un client. Le rétablir est l'affaire d'une ligne si un cas obscur
  // réapparaît — le détail complet reste consultable en développement.
  if (kDebugMode) {
    print('Erreur technique non reconnue : $e');
  }
  return "Une erreur s'est produite, veuillez réessayer plus tard.";
}

List<Cart> paniers = [];

/// Devis dont le panier courant est issu, s'il vient d'un devis repris.
///
/// Sans cette information, la commande partait avec devis_id = null : rien ne
/// reliait les deux, et le devis restait indéfiniment « en attente » dans
/// « Mes devis enregistrés » alors qu'il avait été transformé. Le site, lui,
/// bascule le devis en statut 2 à la conversion, et c'est ce que l'API fait
/// désormais aussi quand elle reçoit cet identifiant.
///
/// Remise à null dès que le panier est vidé : un panier reconstitué depuis le
/// catalogue ne doit surtout pas clore un devis auquel il ne correspond plus.
int? devisRepris;
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

/// Vrai tant qu'un indicateur « Patientez... » est réellement affiché.
/// Sert à empêcher fermerChargement() d'effacer un message qui vient de le remplacer.
bool _chargementEnCours = false;

afficherChargement() {
  _chargementEnCours = true;
  EasyLoading.show(
    dismissOnTap: false,
    status: "Patientez...",
  );
}

fermerChargement() {
  // Ne ferme QUE l'indicateur de chargement. Sans ce garde-fou, un appel à
  // fermerChargement() placé après un message d'erreur effaçait ce message dans
  // la milliseconde : l'utilisateur ne voyait donc AUCUNE réaction (constaté sur
  // « Mot de passe oublié », motif présent sur 24 écrans).
  if (!_chargementEnCours) return;
  _chargementEnCours = false;
  EasyLoading.dismiss();
}

/// Message d'ERREUR. Remplace l'indicateur de chargement et reste affiché même si
/// fermerChargement() est appelé juste après.
void afficherErreur(dynamic message) {
  _chargementEnCours = false;
  final texte = (message == null || message.toString().trim().isEmpty)
      ? "Une erreur s'est produite, veuillez réessayer"
      : message.toString();
  EasyLoading.showError(texte, duration: const Duration(seconds: 3));
}

/// Message d'INFORMATION (même protection).
void afficherInfo(dynamic message) {
  _chargementEnCours = false;
  final texte = (message == null || message.toString().trim().isEmpty)
      ? "Information indisponible"
      : message.toString();
  EasyLoading.showInfo(texte, duration: const Duration(seconds: 3));
}

/// Message de SUCCÈS (même protection).
void afficherSucces(dynamic message) {
  _chargementEnCours = false;
  final texte = (message == null || message.toString().trim().isEmpty)
      ? "Opération effectuée"
      : message.toString();
  EasyLoading.showSuccess(texte, duration: const Duration(seconds: 3));
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
      // « nbreJours ?? 1 » et non « nbreJours! ». L'assertion levait un
      // _TypeError dès qu'une ligne de location arrivait sans durée — et comme
      // getTotalAmount() est appelé au chargement de l'accueil, l'écran
      // affichait « Une erreur s'est produite » sans que rien ne le laisse
      // deviner. Une location porte au minimum une journée : on retombe
      // dessus plutôt que de faire échouer tout le calcul.
      total = paniers.fold(0.0, (sum, p) => sum + (p.product.prixEffectif.toDouble() * p.numOfItem * (p.nbreJours ?? 1).toDouble()));
    }
    // Remises : code promo (% du HT) et points de fidélité, cumulés.
    coutReduction = 0;
    if (reduction.id != null && reduction.id! > 0) {
      // Un code promo enregistré sans taux faisait échouer tout le calcul.
      // Sans taux, pas de remise — jamais une remise inventée.
      coutReduction = total * (reduction.tauxReduction ?? 0) / 100;
    }
    if (utiliserPoint == true) {
      coutReduction += montantPoint * nombrePoint;
    }

    // La remise porte sur la marchandise : elle est plafonnée au HT. Sans ce
    // plafond, des points supérieurs au panier produisaient un total NÉGATIF,
    // envoyé tel quel au serveur.
    if (coutReduction > total) {
      coutReduction = total;
    }

    // TVA sur la base NETTE, c'est-à-dire le HT DIMINUÉ DE LA REMISE.
    //
    // Elle était prise sur le HT brut : le client se voyait réclamer la taxe
    // sur une remise qu'il ne payait pas. La facture, elle, applique la base
    // nette — comme le site. Les deux montants divergeaient donc exactement du
    // taux appliqué à la remise.
    //
    // Constaté sur la commande 849677 : HT 350, remise 35, livraison 65. Le
    // mobile réclamait 443, la facture en retenait 437. Le client avait tout
    // réglé, et son règlement restait pourtant impossible à imputer, la somme
    // versée dépassant la somme due. L'arrondi est celui du serveur, pour que
    // les deux montants tombent au franc près.
    total -= coutReduction;
    if (tva > 0) {
      montantTva = (total * (tva / 100)).roundToDouble();
      total += montantTva;
    } else {
      montantTva = 0;
    }
    return total;
  }catch(e){
    if (kDebugMode) {
      print(e.toString());
    }
    coutReduction=0;
    montantTva=0;
  }
  // Atteint uniquement si le calcul a échoué ci-dessus : la remise y est
  // remise à zéro, et `total` vaut ce qui avait pu être calculé. Le chemin
  // normal retourne à l'intérieur du try, remise déjà déduite.
  return total;
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

/// Nombre de jours entre deux dates (bornes incluses). Renvoie null si l'une des
/// dates est vide ou illisible : l'ancienne version levait une FormatException
/// non gérée (bouton « Ajouter au panier » mort).
int? nombreDeJoursEntre2Dates(String date1, String date2){
  try {
    DateTime parsedDate1 = DateFormat("yyyy-MM-dd").parse(date1);
    DateTime parsedDate2 = DateFormat("yyyy-MM-dd").parse(date2);
    return parsedDate2.difference(parsedDate1).inDays + 1;
  } catch (e) {
    return null;
  }
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