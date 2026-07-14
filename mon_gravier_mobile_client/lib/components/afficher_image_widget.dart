import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/helper/constants.dart';

class AfficherImageWidget extends StatefulWidget {
  static String routeName = "/afficherImage";

  const AfficherImageWidget({super.key});

  @override
  State<AfficherImageWidget> createState() => _AfficherImageWidgetState();
}

class _AfficherImageWidgetState extends State<AfficherImageWidget> {
  String urlImage = "";

  @override
  void initState() {
    urlImage = Get.arguments;
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text("Affichage de l'image"),
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
      body: Image.network(urlImage,
          width: widthOfScreen(context), height: heightOfScreen(context), fit: BoxFit.fill),
    );
  }
}
