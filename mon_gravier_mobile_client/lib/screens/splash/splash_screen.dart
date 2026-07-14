import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/impression/impression_recu_paiement_pdf.dart';
import 'package:mon_gravier_com/screens/init_screen.dart';
import 'package:app_links/app_links.dart'; // remplace uni_links (abandonné, incompatible Flutter 3.38)

import '../../constants.dart';
import '../../globale.dart';
import '../../models/code_promo.dart';
import '../commande_error/commande_error_screen.dart';
import '../sign_in/sign_in_screen.dart';
import 'components/splash_content.dart';

class SplashScreen extends StatefulWidget {
  static String routeName = "/splash";

  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  int currentPage = 0;
  List<Map<String, String>> splashData = [
    {
      "text":
          "Construisez vos rêves dès aujourd'hui \navec notre sable et gravier de qualité.",
      "image": "assets/images/onboarding_1.svg"
    },
    {
      "text":
          "Réserver n’a jamais été aussi simple \ngrâce à notre interface intuitive.",
      "image": "assets/images/onboarding_2.svg"
    },
    {
      "text": "Nous livrons partout ! \nPeu importe où vous vous trouvez.",
      "image": "assets/images/onboarding_3.svg"
    },
  ];

  PageController pageController = PageController();

  recuperationDonnee() async {
    user.token = await lireOuEcrireDonnee("token", '', 2);
    user.nom = await lireOuEcrireDonnee("nom", '', 2);
    user.photo = await lireOuEcrireDonnee("photo", '', 2);
    user.code_parrain = await lireOuEcrireDonnee("code_parrain", '', 2);
    user.clientATerme = await lireOuEcrireDonnee("cat", '', 2) == "1" ? true : false;
    final t = await lireOuEcrireDonnee("type", '', 2);
    user.type = int.parse(t.isEmpty ? '0' : t);
    if (user.token != '' && user.type! > 0 && user.nom != '') {
      user.code = 0;
      Get.toNamed(InitScreen.routeName, arguments: 2);
    }
  }

  String _deepLink = "Aucun lien détecté";
  String _page = "";
  String _code = "";
  int _statut = 0;

  Future<void> initDeepLinkListener() async {
    // Écoute des liens profonds (app_links : même flux que l'ancien uni_links)
    AppLinks().uriLinkStream.listen((Uri? uri) {
      if (uri != null) {
        _deepLink = uri.toString();
        _page = uri.queryParameters['page'] ?? "";
        switch (_page) {
          case "retour_commande":
            _statut = int.parse(uri.queryParameters['statut'].toString()) ?? 0;
            _code = uri.queryParameters['code'] ?? "";
            if (_statut == 1) {
              paniers.clear();
              reduction = Reduction();
              Get.toNamed(ImpressionRecuPaiementPdf.routeName,
                  arguments: [1, _code, 0]);
            } else {
              Get.toNamed(CommandeErrorScreen.routeName,
                  arguments: "Vous n'avez pas validé le paiement");
            }
            break;
        }
        setState(() {});
      }
    });
  }

  @override
  void initState() {
    super.initState();
    initDeepLinkListener();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      recuperationDonnee();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: SizedBox(
          width: double.infinity,
          child: Column(
            children: <Widget>[
              Expanded(
                flex: 2,
                child: PageView.builder(
                  onPageChanged: (value) {
                    setState(() {
                      currentPage = value;
                    });
                  },
                  controller: pageController,
                  itemCount: splashData.length,
                  itemBuilder: (context, index) => SplashContent(
                    image: splashData[index]["image"],
                    text: splashData[index]['text'],
                  ),
                ),
              ),
              Expanded(
                flex: 1,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Column(
                    children: <Widget>[
                      const Spacer(),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: List.generate(
                          splashData.length,
                          (index) => AnimatedContainer(
                            duration: kAnimationDuration,
                            margin: const EdgeInsets.only(right: 5),
                            height: 6,
                            width: currentPage == index ? 20 : 6,
                            decoration: BoxDecoration(
                              color: currentPage == index
                                  ? kPrimaryColor
                                  : const Color(0xFFD8D8D8),
                              borderRadius: BorderRadius.circular(3),
                            ),
                          ),
                        ),
                      ),
                      const Spacer(flex: 3),
                      ElevatedButton(
                        onPressed: () {
                          if (currentPage == (splashData.length - 1)) {
                            Navigator.pushNamed(
                                context, SignInScreen.routeName);
                          } else {
                            setState(() {
                              currentPage++;
                              pageController.nextPage(
                                duration: const Duration(seconds: 1),
                                curve: Curves.decelerate,
                              );
                            });
                          }
                        },
                        child: const Text("Continuer"),
                      ),
                      const Spacer(),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
