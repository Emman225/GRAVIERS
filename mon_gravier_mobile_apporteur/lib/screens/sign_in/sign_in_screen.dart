import 'dart:io';

import 'package:flutter/material.dart';
import 'package:mon_gravier_com_apporteur/helper/constants.dart';

import '../../constants.dart';
import '../../globale.dart';
import '../sign_up/sign_up_screen.dart';
import 'components/sign_form.dart';

class SignInScreen extends StatelessWidget {
  static String routeName = "/sign_in";

  const SignInScreen({super.key});
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Connectez-vous sur l'espace apporteur d'affaire"),
        automaticallyImplyLeading: false,
      ),
      body: WillPopScope(
        onWillPop: () async {
          bool backStatus = onWillPop();
          if (backStatus) {
            exit(0);
          }
          return false;
        },
        child: SafeArea(
          child: Container(
            width: double.infinity,
            height: heightOfScreen(context),
            decoration: const BoxDecoration(
              image: DecorationImage(
                image: AssetImage("assets/images/bg.jpg"),
                fit: BoxFit.cover,
                opacity: 0.2,
              ),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: SingleChildScrollView(
                child: Column(
                  children: [
                    SizedBox(height: (heightOfScreen(context) / 12)),
                    Image.asset('assets/images/logo.png', width: 200, height: 100),
                    const SizedBox(height: 20),
                    const Text(
                      "Bienvenue",
                      style: TextStyle(
                        color: Colors.black,
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const Text(
                      "Veuillez renseigner les informations de votre compte pour vous connecter",
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 16),
                    const SignForm(),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Text(
                          "Vous n'avez pas de compte? ",
                          style: TextStyle(fontSize: 16),
                        ),
                        GestureDetector(
                          onTap: () => Navigator.pushNamed(context, SignUpScreen.routeName),
                          child: const Text(
                            "Inscrivez-vous",
                            style: TextStyle(fontSize: 16, color: kPrimaryColor,decoration: TextDecoration.underline),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
