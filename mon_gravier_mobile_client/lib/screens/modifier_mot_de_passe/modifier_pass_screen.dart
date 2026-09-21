import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/modifier_mot_de_passe/components/modifier_pass_form.dart';
import '../../components/bouton_retour.dart';

class ModifierPasseScreen extends StatefulWidget {
  static String routeName = "/modifier_pass";

  const ModifierPasseScreen({super.key});

  @override
  State<ModifierPasseScreen> createState() => _ModifierPasseScreenState();
}

class _ModifierPasseScreenState extends State<ModifierPasseScreen> {
  int niveau = Get.arguments;

  @override
  void initState() {
    niveau = Get.arguments;
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Modifier mon mot de passe"),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: SingleChildScrollView(
            child: Column(
              children: [
                Image.asset('assets/images/edit_pass.jpg'),
                const SizedBox(height: 16),
                ModifierPassForm(niveau: niveau),
                const SizedBox(height: 20),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
