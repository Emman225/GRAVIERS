import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../helper/constants.dart';
import '../screens/init_screen.dart';

class ContinuerSansCompteWidget extends StatelessWidget {
  const ContinuerSansCompteWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(15.0),
      child: ElevatedButton(
        style: ButtonStyle(
          backgroundColor: MaterialStateProperty.all(greenColor),
        ),
        onPressed: () => Get.toNamed(InitScreen.routeName),
        child: const Text("Continuer sans compte"),
      ),
    );
  }
}

