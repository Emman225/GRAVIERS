import 'package:flutter/material.dart';
import 'package:item_count_number_button/item_count_number_button.dart';
import 'package:mon_gravier_com/helper/constants.dart';

import '../../../constants.dart';
import '../../../globale.dart';
import '../../../models/Cart.dart';

class CartCard extends StatefulWidget {
  CartCard({
    super.key,
    required this.cart,
    this.showCounter = true,
  });

  final Cart cart;
  bool showCounter = true;

  @override
  State<CartCard> createState() => _CartCardState();
}

class _CartCardState extends State<CartCard> {
  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: mainSpaceBet,
      children: [
        SizedBox(
          width: 50,
          child: AspectRatio(
            aspectRatio: 0.88,
            child: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFF5F6F9),
                borderRadius: BorderRadius.circular(15),
              ),
              child: Image.network(widget.cart.product.image.toString()),
            ),
          ),
        ),
        Flexible(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                widget.cart.product.nom.toString(),
                style: const TextStyle(color: Colors.black, fontSize: 16),
              ),
              const SizedBox(height: 8),
              Text.rich(
                TextSpan(
                  text: "${formaterMontant(widget.cart.product.prixEffectif.toDouble())} / ${widget.cart.product.unite}",
                  style: const TextStyle(
                      fontWeight: FontWeight.w600, color: kPrimaryColor),
                  children: [
                    TextSpan(
                        text: " x${widget.cart.numOfItem}",
                        style: Theme.of(context).textTheme.bodyLarge),
                    if (widget.cart.product.aPrixPersonnalise)
                      TextSpan(
                        text: "\n${formaterMontant(widget.cart.product.prixMoyen!.toDouble())}",
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: redColor,
                          decoration: TextDecoration.lineThrough,
                        ),
                      ),
                  ],
                ),
              )
            ],
          ),
        ),
        if (widget.showCounter == true) ...[
          ItemCount(
            initialValue: widget.cart.numOfItem,
            minValue: 1,
            maxValue: 1000,
            decimalPlaces: 1,
            step: 0.1,
            color: primaryColor,
            onChanged: (value) {
              setState(() {
                var val = value.toDouble().toStringAsFixed(1);
                widget.cart.numOfItem = double.parse(val);
              });
            },
          ),
        ],
        if (widget.cart.product.type_affaire == LOCATION) ...[
          Text(
            "${widget.cart.nbreJours} Jour(s)",
            style: const TextStyle(color: Colors.black, fontSize: 16),
          ),
        ]
      ],
    );
  }
}
