import 'package:flutter/material.dart';
import 'package:carousel_slider/carousel_slider.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import 'package:mon_gravier_com/screens/home/components/special_offers.dart';

import '../../../helper/constants.dart';

class TopSlider extends StatelessWidget {
  final List<Bannieres> items;
  const TopSlider({super.key, required this.items });

  // int _current = 0;
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const SizedBox(height: 10),
        CarouselSlider(
            items: getItems(context),
            options: CarouselOptions(
              height: 160,
              autoPlay: true,
              autoPlayInterval: const Duration(seconds: 3),
              autoPlayAnimationDuration: const Duration(milliseconds: 800),
              autoPlayCurve: Curves.fastOutSlowIn,
              onPageChanged: (index, reason) {
                // setState(() {
                //   _current = index;
                // });
              },
              scrollDirection: Axis.horizontal,
            )
        ),
        // Row(
        //   mainAxisAlignment: mainCenter,
        //   crossAxisAlignment: crossCenter,
        //   children: items.map((b) {
        //     int index = items.indexOf(b);
        //     return Container(
        //       width: 10,
        //       height: 40,
        //       margin: EdgeInsets.symmetric(
        //         // vertical: Dimensions.marginSize * 0.1,
        //         horizontal: 30 * 0.2, vertical: 10
        //       ),
        //       decoration: BoxDecoration(
        //         shape: BoxShape.circle,
        //         color: _current == index
        //             ? greenColor
        //             : primaryColor,
        //       ),
        //     );
        //   }).toList(),
        // )
      ],
    );
  }

  getItems(context){
    List<Widget> datas = [];
    for (var i in items) {
      // if (kDebugMode) {
      //   print(i.image.toString());
      // }
      datas.add(
        SpecialOfferCard(
          image: i.image.toString(),
          category: i.titre.toString(),
          sousTitre: i.sousTitre.toString(),
          online: true,
          numOfBrands: 18,
          myHeight: 160,
          myWidth: widthOfScreen(context),
          press: () {

          },
        ),
      );
    }
    return datas;
  }
}
