import 'package:flutter/material.dart';
import 'package:mon_gravier_com/helper/constants.dart';

import '../constants.dart';
import '../screens/sign_up/sign_up_screen.dart';

class NoAccountText extends StatelessWidget {
  const NoAccountText({
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.start,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          "Vous n'avez pas de compte?",
          style: TextStyle(fontSize: 12),
        ),
        addHorizontalSpace(5),
        GestureDetector(
          onTap: () => Navigator.pushNamed(context, SignUpScreen.routeName),
          child: const Text(
            "Inscrivez-vous",
            style: TextStyle(fontSize: 13, color: kPrimaryColor,decoration: TextDecoration.underline),
          ),
        ),
      ],
    );
  }
}
