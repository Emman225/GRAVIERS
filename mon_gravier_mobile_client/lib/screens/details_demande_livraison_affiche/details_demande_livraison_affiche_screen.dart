import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../helper/constants.dart';
import '../../models/Cart.dart';
import 'components/cart_card.dart';

class DetailsDemandeLivraisonAfficheScreen extends StatefulWidget {
  static String routeName = "/detailsDemandeLivraisonAffiche";
  const DetailsDemandeLivraisonAfficheScreen({super.key});

  @override
  State<DetailsDemandeLivraisonAfficheScreen> createState() => _DetailsDemandeLivraisonAfficheScreenState();
}

class _DetailsDemandeLivraisonAfficheScreenState extends State<DetailsDemandeLivraisonAfficheScreen> {

  List<Cart> datas = Get.arguments;

  @override
  void initState() {
    datas = Get.arguments;
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Détails demande de livraison",
          style: TextStyle(color: Colors.black),
        ),
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
      body: datas.isEmpty
          ? Center(
              child: Image.asset('assets/images/empty_card.gif'),
            )
          : Container(
              width: double.infinity,
              height: heightOfScreen(context),
              decoration: const BoxDecoration(
                image: DecorationImage(
                  image: AssetImage("assets/images/bg.jpg"),
                  fit: BoxFit.cover,
                  opacity: 0.1,
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 5),
                child: ListView.builder(
                  itemCount: datas.length,
                  itemBuilder: (context, index) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    child: CartCard(cart: datas[index]),
                  ),
                ),
              ),
            ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
    );
  }
}
