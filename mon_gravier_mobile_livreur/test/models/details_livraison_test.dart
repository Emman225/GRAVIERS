import 'package:flutter_test/flutter_test.dart';
import 'package:mon_gravier_com_livreur/models/details_livraison.dart';

void main() {
  group('DetailsLivraison', () {
    test('fromJson() full response parsing', () {
      final json = {
        'code': 200,
        'message': 'Success',
        'data': {
          'ligneCommande': {
            'id': 1,
            'produit_id': 10,
            'commande_id': 100,
            'qte': 5,
            'prix': 5000,
            'etat_livraison': 'EN ATTENTE',
            'statut': 1,
            'deleted_at': null,
            'created_at': '2025-01-15',
            'updated_at': '2025-01-15',
            'qte_livree': null,
            'reference': 'REF001',
            'nom': 'Gravier fin',
            'unite': 'Tonne',
            'description': 'Gravier fin 0/4',
            'prix_moyen': 5000,
            'prix_reduction': 0,
            'image': 'gravier.jpg',
          },
          'ligneLivraison': {
            'id': 50,
            'nom_produit': 'Gravier fin',
            'qte': 5,
            'unite': 'Tonne',
            'description': 'Livraison standard',
            'poids_vehicule_souhaite': 10,
            'nombre_voyage': 2,
            'demande_livraison_id': 30,
            'etat_livraison': 'EN TRAITEMENT',
            'statut': 1,
            'deleted_at': null,
            'created_at': '2025-01-16',
            'updated_at': '2025-01-16',
            'unite_produit_id': 1,
            'numero': 'LIV001',
            'libelle': 'Livraison Gravier',
            'client_id': 20,
            'affichage_pec': 'Carriere A',
            'complement_adresse_pec': 'Zone industrielle',
            'longitude_pec': '-5.3599',
            'latitude_pec': '5.3364',
            'affichage_dest': 'Chantier B',
            'complement_adresse_dest': 'Plateau',
            'longitude_dest': '-3.9962',
            'latitude_dest': '5.3484',
            'montantTotal': 50000,
            'etat_commande': 'EN TRAITEMENT',
            'date_livraison': '2025-01-20',
            'date_fin_livraison': null,
            'remise': 0,
            'mode_paiement': 'Especes',
            'type_livraison': 'Standard',
          },
          'vehicules': [],
        },
      };

      final details = DetailsLivraison.fromJson(json);

      expect(details.code, 200);
      expect(details.message, 'Success');
      expect(details.data, isNotNull);
      expect(details.data!.ligneCommande, isNotNull);
      expect(details.data!.ligneCommande!.nom, 'Gravier fin');
      expect(details.data!.ligneLivraison, isNotNull);
      expect(details.data!.ligneLivraison!.numero, 'LIV001');
      expect(details.data!.vehicules, isNotNull);
    });

    test('fromJson() with null data', () {
      final json = {
        'code': 404,
        'message': 'Not found',
        'data': null,
      };

      final details = DetailsLivraison.fromJson(json);

      expect(details.code, 404);
      expect(details.data, isNull);
    });

    test('toJson() serializes correctly', () {
      final details = DetailsLivraison(
        code: 200,
        message: 'OK',
      );

      final json = details.toJson();

      expect(json['code'], 200);
      expect(json['message'], 'OK');
    });
  });

  group('LigneCommande', () {
    test('fromJson() with valid qte and prix parses correctly', () {
      final json = {
        'id': 1,
        'produit_id': 10,
        'commande_id': 100,
        'qte': 7,
        'prix': 8500,
        'etat_livraison': 'EN ATTENTE',
        'statut': 1,
        'deleted_at': null,
        'created_at': '2025-01-15',
        'updated_at': '2025-01-15',
        'qte_livree': null,
        'reference': 'REF002',
        'nom': 'Sable',
        'unite': 'M3',
        'description': 'Sable fin',
        'prix_moyen': 8500,
        'prix_reduction': 500,
        'image': 'sable.jpg',
      };

      final ligne = LigneCommande.fromJson(json);

      expect(ligne.id, 1);
      expect(ligne.produitId, 10);
      expect(ligne.commandeId, 100);
      expect(ligne.qte, 7.0);
      expect(ligne.prix, 8500.0);
      expect(ligne.etatLivraison, 'EN ATTENTE');
      expect(ligne.reference, 'REF002');
      expect(ligne.nom, 'Sable');
      expect(ligne.unite, 'M3');
      expect(ligne.prixMoyen, 8500);
      expect(ligne.prixReduction, 500);
      expect(ligne.image, 'sable.jpg');
    });

    test('fromJson() with null qte and prix defaults to 0 (bug fix)', () {
      final json = {
        'id': 2,
        'produit_id': 11,
        'commande_id': 101,
        'qte': null,
        'prix': null,
        'etat_livraison': 'EN ATTENTE',
        'statut': 1,
        'deleted_at': null,
        'created_at': '2025-02-01',
        'updated_at': '2025-02-01',
        'qte_livree': null,
        'reference': 'REF003',
        'nom': 'Gravier',
        'unite': 'Tonne',
        'description': '',
        'prix_moyen': null,
        'prix_reduction': null,
        'image': null,
      };

      final ligne = LigneCommande.fromJson(json);

      // The null check fix: (json['qte'] ?? 0) and (json['prix'] ?? 0)
      // prevent null.toString() from being passed to double.parse
      expect(ligne.qte, 0.0);
      expect(ligne.prix, 0.0);
    });

    test('fromJson() with string qte and prix parses correctly', () {
      final json = {
        'id': 3,
        'produit_id': 12,
        'commande_id': 102,
        'qte': '3.5',
        'prix': '12000.50',
        'etat_livraison': 'EN COURS LIVRAISON',
        'statut': 1,
        'deleted_at': null,
        'created_at': '2025-03-01',
        'updated_at': '2025-03-01',
        'qte_livree': null,
        'reference': 'REF004',
        'nom': 'Laterite',
        'unite': 'Tonne',
        'description': 'Laterite rouge',
        'prix_moyen': 12000,
        'prix_reduction': 0,
        'image': 'laterite.jpg',
      };

      final ligne = LigneCommande.fromJson(json);

      expect(ligne.qte, 3.5);
      expect(ligne.prix, 12000.50);
    });

    test('toJson() serializes correctly', () {
      final ligne = LigneCommande(
        id: 1,
        produitId: 10,
        commandeId: 100,
        qte: 5.0,
        prix: 7500.0,
        nom: 'Test',
        unite: 'Tonne',
      );

      final json = ligne.toJson();

      expect(json['id'], 1);
      expect(json['produit_id'], 10);
      expect(json['commande_id'], 100);
      expect(json['qte'], 5.0);
      expect(json['prix'], 7500.0);
      expect(json['nom'], 'Test');
      expect(json['unite'], 'Tonne');
    });
  });

  group('LigneLivraison', () {
    test('fromJson() parses all fields correctly', () {
      final json = {
        'id': 50,
        'nom_produit': 'Gravier moyen',
        'qte': 10,
        'unite': 'Tonne',
        'description': 'Livraison express',
        'poids_vehicule_souhaite': 15,
        'nombre_voyage': 3,
        'demande_livraison_id': 25,
        'etat_livraison': 'EN COURS LIVRAISON',
        'statut': 1,
        'deleted_at': null,
        'created_at': '2025-04-01',
        'updated_at': '2025-04-05',
        'unite_produit_id': 2,
        'numero': 'LIV050',
        'libelle': 'Livraison gravier moyen',
        'client_id': 30,
        'affichage_pec': 'Depot Central',
        'complement_adresse_pec': 'Zone portuaire',
        'longitude_pec': '-4.0283',
        'latitude_pec': '5.3097',
        'affichage_dest': 'Chantier Cocody',
        'complement_adresse_dest': 'Riviera 3',
        'longitude_dest': '-3.9568',
        'latitude_dest': '5.3647',
        'montantTotal': 150000,
        'etat_commande': 'EN TRAITEMENT',
        'date_livraison': '2025-04-10',
        'date_fin_livraison': '2025-04-12',
        'remise': 5000,
        'mode_paiement': 'Mobile Money',
        'type_livraison': 'Express',
      };

      final ligne = LigneLivraison.fromJson(json);

      expect(ligne.id, 50);
      expect(ligne.nomProduit, 'Gravier moyen');
      expect(ligne.qte, 10);
      expect(ligne.unite, 'Tonne');
      expect(ligne.description, 'Livraison express');
      expect(ligne.poidsVehiculeSouhaite, 15);
      expect(ligne.nombreVoyage, 3);
      expect(ligne.demandeLivraisonId, 25);
      expect(ligne.etatLivraison, 'EN COURS LIVRAISON');
      expect(ligne.statut, 1);
      expect(ligne.uniteProduitId, 2);
      expect(ligne.numero, 'LIV050');
      expect(ligne.libelle, 'Livraison gravier moyen');
      expect(ligne.clientId, 30);
      expect(ligne.affichagePec, 'Depot Central');
      expect(ligne.complementAdressePec, 'Zone portuaire');
      expect(ligne.longitudePec, '-4.0283');
      expect(ligne.latitudePec, '5.3097');
      expect(ligne.affichageDest, 'Chantier Cocody');
      expect(ligne.complementAdresseDest, 'Riviera 3');
      expect(ligne.longitudeDest, '-3.9568');
      expect(ligne.latitudeDest, '5.3647');
      expect(ligne.montantTotal, 150000);
      expect(ligne.etatCommande, 'EN TRAITEMENT');
      expect(ligne.dateLivraison, '2025-04-10');
      expect(ligne.dateFinLivraison, '2025-04-12');
      expect(ligne.remise, 5000);
      expect(ligne.modePaiement, 'Mobile Money');
      expect(ligne.typeLivraison, 'Express');
    });

    test('toJson() serializes all fields correctly', () {
      final ligne = LigneLivraison(
        id: 1,
        nomProduit: 'Sable',
        qte: 5,
        unite: 'M3',
        numero: 'LIV001',
        clientId: 10,
        affichagePec: 'Point A',
        affichageDest: 'Point B',
        montantTotal: 50000,
        modePaiement: 'Especes',
        typeLivraison: 'Standard',
      );

      final json = ligne.toJson();

      expect(json['id'], 1);
      expect(json['nom_produit'], 'Sable');
      expect(json['qte'], 5);
      expect(json['unite'], 'M3');
      expect(json['numero'], 'LIV001');
      expect(json['client_id'], 10);
      expect(json['affichage_pec'], 'Point A');
      expect(json['affichage_dest'], 'Point B');
      expect(json['montantTotal'], 50000);
      expect(json['mode_paiement'], 'Especes');
      expect(json['type_livraison'], 'Standard');
    });
  });
}
