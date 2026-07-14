import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:mon_gravier_com/globale.dart';
import 'package:mon_gravier_com/screens/details_demande_livraison/components/check_out_card.dart';
import 'package:select_searchable_list/select_searchable_list.dart';

import '../../helper/constants.dart';
import '../../models/Cart.dart';
import '../../models/ConfigModel.dart';
import 'components/cart_card.dart';

class DetailsDemandeLivraisonScreen extends StatefulWidget {
  static String routeName = "/finalisationDemandeLivraison";

  const DetailsDemandeLivraisonScreen({super.key});

  @override
  State<DetailsDemandeLivraisonScreen> createState() =>
      _DetailsDemandeLivraisonScreenState();
}

class _DetailsDemandeLivraisonScreenState
    extends State<DetailsDemandeLivraisonScreen> {
  TextEditingController articleController = TextEditingController();
  TextEditingController uniteController = TextEditingController();
  TextEditingController qteController = TextEditingController();
  List<Unites> _listUnite = [];
  int _unite = 0;

  @override
  void initState() {
    articleController = TextEditingController();
    uniteController = TextEditingController();
    qteController = TextEditingController();
    _listUnite = user.configs?.unites ?? [];
    paniers.clear();
    super.initState();
  }

  @override
  void dispose() {
    articleController.dispose();
    uniteController.dispose();
    qteController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          "Détails des produits à livrer",
          style: TextStyle(color: Colors.black),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.all(8.0),
          child: ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(
              shape: const CircleBorder(),
              padding: EdgeInsets.zero,
              elevation: 0,
              backgroundColor: Colors.white,
            ),
            child: const Icon(
              Icons.arrow_back_ios_new,
              color: Colors.black,
              size: 20,
            ),
          ),
        ),
      ),
      body: paniers.isEmpty
          ? Center(
              child: Image.asset('assets/images/empty_card.gif'),
            )
          : Container(
              width: double.infinity,
              height: heightOfScreen(context),
              decoration: const BoxDecoration(
                image: DecorationImage(
                  image: AssetImage("assets/images/bg.jpg"),
                  fit: BoxFit.cover,
                  opacity: 0.1,
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 5),
                child: ListView.builder(
                  itemCount: paniers.length,
                  itemBuilder: (context, index) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 10),
                    child: Dismissible(
                      key: Key(paniers[index].product.id.toString()),
                      direction: DismissDirection.endToStart,
                      onDismissed: (direction) {
                        setState(() {
                          paniers.removeAt(index);
                        });
                      },
                      background: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 20),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFE6E6),
                          borderRadius: BorderRadius.circular(15),
                        ),
                        child: Row(
                          children: [
                            const Spacer(),
                            SvgPicture.asset("assets/icons/Trash.svg"),
                          ],
                        ),
                      ),
                      child: CartCard(cart: paniers[index]),
                    ),
                  ),
                ),
              ),
            ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xff03dac6),
        foregroundColor: Colors.black,
        onPressed: () => _editProduitForm(),
        icon: const Icon(Icons.add),
        label: const Text('Ajouter produit'),
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
      bottomNavigationBar: CheckoutCard(niveau: 1),
    );
  }

  _refresh(){
    setState(() {
      articleController.text = '';
      qteController.text = '';
      uniteController.text = '';
      _unite = 0;
    });
  }

  _editProduitForm() async {
    _refresh();
    return showDialog(
        barrierDismissible: true,
        context: context,
        builder: (BuildContext context) {
          return AlertDialog(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(30),
            ),
            elevation: 5.0,
            content: SizedBox(
              height: 300,
              child: Column(
                children: [
                  TextFormField(
                    controller: articleController,
                    keyboardType: TextInputType.text,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: "Produit ou article",
                      hintText: "Article à livrer",
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: qteController,
                    keyboardType: TextInputType.number,
                    textInputAction: TextInputAction.next,
                    textCapitalization: TextCapitalization.characters,
                    decoration: const InputDecoration(
                      labelText: "Quantité totale",
                      hintText: "Quantité à livrer",
                    ),
                  ),
                  const SizedBox(height: 10),
                  DropDownTextField(
                    textEditingController: uniteController,
                    title: 'Unité de mesure',
                    hint: 'Choisir l\'unité de mesure',
                    options: { for (var p in _listUnite) p.id ?? 0 : p.libelle.toString() },
                    multiple: false,
                    textInputAction: TextInputAction.next,
                    onChanged: (selectedIds) {
                      setState((){
                        _unite = selectedIds?.first ?? 0;
                      });
                    },
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () {
                      setState(() {
                        paniers.add(Cart(
                          type: 2,
                          numOfItem: double.parse(qteController.text.trim()),
                          product: Produits(
                              prixMoyen: 0,
                              prixReduction: 0,
                              unite_id: _unite,
                              unite: uniteController.text,
                              nom: articleController.text.trim()),
                        ));
                      });
                      Get.back();
                    },
                    child: const Text("Ajouter"),
                  ),
                ],
              ),
            ),
          );
        });
  }
}
