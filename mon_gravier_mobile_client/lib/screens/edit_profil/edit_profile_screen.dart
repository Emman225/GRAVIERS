import 'package:flutter/material.dart';

import '../../components/bouton_retour.dart';
import '../../helper/constants.dart';
import 'components/edit_profile_form.dart';

class EditProfileScreen extends StatelessWidget {
  static String routeName = "/edit_profile";

  const EditProfileScreen({super.key});
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Mes information"),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: SafeArea(
        child: Container(
          width: double.infinity,
          height: heightOfScreen(context),
          child: const Padding(
            padding: EdgeInsets.symmetric(horizontal: 20),
            child: SingleChildScrollView(
              child: Column(
                children: [
                  SizedBox(height: 16),
                  Text(
                    "Veuillez renseigner correctement le formulaire",
                    textAlign: TextAlign.center,
                  ),
                  EditProfileForm(),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
