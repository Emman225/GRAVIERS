import 'package:flutter/material.dart';
import 'package:mon_gravier_com/helper/constants.dart';

class SectionTitle extends StatelessWidget {
  const SectionTitle({
    super.key,
    required this.title,
    required this.press,
    required this.showVoirPlus,
  });

  final String title;
  final GestureTapCallback press;
  final bool showVoirPlus;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w600,
            color: Colors.black,
          ),
        ),
          TextButton(
            onPressed: press,
            style: TextButton.styleFrom(foregroundColor: Colors.grey),
            child: Text(showVoirPlus ? "Voir plus" : ""),
          ),
      ],
    );
  }
}
