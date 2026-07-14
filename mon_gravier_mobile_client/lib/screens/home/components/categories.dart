import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../models/ConfigModel.dart';
import '../../products/products_categorie_screen.dart';

class CategoriesArticle extends StatelessWidget {
  final List<Categories> categories;
  const CategoriesArticle({super.key, required this.categories});

  // @override
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: List.generate(
          categories.length,
          (index) => CategoryCard(
            icon: categories[index].image.toString(),
            text: categories[index].nom.toString(),
            press: () => Get.toNamed(ProductsCategorieScreen.routeName, arguments: [categories[index].id, categories[index].nom]),
          ),
        ),
      ),
    );
  }
}

class CategoryCard extends StatelessWidget {
  const CategoryCard({
    super.key,
    required this.icon,
    required this.text,
    required this.press,
  });

  final String icon, text;
  final GestureTapCallback press;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: press,
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            height: 56,
            width: 56,
            decoration: BoxDecoration(
              color: const Color(0xFFFFECDF),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Image.network(
              icon,
              fit: BoxFit.cover,
            ),
          ),
          const SizedBox(height: 4),
          Text(text, textAlign: TextAlign.center, style: const TextStyle(fontSize: 12))
        ],
      ),
    );
  }
}
