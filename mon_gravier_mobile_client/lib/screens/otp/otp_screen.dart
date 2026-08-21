import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;

import '../../constants.dart';

import '../../globale.dart';
import 'components/otp_form.dart';

class OtpScreen extends StatefulWidget {
  static String routeName = "/otp";

  const OtpScreen({super.key});

  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  // Niveau 1 = finalisation d'une INSCRIPTION, niveau 2 = mot de passe oublié.
  // Repli sur 1 si l'écran est ouvert sans argument : l'ancienne écriture
  // « int niveau = Get.arguments » plantait sur une valeur nulle.
  int niveau = (Get.arguments is int) ? Get.arguments as int : 1;

  @override
  void initState() {
    if (Get.arguments is int) {
      niveau = Get.arguments as int;
    }
    super.initState();
  }

  bool get estInscription => niveau == 1;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(estInscription ? "Finaliser l'inscription" : "Vérification du code"),
      ),
      body: SizedBox(
        width: double.infinity,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: SingleChildScrollView(
            child: Column(
              children: [
                Image.asset('assets/images/otp.webp'),
                const SizedBox(height: 16),

                // Le message qui s'affichait auparavant dans une fenêtre après
                // l'inscription est repris ICI : le client le lit au moment où il
                // en a besoin, devant le champ à remplir.
                Text(
                  estInscription ? "Plus qu'une étape" : "Vérification du code",
                  style: headingStyle,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 12),

                if (estInscription)
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      // Teintes fixes plutôt que withOpacity() : cette méthode est
                      // dépréciée sur les SDK récents, et sa remplaçante n'existe
                      // pas sur les plus anciens. Une couleur littérale compile
                      // dans les deux cas. Valeurs = bleu de la charte éclairci.
                      color: const Color(0xFFEDEFF7),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFC7CCE2)),
                    ),
                    child: const Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.mark_email_unread_outlined,
                            color: kPrimaryColor, size: 26),
                        SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                "Votre compte a bien été créé.",
                                style: TextStyle(fontWeight: FontWeight.bold),
                              ),
                              SizedBox(height: 4),
                              Text(
                                "Un code de validation vient de vous être envoyé par e-mail. "
                                "Saisissez-le ci-dessous pour finaliser votre inscription.",
                              ),
                              SizedBox(height: 6),
                              Text(
                                "Pensez à regarder vos courriers indésirables si vous ne le voyez pas.",
                                style: TextStyle(fontSize: 12, color: Colors.black54),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  )
                else ...[
                  const Text("Un code à été envoyé sur votre adresse email"),
                  const Text("Veuillez le saisir pour continuer "),
                ],

                const SizedBox(height: 8),
                OtpForm(niveau: niveau),
                const SizedBox(height: 20),
                GestureDetector(
                  onTap: () => renvoyerCodeOtp(),
                  child: const Text(
                    "Renvoyer le code OTP",
                    style: TextStyle(decoration: TextDecoration.underline),
                  ),
                )
              ],
            ),
          ),
        ),
      ),
    );
  }

  renvoyerCodeOtp() async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "niveau": niveau,
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}renvoyerOtp'),
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            afficherSucces(datas['message']);
          }else{
            afficherErreur(datas['message']);
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        user.code = 500;
        user.message = messageErreurTechnique(e);
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }
}
