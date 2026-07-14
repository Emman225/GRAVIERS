import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/sign_up/sign_up_screen.dart';

import '../helper/constants.dart';
import '../screens/sign_in/sign_in_screen.dart';

class EmptyUserWidget extends StatelessWidget {
  final bool showImage;
  final String msg;
  const EmptyUserWidget({super.key, this.showImage = true, this.msg = "Veuillez vous connecter ou vous inscrire avant d'accéder a cette section"});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        crossAxisAlignment: crossCenter,
        mainAxisAlignment: mainCenter,
        children: [
          if(showImage) Image.asset("assets/images/connexion.jpg"),
          Padding(
            padding: const EdgeInsets.all(15.0),
            child: Text(
              msg,
              textAlign: TextAlign.center, style: red14MediumTextStyle,),
          ),
          Padding(
            padding: const EdgeInsets.all(15.0),
            child: Row(
              children: [
                Expanded(
                  child: ElevatedButton(
                    style: ButtonStyle(
                      backgroundColor: MaterialStateProperty.all(Colors.blueAccent)
                    ),
                      onPressed: () => Get.toNamed(SignInScreen.routeName),
                      child: const Text("Connexion")),
                ),
                addHorizontalSpace(10),
                Expanded(
                  child: ElevatedButton(
                      style: ButtonStyle(
                          backgroundColor: MaterialStateProperty.all(Colors.blueAccent)
                      ),
                      onPressed: () => Get.toNamed(SignUpScreen.routeName),
                      child: const Text("Inscription")),
                ),
              ],
            ),
          )
        ],
      ),
    );
  }
}

