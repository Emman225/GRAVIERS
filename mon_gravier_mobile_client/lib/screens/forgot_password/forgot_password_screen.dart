import 'package:flutter/material.dart';

import '../../components/bouton_retour.dart';
import '../../components/continuer_san_compte.dart';
import '../../helper/constants.dart';
import 'components/forgot_pass_form.dart';

class ForgotPasswordScreen extends StatelessWidget {
  static String routeName = "/forgot_password";

  const ForgotPasswordScreen({super.key});
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Mot de passe oublié"),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Column(
            children: [
              SizedBox(height: (heightOfScreen(context) / 15)),
              Image.asset('assets/images/mdp.jpg'),
              const SizedBox(height: 32),
              const ForgotPassForm(),
            ],
          ),
        ),
      ),
      bottomNavigationBar: const ContinuerSansCompteWidget(),
    );
  }
}
