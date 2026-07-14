import 'package:flutter/material.dart';
import 'package:mon_gravier_com_apporteur/helper/constants.dart';
import 'package:mon_gravier_com_apporteur/screens/modifier_mot_de_passe/components/modifier_pass_form.dart';

class ModifierPasseScreen extends StatelessWidget {
  static String routeName = "/modifier_pass";

  const ModifierPasseScreen({super.key});
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Modifier mon mot de passe"),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.all(8.0),
          child: ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
            },
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
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: SingleChildScrollView(
            child: Column(
              children: [
                Image.asset('assets/images/edit_pass.jpg'),
                const SizedBox(height: 16),
                const ModifierPassForm(),
                const SizedBox(height: 20),
              ],
            ),
          ),
        ),
      ),
    );
  }

}
