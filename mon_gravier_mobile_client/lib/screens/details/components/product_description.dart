import 'dart:convert';

import 'package:date_field/date_field.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:mon_gravier_com/helper/constants.dart';
import 'package:mon_gravier_com/models/ConfigModel.dart';
import 'package:http/http.dart' as http;

import '../../../components/custom_surfix_icon.dart';
import '../../../components/separateur_de_milier.dart';
import '../../../constants.dart';
import '../../../globale.dart';

class ProductDescription extends StatefulWidget {
  const ProductDescription({
    super.key,
    required this.product,
    required this.qteController,
    required this.mtnController,
    required this.debutController,
    required this.finController,
    this.pressOnSeeMore,
  });

  final Produits product;
  final TextEditingController qteController;
  final TextEditingController mtnController;
  final TextEditingController debutController;
  final TextEditingController finController;
  final GestureTapCallback? pressOnSeeMore;


  @override
  State<ProductDescription> createState() => _ProductDescriptionState();
}

class _ProductDescriptionState extends State<ProductDescription> {

  final String _du = DateFormat('yyyy-MM-dd').format(DateTime.now().add(const Duration(days: 1)));
  final String _au = DateFormat('yyyy-MM-dd').format(DateTime.now().add(const Duration(days: 1)));

  @override
  void initState() {
    widget.mtnController.text = widget.product.prixEffectif.toString();
    widget.debutController.text = _du;
    widget.finController.text = _au;
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    final produit = widget.product;
    final bool estLocation = produit.type_affaire == LOCATION;
    final double? ancienPrix = produit.aPrixPersonnalise
        ? produit.prixMoyen?.toDouble()
        : ((produit.prixReduction ?? 0) > 0
            ? produit.prixReduction?.toDouble()
            : null);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: kSpaceXl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // « VENTE de Sable lavé » : la nature de l'offre était
                        // collée au nom du produit, en corps de titre. Elle
                        // devient une étiquette, et le nom redevient le titre.
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: kSpaceSm, vertical: 3),
                          decoration: BoxDecoration(
                            color: estLocation
                                ? kAccentSoftColor
                                : kPrimarySoftColor,
                            borderRadius: BorderRadius.circular(kRadiusPill),
                          ),
                          child: Text(
                            estLocation ? "LOCATION" : "VENTE",
                            style: kEtiquetteStyle.copyWith(
                              color: estLocation ? kAccentColor : kPrimaryColor,
                              fontSize: 10,
                            ),
                          ),
                        ),
                        const SizedBox(height: kSpaceMd),
                        Text(
                          produit.nom.toString(),
                          style: kTitreEcranStyle.copyWith(fontSize: 22),
                        ),
                        const SizedBox(height: kSpaceXs),
                        // La référence s'affichait en ROUGE, comme une alerte.
                        Text("Référence ${produit.reference}", style: kLegendeStyle),
                      ],
                    ),
                  ),
                  const SizedBox(width: kSpaceMd),
                  // Le bouton « liste de souhaits » était une demi-pastille
                  // rose accrochée au bord droit de l'écran, sans libellé ni
                  // état visible. Il devient un bouton rond, cadré, de taille
                  // tactile.
                  Material(
                    color: kSurfaceMutedColor,
                    shape: const CircleBorder(),
                    clipBehavior: Clip.antiAlias,
                    child: InkWell(
                      onTap: () =>
                          _ajouterRetirerDeLaListeDeSouhait(produit.id),
                      child: Padding(
                        padding: const EdgeInsets.all(kSpaceMd),
                        child: SvgPicture.asset(
                          "assets/icons/Heart Icon_2.svg",
                          height: 18,
                          width: 18,
                          colorFilter: const ColorFilter.mode(
                              kErrorColor, BlendMode.srcIn),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: kSpaceLg),
              Row(
                crossAxisAlignment: CrossAxisAlignment.baseline,
                textBaseline: TextBaseline.alphabetic,
                children: [
                  Flexible(
                    child: Text(
                      formaterMontant(produit.prixEffectif.toDouble()),
                      style: kMontantFortStyle.copyWith(fontSize: 26),
                    ),
                  ),
                  Text(
                    " / ${produit.unite}",
                    style: kCorpsSecondaireStyle,
                  ),
                  if (ancienPrix != null) ...[
                    const SizedBox(width: kSpaceMd),
                    Flexible(
                      child: Text(
                        formaterMontant(ancienPrix),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 15,
                          color: kTextMutedColor,
                          decoration: TextDecoration.lineThrough,
                          decorationColor: kTextMutedColor,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
              if (produit.description != null &&
                  produit.description.toString().trim().isNotEmpty &&
                  produit.description.toString() != 'null') ...[
                const SizedBox(height: kSpaceLg),
                // La description était bornée à trois lignes, coupées net,
                // sans « voir plus » : la fin du texte était perdue.
                Text(
                  produit.description.toString(),
                  style: kCorpsStyle.copyWith(color: kTextSecondaryColor),
                ),
              ],
            ],
          ),
        ),
        addVerticalSpace(30),
        if (widget.product.type_affaire == VENTE) ...[
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
                labelText: "Quantité en ${widget.product.unite} *",
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
                _updateMontant(value ?? '0');
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
        ]else ...[
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextFormField(
              keyboardType: TextInputType.number,
              textInputAction: TextInputAction.done,
              controller: widget.qteController,
              decoration: const InputDecoration(
                labelText: "Quantité *",
                hintText: "Saisir la quantité",
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: Row(
              children: [
                Flexible(
                  child: DateTimeFormField(
                    decoration: const InputDecoration(
                      hintStyle: TextStyle(color: Colors.black45),
                      errorStyle: TextStyle(color: Colors.redAccent),
                      border: OutlineInputBorder(),
                      suffixIcon: Icon(Icons.event_note),
                      labelText: 'Date début *',
                    ),
                    initialValue: DateTime.parse(widget.debutController.text),
                    use24hFormat: true,
                    mode: DateTimeFieldPickerMode.date,
                    dateFormat: DateFormat('dd-MM-yyyy'),
                    autovalidateMode: AutovalidateMode.always,
                    onDateSelected: (DateTime value) {
                      widget.debutController.text = DateFormat('yyyy-MM-dd').format(value);
                    },
                  ),
                ),
                addHorizontalSpace(5),
                Flexible(
                  child: DateTimeFormField(
                    decoration: const InputDecoration(
                      hintStyle: TextStyle(color: Colors.black45),
                      errorStyle: TextStyle(color: Colors.redAccent),
                      border: OutlineInputBorder(),
                      suffixIcon: Icon(Icons.event_note),
                      labelText: 'Date fin *',
                    ),
                    initialValue: DateTime.parse(widget.finController.text),
                    use24hFormat: true,
                    mode: DateTimeFieldPickerMode.date,
                    dateFormat: DateFormat('dd-MM-yyyy'),
                    autovalidateMode: AutovalidateMode.always,
                    onDateSelected: (DateTime value) {
                      widget.finController.text = DateFormat('yyyy-MM-dd').format(value);
                    },
                  ),
                ),
              ],
            ),
          ),
        ],
        addVerticalSpace(20),
      ],
    );
  }

  _updateQte(value) {
    try {
      double qte = double.parse(value);
      setState(() {
        var pm = widget.product.prixEffectif;
        var mtn = pm * qte;
        widget.mtnController.text = mtn.round().toString();
      });
    } catch (e) {
      if (kDebugMode) {
        print(e.toString());
      }
    }
  }

  _updateMontant(String value) {
    try {
      double mtn = double.parse(value.removeAllWhitespace);
      setState(() {
        var pm = widget.product.prixEffectif;
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
            afficherSucces(datas['message']);
          } else {
            afficherErreur(datas['message']);
          }
        } else {
          // Sans cette branche, une réponse serveur en erreur ne produisait
          // AUCUNE réaction à l'écran : l'utilisateur recliquait sans savoir.
          afficherErreur("Erreur serveur (code ${retourHttp.statusCode}). Veuillez réessayer.");
        }
      } catch (e) {
        user.code = 500;
        user.message = messageErreurTechnique(e);
        if (kDebugMode) {
          print(e.toString());
        }
      }
      fermerChargement();
    } else {
      afficherInfo("Veuillez vérifier votre connexion internet");
    }
  }
}
