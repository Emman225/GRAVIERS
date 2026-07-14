# Rapport Fonctionnel Du Projet

Périmètre analysé depuis le code Laravel (routes, contrôleurs, modèles, vues, migrations).

Références principales: `routes/web.php`, `app/Http/Controllers/ClientController.php`, `app/Http/Controllers/UserController.php`, `app/Help.php`, `database/migrations`.

## 1) Vue d'ensemble

- Application e-commerce orientée matériaux/BTP avec 2 métiers: `VENTE` et `LOCATION`.
- Gestion complète de cycle: catalogue -> panier/devis/commande/location -> livraison/enlèvement -> facturation/paiement -> SAV/retour.
- 395 routes déclarées (web+api+debug).

## 2) Rôles et accès

- Rôles métier: `Admin`, `Gestionnaire`, `Client`, `Fournisseur`, `Apporteur`, `Livreur`, `Agent SAV`, `SA`.
- Middleware custom pour redirection selon rôle et blocage des sessions multi-profils.
- Connexions séparées par profil: client, fournisseur, livreur, apporteur, backoffice.

## 3) Fonctionnalités Client

- Parcours public: accueil, catégories, fiches produits, recherche.
- Panier dynamique: ajout/suppression, incrément/décrément, mise à jour quantité.
- Wishlist / likes produits.
- Devis: création, modification, annulation de modification, conversion devis -> commande.
- Commandes: création, récapitulatifs, validation, suivi, détail.
- Locations: sélection dates, calcul nombre de jours, panier location, validation.
- Adresse/livraison: saisie adresse, demande de livraison, suivi/validation/récupération.
- Paiement: paiement direct, paiement de factures, référence bancaire, vérification de paiement.
- Promotions/fidélité: code promo + réduction par points.
- Documents: factures PDF, téléchargement facture, état de commande, exports.
- Compte client: profil, historique devis/commandes/paiements/livraisons, suppression compte.
- SAV: ticket SAV, retours produit (motif), demande annulation commande.
- Contenu: blog + commentaires + notation.

## 4) Fonctionnalités Admin / Gestionnaire

- Pilotage global: tableaux de bord, CA par famille, CA détaillé, balance âgée.
- Gestion comptes: gestionnaires, agents, clients comptants, clients à terme, blocage compte.
- Gestion fournisseurs/livreurs/apporteurs: création, édition, profil, commissions.
- Validation demandes de paiement (livreur/fournisseur/apporteur) + historique.
- Gestion client à terme: demandes, validation/refus.
- Gestion logistique:
- demandes de livraison (liste, détail, traitement),
- affectation livreur/véhicule,
- suivi livraison en attente/en cours/validée/historique,
- ajustement coût livraison livreur.
- Commandes backoffice:
- liste commandes/devis,
- traitement par item,
- traitement sans livraison,
- bons d'enlèvement,
- génération facture,
- application réduction.
- Gestion SAV et retours:
- tickets SAV (assignation agent),
- retours produits (validation/refus/remboursement logique).
- Paramétrage référentiels:
- pays, villes, régions,
- types/options de configuration.
- Marketing/éditorial:
- bannières (création/modif/publication/suppression),
- blog (CRUD + modération commentaires),
- modération commentaires clients.
- Codes promo: création, mise à jour, suppression.

## 5) Fonctionnalités Fournisseur

- Espace fournisseur: login, dashboard, profil, paramètres.
- Gestion stock et produits fournisseur.
- Bons d'enlèvement: liste, détail, impression, validation, états accepté/refusé.
- Vue livraisons liées.
- Demandes de paiement et historique.
- Alertes fin de stock.

## 6) Fonctionnalités Livreur

- Authentification et tableau de bord livreur.
- Bons d'enlèvement: réception, détail, validation, impression, recherche, statut.
- Livraisons: en cours, validées, marquage "mis en route".
- Validation livraison par code.
- Gestion véhicules: ajout/modif/suppression, disponibilité (`vehiculeDispo`), liste.
- Paramètres compte livreur.
- Demandes de paiement + historique.

## 7) Fonctionnalités Apporteur d'affaires

- Inscription/connexion.
- Confirmation par token/code.
- Profil, paramètres.
- Vue filleuls.
- Solde, commissions, paiements.
- Mécanisme de pourcentage/commission côté backoffice.

## 8) Paiement, facturation, intégrations

- Paiement standard + paiement location + paiement factures client à terme.
- Callback API de paiement en ligne (`/api/callBackPaiement`) pour marquer lignes payées/annulées.
- Génération et suivi des lignes de paiement.
- Commission apporteur créditée après paiement validé.
- E-mails transactionnels disponibles (commande, paiement, location, confirmation, codes).
- Exports Excel et génération PDF actifs.

## 9) Données métier (schéma couvert)

- Entités principales: utilisateurs, clients, fournisseurs, livreurs, apporteurs, produits, catégories, stocks, devis, commandes, détails commandes, livraisons, enlèvements, paiements, lignes paiements, factures, retours, tickets SAV, blogs/commentaires, réductions, véhicules, régions/villes/pays.
- Migrations nombreuses (2023->2025) couvrant vente, location, fidélité, commissions, SAV, logs, preuves opération bancaire.

## 10) Volumétrie technique

- Routes totales: `395`.
- Répartition routes (principaux contrôleurs):
- `UserController` 132
- `ClientController` 103
- `LivreurController` 26
- `SellerController` 24
- `OrdersController` 20
- `ApporteurController` 14
- `ProductsController` 12

## 11) Observations importantes (audit)

- Route sensible publique `storage-link` (création lien stockage) exposée.
- Secrets visibles en code/config (mail sortant, clés API paiement).
- Présence de fichiers potentiellement dangereux dans stockage public temporaire (extensions `.exe`, `.dll`, `.sql`), suggérant un contrôle d'upload à renforcer.
- Projet très riche fonctionnellement mais fortement centralisé dans `UserController` et `ClientController` (gros volume logique).
