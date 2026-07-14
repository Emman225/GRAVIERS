import 'package:flutter/material.dart';
import 'package:mon_gravier_com/helper/constants.dart';

import '../../../models/ConfigModel.dart';
import 'section_title.dart';

class SpecialOffers extends StatelessWidget {

  List<Bannieres> items = [];

  SpecialOffers({
    super.key,
    required this.items
  });

  // @override
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: SectionTitle(
            showVoirPlus: false,
            title: "Specialement pour vous",
            press: () {},
          ),
        ),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children:[
              ...List.generate(items.length, (index) {
                return SpecialOfferCard(
                  image: items[index].image.toString(),
                  category: items[index].titre.toString(),
                  sousTitre: items[index].sousTitre.toString(),
                  online: true,
                  numOfBrands: 0,
                  press: () {
                    // Navigator.pushNamed(context, ProductsScreen.routeName);
                  },
                );
              })
              // SpecialOfferCard(
              //   image: "assets/images/Image Banner 2.png",
              //   category: "Smartphone",
              //   numOfBrands: 18,
              //   press: () {
              //   },
              // ),
              // SpecialOfferCard(
              //   image: "assets/images/Image Banner 3.png",
              //   category: "Fashion",
              //   numOfBrands: 24,
              //   press: () {
              //   },
              // ),
            ],
          ),
        ),
      ],
    );
  }

  getWidget() {
    return items
        .map((e) => SpecialOfferCard(
      image: e.image.toString(),
      category: e.titre.toString(),
      sousTitre: e.sousTitre.toString(),
      online: true,
      numOfBrands: 0,
      press: () {
        // Navigator.pushNamed(context, ProductsScreen.routeName);
      },
    ))
        .toList();
  }
}

class SpecialOfferCard extends StatelessWidget {
  const SpecialOfferCard({
    super.key,
    required this.category,
    required this.image,
    required this.numOfBrands,
    required this.press,
    this.myWidth = 242,
    this.myHeight = 100,
    this.online = false,
    this.sousTitre = '',
  });

  final String category, image, sousTitre;
  final int numOfBrands;

  final double myWidth, myHeight;
  final GestureTapCallback press;
  final bool online;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 20),
      child: GestureDetector(
        onTap: press,
        child: SizedBox(
          width: myWidth,
          height: myHeight,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: Stack(
              children: [
                online
                    ? Image.network(
                        image,
                        fit: BoxFit.cover,
                  height: myHeight,
                  width: heightOfScreen(context),
                      )
                    : Image.asset(
                        image,
                        fit: BoxFit.cover,
                      ),
                Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Colors.black54,
                        Colors.black38,
                        Colors.black26,
                        Colors.transparent,
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 15,
                    vertical: 10,
                  ),
                  child: Text.rich(
                    TextSpan(
                      style: const TextStyle(color: Colors.white),
                      children: [
                        TextSpan(
                          text: "$category\n",
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        TextSpan(text: online ? sousTitre : "$numOfBrands Brands")
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
