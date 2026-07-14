import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';

import '../../../components/custom_surfix_icon.dart';
import '../../../constants.dart';
import '../../../globale.dart';

class ProductDescription extends StatelessWidget {
  const ProductDescription({
    super.key,
    required this.product,
    required this.qteController,
    this.pressOnSeeMore,
  });

  final Produits product;
  final TextEditingController qteController;
  final GestureTapCallback? pressOnSeeMore;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Column(
            mainAxisAlignment: mainStart,
            crossAxisAlignment: crossStart,
            children: [
              Text(
                product.nom.toString(),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              Text("#${product.reference}",style: red14MediumTextStyle),
              addVerticalSpace(20),
              Text(formaterMontant(product.prixEffectif.toDouble()),style: Theme.of(context).textTheme.titleLarge),
            ],
          ),
        ),
        Align(
          alignment: Alignment.centerRight,
          child: Container(
            padding: const EdgeInsets.all(16),
            width: 48,
            decoration: const BoxDecoration(
              color: Color(0xFFFFE6E6),
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(20),
                bottomLeft: Radius.circular(20),
              ),
            ),
            child: SvgPicture.asset(
              "assets/icons/Heart Icon_2.svg",
              colorFilter: const ColorFilter.mode(Color(0xFFFF4848),
                  BlendMode.srcIn),
              height: 16,
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(
            left: 20,
            right: 64,
          ),
          child: Text(
            product.description.toString(),
            maxLines: 3,
          ),
        ),
        addVerticalSpace(30),
        Padding(
          padding: const EdgeInsets.all(8.0),
          child: TextFormField(
            keyboardType: TextInputType.number,
            textInputAction: TextInputAction.done,
            controller: qteController,
            decoration: const InputDecoration(
              labelText: "Quantité en tonne",
              hintText: "Saisir la quantité",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Shop Icon.svg"),
            ),
          ),
        ),
        addVerticalSpace(20),
      ],
    );
  }
}
