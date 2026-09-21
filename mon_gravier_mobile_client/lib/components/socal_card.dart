import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../constants.dart';

class SocalCard extends StatelessWidget {
  const SocalCard({
    Key? key,
    this.icon,
    this.press,
  }) : super(key: key);

  final String? icon;
  final Function? press;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: press as void Function()?,
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 10),
        padding: const EdgeInsets.all(12),
        height: 40,
        width: 40,
        decoration: const BoxDecoration(
          color: kSurfaceMutedColor,
          shape: BoxShape.circle,
        ),
        child: SvgPicture.asset(icon!),
      ),
    );
  }
}
