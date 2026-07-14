import 'package:flutter/material.dart';
import 'package:flutter_svg/svg.dart';
import 'package:mon_gravier_com/helper/constants.dart';

import '../../../constants.dart';

class SplashContent extends StatefulWidget {
  const SplashContent({
    super.key,
    this.text,
    this.image,
  });
  final String? text, image;

  @override
  State<SplashContent> createState() => _SplashContentState();
}

class _SplashContentState extends State<SplashContent> {
  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        const Spacer(),
        const Text(
          "MON GRAVIER",
          style: TextStyle(
            fontSize: 32,
            color: kPrimaryColor,
            fontWeight: FontWeight.bold,
          ),
        ),
        Text(
          widget.text!,
          textAlign: TextAlign.center,
        ),
        addVerticalSpace(30),
        SvgPicture.asset(
          widget.image!,
          height: 265,
          width: 235,
        ),

        // Image.asset(
        //   widget.image!,
        //   height: 265,
        //   width: 235,
        // ),
      ],
    );
  }
}
