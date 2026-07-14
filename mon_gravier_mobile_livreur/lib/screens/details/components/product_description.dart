import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_easyloading/flutter_easyloading.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:mon_gravier_com_livreur/helper/constants.dart';
import 'package:http/http.dart' as http;

import '../../../components/custom_surfix_icon.dart';
import '../../../components/separateur_de_milier.dart';
import '../../../globale.dart';
import '../../../models/Product.dart';

class ProductDescription extends StatefulWidget {
  const ProductDescription({
    super.key,
    required this.product,
    required this.qteController,
    required this.mtnController,
    this.pressOnSeeMore,
  });

  final Produits product;
  final TextEditingController qteController;
  final TextEditingController mtnController;
  final GestureTapCallback? pressOnSeeMore;

  @override
  State<ProductDescription> createState() => _ProductDescriptionState();
}

class _ProductDescriptionState extends State<ProductDescription> {
  @override
  void initState() {
    widget.mtnController.text = widget.product.prixMoyen.toString();
    super.initState();
  }

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
                widget.product.nom.toString(),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              Text("#${widget.product.reference}", style: red14MediumTextStyle),
              addVerticalSpace(20),
              Text(formaterMontant(widget.product.prixMoyen!.toDouble()),
                  style: Theme.of(context).textTheme.titleLarge),
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
            child: GestureDetector(
              onTap: () => _ajouterRetirerDeLaListeDeSouhait(widget.product.id),
              child: SvgPicture.asset(
                "assets/icons/Heart Icon_2.svg",
                colorFilter:
                    const ColorFilter.mode(Color(0xFFFF4848), BlendMode.srcIn),
                height: 16,
              ),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(
            left: 20,
            right: 64,
          ),
          child: Text(
            widget.product.description.toString(),
            maxLines: 3,
          ),
        ),
        addVerticalSpace(30),
        Padding(
          padding: const EdgeInsets.all(8.0),
          child: TextFormField(
            keyboardType: TextInputType.number,
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r"[0-9.]")),
              TextInputFormatter.withFunction((oldValue, newValue) {
                final text = newValue.text;
                return text.isEmpty
                    ? newValue
                    : double.tryParse(text) == null
                        ? oldValue
                        : newValue;
              }),
            ],
            onFieldSubmitted: (value) {
              _updateQte(value);
            },
            onSaved: (value) {
              _updateQte(value);
            },
            onChanged: (value) {
              _updateQte(value);
            },
            textInputAction: TextInputAction.done,
            controller: widget.qteController,
            decoration: InputDecoration(
              labelText: "Quantité en ${widget.product.unite}",
              hintText: "Saisir la quantité",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: const CustomSurffixIcon(
                  svgIcon: "assets/icons/Shop Icon.svg"),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(8.0),
          child: TextFormField(
            keyboardType: TextInputType.number,
            textInputAction: TextInputAction.done,
            controller: widget.mtnController,
            inputFormatters: [ThousandsSeparatorInputFormatter()],
            onFieldSubmitted: (value) {
              _updateMontant(value);
            },
            onSaved: (value) {
              _updateMontant(value);
            },
            onChanged: (value) {
              _updateMontant(value);
            },
            decoration: const InputDecoration(
              labelText: "Montant à payer",
              hintText: "Montant à payer",
              // If  you are using latest version of flutter then lable text and hint text shown like this
              // if you r using flutter less then 1.20.* then maybe this is not working properly
              floatingLabelBehavior: FloatingLabelBehavior.always,
              suffixIcon: CustomSurffixIcon(svgIcon: "assets/icons/Cash.svg"),
            ),
          ),
        ),
        addVerticalSpace(20),
      ],
    );
  }

  _updateQte(value) {
    try {
      double qte = double.parse(value);
      setState(() {
        var pm = widget.product.prixMoyen ?? 0;
        var mtn = pm * qte;
        widget.mtnController.text = mtn.round().toString();
      });
    } catch (e) {
      if (kDebugMode) {
        print(e.toString());
      }
    }
  }

  _updateMontant(value) {
    try {
      double mtn = double.parse(value);
      setState(() {
        var pm = widget.product.prixMoyen ?? 0;
        var qte = mtn / pm;
        widget.qteController.text = qte.toStringAsFixed(1);
      });
    } catch (e) {
      if (kDebugMode) {
        print(e.toString());
      }
    }
  }

  _ajouterRetirerDeLaListeDeSouhait(int? id) async {
    if (await verifierConnexion()) {
      afficherChargement();

      var param = {
        "access": user.token.toString(),
        "type": user.type.toString(),
        "niveau": 1
      };

      if (kDebugMode) {
        print(param);
      }

      try {
        retourHttp = await http
            .post(Uri.parse('${lienAPI()}ajouter-retirer-liste-souhait/$id'),
                headers: {"Content-Type": "application/json"},
                body: jsonEncode(param))
            .timeout(const Duration(minutes: 2));
        var datas = jsonDecode(retourHttp.body);
        if (retourHttp.statusCode == 200) {
          if (datas['code'] == 200) {
            EasyLoading.showSuccess(datas['message']);
          } else {
            EasyLoading.showError(datas['message']);
          }
        }
      } catch (e) {
        user.code = 500;
        user.message = "Une erreur s'est produite veuillez reesayer plus tard";
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      EasyLoading.showInfo("Veuillez vérifier votre connexion internet");
    }
  }
}
