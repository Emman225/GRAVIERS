import 'package:flutter/material.dart';
import 'package:mon_gravier_com/screens/client_a_terme/components/client_a_terme_form.dart';

import '../../components/bouton_retour.dart';
import '../../helper/constants.dart';

class DemandeClientATermeScreen extends StatelessWidget {
  static String routeName = "/client_a_terme";

  const DemandeClientATermeScreen({super.key});
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Devenir un client à terme"),
        elevation: 0,
        leading: const BoutonRetour(),
      ),
      body: SafeArea(
        child: Container(
          width: double.infinity,
          height: heightOfScreen(context),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: SingleChildScrollView(
              child: Column(
                children: [
                  Image.asset('assets/images/client.jpg', height: 350,),
                  const SizedBox(height: 20),
                  const DemandeClientATermeForm(),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
