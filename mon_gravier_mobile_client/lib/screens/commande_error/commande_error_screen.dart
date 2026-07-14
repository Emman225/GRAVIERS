import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/screens/init_screen.dart';

class CommandeErrorScreen extends StatefulWidget {
  static String routeName = "/commande_error";

  const CommandeErrorScreen({super.key});

  @override
  State<CommandeErrorScreen> createState() => _CommandeErrorScreenState();
}

class _CommandeErrorScreenState extends State<CommandeErrorScreen> {

  String message = '';

  @override
  void initState() {
    message = Get.arguments;
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const SizedBox(),
        title: const Text("Ooops"),
      ),
      body: Column(
        children: [
          const SizedBox(height: 16),
          Image.asset(
            "assets/images/error.png",
            height: MediaQuery.of(context).size.height * 0.4, //40%
          ),
          const SizedBox(height: 16),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 30,
              fontWeight: FontWeight.bold,
              color: Colors.black,
            ),
          ),
          const Spacer(),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: ElevatedButton(
              onPressed: () {
                Get.offAllNamed(InitScreen.routeName);
              },
              child: const Text("Aller à l'accueil"),
            ),
          ),
          const Spacer(),
        ],
      ),
    );
  }
}
