import 'package:flutter/material.dart';
import 'package:mon_gravier_com/screens/client_a_terme/components/client_a_terme_form.dart';

import '../../helper/constants.dart';

class DemandeClientATermeScreen extends StatelessWidget {
  static String routeName = "/client_a_terme";

  const DemandeClientATermeScreen({super.key});
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Devenir un client à terme"),
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
        child: Container(
          width: double.infinity,
          height: heightOfScreen(context),
          decoration: BoxDecoration(
            image: const DecorationImage(
              image: AssetImage("assets/images/bg.jpg"),
              fit: BoxFit.cover,
              opacity: 0.1,
            ),
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
