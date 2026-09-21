import 'package:flutter/material.dart';
import 'package:mon_gravier_com/helper/constants.dart';

import '../../../components/compteur_quantite.dart';
import '../../../constants.dart';
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
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 10),
      child: Row(
        mainAxisAlignment: mainSpaceBet,
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                widget.cart.product.nom.toString(),
                style: const TextStyle(color: Colors.black, fontSize: 16),
                maxLines: 2,
              ),
              const SizedBox(height: 8),
              Text.rich(
                TextSpan(
                  text: "${widget.cart.product.unite}",
                  style: const TextStyle(
                      fontWeight: FontWeight.w600, color: kPrimaryColor),
                  children: [
                    TextSpan(
                        text: " x${widget.cart.numOfItem}",
                        style: Theme.of(context).textTheme.bodyLarge),
                  ],
                ),
              )
            ],
          ),

          if (widget.showCounter) ...[
            CompteurQuantite(
              valeurInitiale: widget.cart.numOfItem,
              minimum: 1,
              maximum: 1000,
              decimales: 1,
              pas: 0.1,
              couleur: kPrimaryColor,
              onChanged: (value) {
                setState(() {
                  var val = value.toDouble().toStringAsFixed(1);
                  widget.cart.numOfItem = double.parse(val);
                });
              },
            ),
          ],

        ],
      ),
    );
  }
}
