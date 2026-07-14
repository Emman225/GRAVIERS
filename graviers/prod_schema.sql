
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admins_user_id_foreign` (`user_id`),
  CONSTRAINT `admins_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adresse_livraison`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adresse_livraison` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `pays_id` bigint unsigned NOT NULL,
  `ville_id` bigint unsigned NOT NULL,
  `affichage` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `complement_adresse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `defaut` tinyint(1) NOT NULL DEFAULT '0',
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `adresse_livraison_client_id_foreign` (`client_id`),
  KEY `adresse_livraison_pays_id_foreign` (`pays_id`),
  KEY `adresse_livraison_ville_id_foreign` (`ville_id`),
  CONSTRAINT `adresse_livraison_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `adresse_livraison_pays_id_foreign` FOREIGN KEY (`pays_id`) REFERENCES `pays` (`id`),
  CONSTRAINT `adresse_livraison_ville_id_foreign` FOREIGN KEY (`ville_id`) REFERENCES `ville` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `agence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agence` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsable` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `agence_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `apporteur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `apporteur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `solde` double NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pourcentage` double(8,2) NOT NULL DEFAULT '1.00',
  `piece_recto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `piece_verso` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cni` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mode_paiement_prefere` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coordonnees_paiement` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_intervention` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_piece` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mode_paiement_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `apporteur_code_unique` (`code`),
  KEY `apporteur_user_id_foreign` (`user_id`),
  KEY `apporteur_mode_paiement_id_foreign` (`mode_paiement_id`),
  CONSTRAINT `apporteur_mode_paiement_id_foreign` FOREIGN KEY (`mode_paiement_id`) REFERENCES `mode_paiement` (`id`) ON DELETE SET NULL,
  CONSTRAINT `apporteur_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banniere`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banniere` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `titre` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sous_titre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `num_ordre` smallint unsigned DEFAULT '0',
  `type_banniere` enum('TOP','FLASH','BOTTOM') COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_heure_decompte` datetime DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bl_client`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bl_client` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `fichier` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` double NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `commande_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `bl_client_client_id_foreign` (`client_id`),
  KEY `bl_client_numero_index` (`numero`),
  KEY `bl_client_commande_id_foreign` (`commande_id`),
  CONSTRAINT `bl_client_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `bl_client_commande_id_foreign` FOREIGN KEY (`commande_id`) REFERENCES `commande` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_commentaires`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_commentaires` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `note` int DEFAULT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `client_id` bigint unsigned NOT NULL,
  `blog_id` bigint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `blog_commentaires_client_id_foreign` (`client_id`),
  KEY `blog_commentaires_blog_id_foreign` (`blog_id`),
  CONSTRAINT `blog_commentaires_blog_id_foreign` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`),
  CONSTRAINT `blog_commentaires_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blogs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vu` int DEFAULT NULL,
  `userVu` bigint unsigned NOT NULL,
  `user_publie` bigint unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `blogs_uservu_foreign` (`userVu`),
  KEY `blogs_user_publie_foreign` (`user_publie`),
  CONSTRAINT `blogs_user_publie_foreign` FOREIGN KEY (`user_publie`) REFERENCES `users` (`id`),
  CONSTRAINT `blogs_uservu_foreign` FOREIGN KEY (`userVu`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categorie`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorie` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` bigint unsigned NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categorie_produit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorie_produit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `categorie_id` bigint unsigned NOT NULL,
  `produit_id` bigint unsigned NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `categorie_produit_categorie_id_foreign` (`categorie_id`),
  KEY `categorie_produit_produit_id_foreign` (`produit_id`),
  CONSTRAINT `categorie_produit_categorie_id_foreign` FOREIGN KEY (`categorie_id`) REFERENCES `categorie` (`id`),
  CONSTRAINT `categorie_produit_produit_id_foreign` FOREIGN KEY (`produit_id`) REFERENCES `produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact1` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code_parrain` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rccm_clt` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ncc_clt` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regime_imposition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dfe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registre_commerce` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parrain_id` bigint unsigned DEFAULT '0',
  `type_client` enum('PARTICULIER','ENTREPRISE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `applique_tva` tinyint(1) NOT NULL DEFAULT '1',
  `client_a_terme` tinyint(1) NOT NULL DEFAULT '0',
  `point` double(8,2) NOT NULL DEFAULT '1.00',
  `plafond_credit` bigint NOT NULL DEFAULT '0',
  `delai_paiement` int NOT NULL DEFAULT '30',
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_email_unique` (`email`),
  KEY `client_user_id_foreign` (`user_id`),
  KEY `client_code_parrain_index` (`code_parrain`),
  KEY `client_parrain_id_index` (`parrain_id`),
  CONSTRAINT `client_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `client_comptes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `client_comptes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `compte_id` bigint unsigned NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_comptes_client_id_foreign` (`client_id`),
  KEY `client_comptes_compte_id_foreign` (`compte_id`),
  CONSTRAINT `client_comptes_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `client_comptes_compte_id_foreign` FOREIGN KEY (`compte_id`) REFERENCES `comptes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `code_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `code_resets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT '0',
  `type_code` smallint unsigned DEFAULT '0',
  `expiration_date` datetime DEFAULT '2026-04-13 00:00:00',
  `utilise` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commande`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commande` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `devis_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `mode_paiement_id` bigint unsigned DEFAULT NULL,
  `adresse_livraison_id` bigint unsigned DEFAULT NULL,
  `agence_id` bigint unsigned DEFAULT NULL,
  `date_commande` datetime NOT NULL DEFAULT '2026-04-13 12:32:21',
  `date_limite_paiement` date DEFAULT NULL,
  `montant_total` double NOT NULL DEFAULT '0',
  `etat_commande` enum('EN ATTENTE','EN TRAITEMENT','TERMINEE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut_comptant` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_livraison` datetime NOT NULL DEFAULT '2026-04-13 12:32:21',
  `date_fin_livraison` datetime NOT NULL DEFAULT '2026-04-13 12:32:21',
  `statut` smallint NOT NULL DEFAULT '1',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `remise` double NOT NULL DEFAULT '0',
  `type_livraison_id` bigint unsigned DEFAULT NULL,
  `type_livraison` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cout_livraison_client` double DEFAULT '0',
  `est_livrable` tinyint(1) NOT NULL DEFAULT '0',
  `cout_reduction` double DEFAULT '0',
  `fichier_bl` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_bl` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commande_numero_unique` (`numero`),
  KEY `commande_devis_id_foreign` (`devis_id`),
  KEY `commande_client_id_foreign` (`client_id`),
  KEY `commande_mode_paiement_id_foreign` (`mode_paiement_id`),
  KEY `commande_adresse_livraison_id_foreign` (`adresse_livraison_id`),
  KEY `commande_etat_commande_index` (`etat_commande`),
  KEY `commande_type_livraison_id_foreign` (`type_livraison_id`),
  KEY `commande_agence_id_index` (`agence_id`),
  CONSTRAINT `commande_adresse_livraison_id_foreign` FOREIGN KEY (`adresse_livraison_id`) REFERENCES `adresse_livraison` (`id`),
  CONSTRAINT `commande_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `commande_devis_id_foreign` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`),
  CONSTRAINT `commande_mode_paiement_id_foreign` FOREIGN KEY (`mode_paiement_id`) REFERENCES `mode_paiement` (`id`),
  CONSTRAINT `commande_type_livraison_id_foreign` FOREIGN KEY (`type_livraison_id`) REFERENCES `type_livraison` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commission_apporteur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commission_apporteur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `apporteur_id` bigint unsigned NOT NULL,
  `commande_id` bigint unsigned NOT NULL,
  `montant` double NOT NULL DEFAULT '1',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_affaire` enum('LOCATION','VENTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_echeance` date DEFAULT NULL,
  `statut_commission` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `commission_apporteur_apporteur_id_foreign` (`apporteur_id`),
  KEY `commission_apporteur_commande_id_foreign` (`commande_id`),
  KEY `commission_apporteur_numero_index` (`numero`),
  CONSTRAINT `commission_apporteur_apporteur_id_foreign` FOREIGN KEY (`apporteur_id`) REFERENCES `apporteur` (`id`),
  CONSTRAINT `commission_apporteur_commande_id_foreign` FOREIGN KEY (`commande_id`) REFERENCES `commande` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `compte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compte` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `solde` double NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `compte_numero_unique` (`numero`),
  UNIQUE KEY `compte_libelle_unique` (`libelle`),
  UNIQUE KEY `compte_prefix_unique` (`prefix`),
  KEY `compte_user_id_foreign` (`user_id`),
  CONSTRAINT `compte_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comptes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comptes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefix` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `solde` double NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comptes_numero_unique` (`numero`),
  UNIQUE KEY `comptes_libelle_unique` (`libelle`),
  UNIQUE KEY `comptes_prefix_unique` (`prefix`),
  KEY `comptes_user_id_foreign` (`user_id`),
  CONSTRAINT `comptes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuration` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tva` double(8,2) NOT NULL DEFAULT '18.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `montant_point` double(8,2) NOT NULL,
  `devise` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_tresorier` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_directeur_marketing` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gestionnaire1_id` bigint unsigned DEFAULT NULL,
  `gestionnaire2_id` bigint unsigned DEFAULT NULL,
  `raison_sociale` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ncc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regime_imposition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `centre_impots` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rccm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_bancaires` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnps` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capital_social` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse_siege` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_entreprise` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_etablissement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_pdv` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nom du point de vente',
  `prixKm` double(8,2) DEFAULT '0.00',
  `cout_livraison_min` double(8,2) DEFAULT '0.00',
  `tonne_moyenne` double(8,2) DEFAULT '40.00',
  `cout_liv_fixe` double(8,2) DEFAULT '0.00',
  `delai_relance_standard` int NOT NULL DEFAULT '7',
  `seuil_alerte_retard` int NOT NULL DEFAULT '15',
  `delai_max_paiement_agence` int NOT NULL DEFAULT '3',
  `delai_annulation_auto` int NOT NULL DEFAULT '7',
  `frequence_paiement_livreur` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Hebdomadaire',
  `jour_paiement_livreur` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Vendredi',
  `taux_commission_standard` decimal(5,2) NOT NULL DEFAULT '3.00',
  `delai_paiement_commission` int NOT NULL DEFAULT '15',
  `termes_conditions` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `configuration_email_tresorier_index` (`email_tresorier`),
  KEY `configuration_email_directeur_marketing_index` (`email_directeur_marketing`),
  KEY `configuration_gestionnaire1_id_index` (`gestionnaire1_id`),
  KEY `configuration_gestionnaire2_id_index` (`gestionnaire2_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom_prenoms` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sujet` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '2',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cout_livraison`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cout_livraison` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `unite_produit_id` bigint unsigned NOT NULL,
  `distance_min_km` double NOT NULL DEFAULT '1',
  `distance_max_km` double NOT NULL DEFAULT '1',
  `prix_km` double NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `unite_min` double(8,2) NOT NULL DEFAULT '0.00',
  `unite_max` double(8,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `cout_livraison_unite_produit_id_foreign` (`unite_produit_id`),
  CONSTRAINT `cout_livraison_unite_produit_id_foreign` FOREIGN KEY (`unite_produit_id`) REFERENCES `unite_produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `demande_annulation_commande`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demande_annulation_commande` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `commande_id` bigint unsigned NOT NULL,
  `motif` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `est_traite` tinyint(1) NOT NULL DEFAULT '0',
  `note` mediumtext COLLATE utf8mb4_unicode_ci,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_affaire` enum('LOCATION','VENTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `demande_annulation_commande_client_id_foreign` (`client_id`),
  KEY `demande_annulation_commande_commande_id_foreign` (`commande_id`),
  CONSTRAINT `demande_annulation_commande_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `demande_annulation_commande_commande_id_foreign` FOREIGN KEY (`commande_id`) REFERENCES `commande` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `demande_compte_client_a_terme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demande_compte_client_a_terme` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `objet` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `documents_path` json DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `approuve` tinyint(1) NOT NULL DEFAULT '0',
  `plafond_credit` decimal(18,2) DEFAULT NULL,
  `delai_paiement` int DEFAULT NULL,
  `commentaire_admin` text COLLATE utf8mb4_unicode_ci,
  `motif_refus` text COLLATE utf8mb4_unicode_ci,
  `decided_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `demande_compte_client_a_terme_client_id_foreign` (`client_id`),
  KEY `demande_compte_client_a_terme_user_id_foreign` (`user_id`),
  CONSTRAINT `demande_compte_client_a_terme_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `demande_compte_client_a_terme_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `demande_livraison`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demande_livraison` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `client_id` bigint unsigned NOT NULL,
  `adresse_livraison_pec_id` bigint unsigned DEFAULT NULL,
  `adresse_livraison_dest_id` bigint unsigned DEFAULT NULL,
  `montantTotal` double NOT NULL DEFAULT '0',
  `etat_commande` enum('EN ATTENTE','EN TRAITEMENT','TERMINEE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_livraison` datetime NOT NULL DEFAULT '2026-04-13 12:32:24',
  `date_fin_livraison` datetime NOT NULL DEFAULT '2026-04-13 12:32:24',
  `remise` double NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `mode_paiement_id` bigint unsigned DEFAULT NULL,
  `type_livraison_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `demande_livraison_numero_unique` (`numero`),
  KEY `demande_livraison_client_id_foreign` (`client_id`),
  KEY `demande_livraison_adresse_livraison_pec_id_foreign` (`adresse_livraison_pec_id`),
  KEY `demande_livraison_adresse_livraison_dest_id_foreign` (`adresse_livraison_dest_id`),
  KEY `demande_livraison_etat_commande_index` (`etat_commande`),
  KEY `demande_livraison_mode_paiement_id_foreign` (`mode_paiement_id`),
  KEY `demande_livraison_type_livraison_id_foreign` (`type_livraison_id`),
  CONSTRAINT `demande_livraison_adresse_livraison_dest_id_foreign` FOREIGN KEY (`adresse_livraison_dest_id`) REFERENCES `adresse_livraison` (`id`),
  CONSTRAINT `demande_livraison_adresse_livraison_pec_id_foreign` FOREIGN KEY (`adresse_livraison_pec_id`) REFERENCES `adresse_livraison` (`id`),
  CONSTRAINT `demande_livraison_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `demande_livraison_mode_paiement_id_foreign` FOREIGN KEY (`mode_paiement_id`) REFERENCES `mode_paiement` (`id`),
  CONSTRAINT `demande_livraison_type_livraison_id_foreign` FOREIGN KEY (`type_livraison_id`) REFERENCES `type_livraison` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `demande_paiement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demande_paiement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `montant` double NOT NULL DEFAULT '0',
  `mode_paiement_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `user_valide_id` bigint unsigned DEFAULT NULL,
  `date_validation` datetime NOT NULL DEFAULT '2026-04-13 12:32:25',
  `paye` tinyint(1) NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_valide2_id` bigint unsigned DEFAULT NULL,
  `numero_compte` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `demande_paiement_mode_paiement_id_foreign` (`mode_paiement_id`),
  KEY `demande_paiement_user_id_foreign` (`user_id`),
  KEY `demande_paiement_user_valide_id_foreign` (`user_valide_id`),
  KEY `demande_paiement_user_valide2_id_index` (`user_valide2_id`),
  CONSTRAINT `demande_paiement_mode_paiement_id_foreign` FOREIGN KEY (`mode_paiement_id`) REFERENCES `mode_paiement` (`id`),
  CONSTRAINT `demande_paiement_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `demande_paiement_user_valide_id_foreign` FOREIGN KEY (`user_valide_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `demande_paiements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `demande_paiements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `montant` double NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `numero_compte` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `demande_paiements_numero_unique` (`numero`),
  KEY `demande_paiements_user_id_index` (`user_id`),
  KEY `demande_paiements_numero_compte_index` (`numero_compte`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_commande`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_commande` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produit_id` bigint unsigned NOT NULL,
  `commande_id` bigint unsigned NOT NULL,
  `qte` double(8,2) NOT NULL DEFAULT '0.00',
  `prix` double NOT NULL DEFAULT '0',
  `prix_fournisseur` double DEFAULT '0',
  `etat_livraison` enum('EN ATTENTE','EN TRAITEMENT','LIVREE','EN COURS LIVRAISON') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `qte_livree` int DEFAULT NULL,
  `reference` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cout_livraison` double DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `detail_commande_produit_id_foreign` (`produit_id`),
  KEY `detail_commande_commande_id_foreign` (`commande_id`),
  KEY `detail_commande_etat_livraison_index` (`etat_livraison`),
  CONSTRAINT `detail_commande_commande_id_foreign` FOREIGN KEY (`commande_id`) REFERENCES `commande` (`id`),
  CONSTRAINT `detail_commande_produit_id_foreign` FOREIGN KEY (`produit_id`) REFERENCES `produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_devis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_devis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produit_id` bigint unsigned NOT NULL,
  `devis_id` bigint unsigned NOT NULL,
  `qte` double(8,2) NOT NULL,
  `prix` double NOT NULL,
  `prix_fournisseur` double DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cout_livraison` double DEFAULT '0',
  `debut_location` date DEFAULT NULL,
  `fin_location` date DEFAULT NULL,
  `nbre_jour_location` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `detail_devis_produit_id_foreign` (`produit_id`),
  KEY `detail_devis_devis_id_foreign` (`devis_id`),
  CONSTRAINT `detail_devis_devis_id_foreign` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`),
  CONSTRAINT `detail_devis_produit_id_foreign` FOREIGN KEY (`produit_id`) REFERENCES `produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_livraison`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_livraison` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom_produit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qte` double(8,2) NOT NULL,
  `unite` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `demande_livraison_id` bigint unsigned NOT NULL,
  `etat_livraison` enum('EN ATTENTE','EN TRAITEMENT','LIVREE','EN COURS LIVRAISON') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `unite_produit_id` bigint unsigned NOT NULL,
  `poids_vehicule_souhaite` double DEFAULT '0',
  `nombre_voyage` int DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_livraison_demande_livraison_id_foreign` (`demande_livraison_id`),
  KEY `detail_livraison_etat_livraison_index` (`etat_livraison`),
  KEY `detail_livraison_unite_produit_id_foreign` (`unite_produit_id`),
  KEY `detail_livraison_user_id_index` (`user_id`),
  CONSTRAINT `detail_livraison_demande_livraison_id_foreign` FOREIGN KEY (`demande_livraison_id`) REFERENCES `demande_livraison` (`id`),
  CONSTRAINT `detail_livraison_unite_produit_id_foreign` FOREIGN KEY (`unite_produit_id`) REFERENCES `unite_produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_location` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produit_id` bigint unsigned NOT NULL,
  `location_id` bigint unsigned NOT NULL,
  `qte` double(8,2) NOT NULL DEFAULT '0.00',
  `debut` datetime NOT NULL,
  `fin` datetime NOT NULL,
  `prix` double NOT NULL DEFAULT '0',
  `etat_location` enum('EN ATTENTE','EN COURS','TERMINE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `nombre_jour` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `detail_location_produit_id_foreign` (`produit_id`),
  KEY `detail_location_location_id_foreign` (`location_id`),
  KEY `detail_location_etat_location_index` (`etat_location`),
  CONSTRAINT `detail_location_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `location` (`id`),
  CONSTRAINT `detail_location_produit_id_foreign` FOREIGN KEY (`produit_id`) REFERENCES `produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `devis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `devis` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `montant` double DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `libelle` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_livraison` date DEFAULT NULL,
  `adresse_livraison_id` bigint unsigned DEFAULT NULL,
  `type_livraison_id` bigint unsigned DEFAULT NULL,
  `mode_paiement_id` bigint unsigned DEFAULT NULL,
  `tva` double DEFAULT '0',
  `cout_reduction` double DEFAULT '0',
  `cout_livraison` double DEFAULT '0',
  `montant_ht` double DEFAULT '0',
  `service` smallint DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `devis_numero_unique` (`numero`),
  KEY `devis_client_id_foreign` (`client_id`),
  CONSTRAINT `devis_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `enlevement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enlevement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fournisseur_id` bigint unsigned NOT NULL,
  `livraison_id` bigint unsigned NOT NULL,
  `qte` double(8,2) NOT NULL,
  `produit_id` bigint unsigned NOT NULL,
  `livreur_id` bigint unsigned DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `code_enleve` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `livreur_validation` datetime DEFAULT NULL,
  `fournisseur_validation` datetime DEFAULT NULL,
  `vehicule_id` bigint unsigned DEFAULT NULL,
  `prix_fournisseur` double NOT NULL DEFAULT '0',
  `facture_id` bigint unsigned DEFAULT NULL,
  `qte_servi` int unsigned DEFAULT NULL,
  `gestionnaire_id` bigint unsigned DEFAULT NULL,
  `code_enlevement` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_echeance` date DEFAULT NULL,
  `statut_dette` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enlevement_code_enleve_unique` (`code_enleve`),
  KEY `enlevement_fournisseur_id_foreign` (`fournisseur_id`),
  KEY `enlevement_livraison_id_foreign` (`livraison_id`),
  KEY `enlevement_produit_id_foreign` (`produit_id`),
  KEY `enlevement_vehicule_id_index` (`vehicule_id`),
  KEY `enlevement_fature_id_index` (`facture_id`),
  CONSTRAINT `enlevement_fournisseur_id_foreign` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseur` (`id`),
  CONSTRAINT `enlevement_livraison_id_foreign` FOREIGN KEY (`livraison_id`) REFERENCES `livraison` (`id`),
  CONSTRAINT `enlevement_produit_id_foreign` FOREIGN KEY (`produit_id`) REFERENCES `produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `facture`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facture` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_fne` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fne_invoice_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fne_reference` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fne_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fne_balance_sticker` int DEFAULT NULL,
  `fne_warning` tinyint(1) NOT NULL DEFAULT '0',
  `fne_template` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fne_payment_method` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fne_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `fne_certified_at` timestamp NULL DEFAULT NULL,
  `fne_error_message` text COLLATE utf8mb4_unicode_ci,
  `fne_request_payload` json DEFAULT NULL,
  `fne_response_payload` json DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `date_echeance` date DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `statut_creance` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `montant` double NOT NULL DEFAULT '0',
  `service` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_id` bigint unsigned DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `facture_numero_unique` (`numero`),
  UNIQUE KEY `facture_numero_fne_unique` (`numero_fne`),
  KEY `facture_user_id_foreign` (`user_id`),
  CONSTRAINT `facture_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fournisseur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fournisseur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `nom_prenoms` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact1` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact2` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse_geo` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse_postale` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `solde` double NOT NULL DEFAULT '0',
  `contact` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_fournisseur` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `produit_principal` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delai_paiement` int NOT NULL DEFAULT '30',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `dfe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registre_commerce` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fournisseur_email_unique` (`email`),
  KEY `fournisseur_user_id_foreign` (`user_id`),
  KEY `fournisseur_code_index` (`code`),
  CONSTRAINT `fournisseur_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `historique_prix_livraison_livreur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historique_prix_livraison_livreur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `livreur_id` bigint unsigned NOT NULL,
  `ancien_prix` double NOT NULL DEFAULT '0',
  `nouveau_prix` double NOT NULL DEFAULT '0',
  `user_id` bigint unsigned DEFAULT NULL,
  `motif` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `historique_prix_livraison_livreur_user_id_foreign` (`user_id`),
  KEY `historique_prix_livraison_livreur_livreur_id_index` (`livreur_id`),
  CONSTRAINT `historique_prix_livraison_livreur_livreur_id_foreign` FOREIGN KEY (`livreur_id`) REFERENCES `livreur` (`id`) ON DELETE CASCADE,
  CONSTRAINT `historique_prix_livraison_livreur_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `image_produit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `image_produit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `produit_id` bigint unsigned NOT NULL,
  `defaut` tinyint(1) NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `image_produit_produit_id_foreign` (`produit_id`),
  CONSTRAINT `image_produit_produit_id_foreign` FOREIGN KEY (`produit_id`) REFERENCES `produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `interval_commission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `interval_commission` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `montant_de` double NOT NULL DEFAULT '1',
  `montant_a` double NOT NULL DEFAULT '1',
  `pourcentage` double(8,2) NOT NULL DEFAULT '1.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `interval_point`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `interval_point` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `montant_de` double NOT NULL DEFAULT '1',
  `montant_a` double NOT NULL DEFAULT '1',
  `nombre_point` double(8,2) NOT NULL DEFAULT '1.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ligne_paiement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ligne_paiement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `paiement_id` bigint unsigned NOT NULL,
  `mode_paiement_id` bigint unsigned NOT NULL,
  `reference` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moyen_paiement` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_paiement` datetime NOT NULL DEFAULT '2026-04-13 12:32:23',
  `montant` double NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `code_paiement` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_id` bigint unsigned DEFAULT NULL,
  `service` enum('COMMANDE','LOCATION','LIVRAISON') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ligne_paiement_paiement_id_foreign` (`paiement_id`),
  KEY `ligne_paiement_mode_paiement_id_foreign` (`mode_paiement_id`),
  KEY `ligne_paiement_reference_index` (`reference`),
  KEY `ligne_paiement_user_id_index` (`user_id`),
  KEY `ligne_paiement_code_paiement_index` (`code_paiement`),
  KEY `ligne_paiement_service_id_index` (`service_id`),
  CONSTRAINT `ligne_paiement_mode_paiement_id_foreign` FOREIGN KEY (`mode_paiement_id`) REFERENCES `mode_paiement` (`id`),
  CONSTRAINT `ligne_paiement_paiement_id_foreign` FOREIGN KEY (`paiement_id`) REFERENCES `paiement` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `likes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produit_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `likes_produit_id_foreign` (`produit_id`),
  KEY `likes_client_id_foreign` (`client_id`),
  CONSTRAINT `likes_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `likes_produit_id_foreign` FOREIGN KEY (`produit_id`) REFERENCES `produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `livraison`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `livraison` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `livreur_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `detail_commande_id` bigint unsigned NOT NULL DEFAULT '0',
  `detail_livraison_id` bigint unsigned NOT NULL DEFAULT '0',
  `adresse_livraison_id` bigint unsigned DEFAULT NULL,
  `cout_livraison` int unsigned NOT NULL DEFAULT '0',
  `date_livraison` date NOT NULL,
  `qte` double(8,2) NOT NULL,
  `note_livreur` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etat_livraison` enum('EN ATTENTE','EN TRAITEMENT','LIVREE','EN COURS LIVRAISON') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `provenance` enum('COMMANDE','LIVRAISON') COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicule_id` bigint unsigned DEFAULT NULL,
  `type_livraison_id` bigint unsigned DEFAULT NULL,
  `accepte` int DEFAULT '2',
  `date_accord` datetime DEFAULT NULL,
  `date_affectation_livreur` datetime DEFAULT NULL,
  `code_livraison` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gestionnaire_id` bigint unsigned DEFAULT NULL,
  `livre_par` smallint DEFAULT NULL,
  `distance_km` decimal(8,2) DEFAULT NULL,
  `forfait_base` int NOT NULL DEFAULT '0',
  `frais_km` int NOT NULL DEFAULT '0',
  `statut_paiement_livreur` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_paiement_livreur` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `livraison_numero_unique` (`numero`),
  KEY `livraison_livreur_id_foreign` (`livreur_id`),
  KEY `livraison_client_id_foreign` (`client_id`),
  KEY `livraison_adresse_livraison_id_foreign` (`adresse_livraison_id`),
  KEY `livraison_etat_livraison_index` (`etat_livraison`),
  KEY `livraison_vehicule_id_index` (`vehicule_id`),
  KEY `livraison_type_livraison_id_index` (`type_livraison_id`),
  CONSTRAINT `livraison_adresse_livraison_id_foreign` FOREIGN KEY (`adresse_livraison_id`) REFERENCES `adresse_livraison` (`id`),
  CONSTRAINT `livraison_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `livreur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `livreur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type_vehicule_id` bigint unsigned DEFAULT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `num_piece_identite` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `piece_recto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `piece_verso` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `solde` double NOT NULL DEFAULT '0',
  `cout_livraison` double DEFAULT '0',
  `mode_tarification` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'base',
  `longitude` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `derniere_position_at` timestamp NULL DEFAULT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT '1',
  `zone_intervention` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tarif_km` int NOT NULL DEFAULT '0',
  `tarif_forfait_base` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `livreur_user_id_foreign` (`user_id`),
  KEY `livreur_code_index` (`code`),
  KEY `livreur_type_vehicule_id_index` (`type_vehicule_id`),
  CONSTRAINT `livreur_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `livreur_bon_en_attente`;
/*!50001 DROP VIEW IF EXISTS `livreur_bon_en_attente`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `livreur_bon_en_attente` AS SELECT 
 1 AS `code_enleve`,
 1 AS `qte`,
 1 AS `fournisseur`,
 1 AS `frs_supprime`,
 1 AS `produit`,
 1 AS `nom_vehicule`,
 1 AS `marque`,
 1 AS `capacite`,
 1 AS `date_reception`,
 1 AS `date_prevu`,
 1 AS `qui_livre`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `location` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `mode_paiement_id` bigint unsigned DEFAULT NULL,
  `adresse_livraison_id` bigint unsigned DEFAULT NULL,
  `date_location` datetime NOT NULL DEFAULT '2026-04-13 12:32:30',
  `montant_total` double NOT NULL DEFAULT '0',
  `etat_location` enum('EN ATTENTE','EN COURS','TERMINE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remise` double NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `location_numero_unique` (`numero`),
  KEY `location_client_id_foreign` (`client_id`),
  KEY `location_mode_paiement_id_foreign` (`mode_paiement_id`),
  KEY `location_adresse_livraison_id_foreign` (`adresse_livraison_id`),
  KEY `location_etat_location_index` (`etat_location`),
  CONSTRAINT `location_adresse_livraison_id_foreign` FOREIGN KEY (`adresse_livraison_id`) REFERENCES `adresse_livraison` (`id`),
  CONSTRAINT `location_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `location_mode_paiement_id_foreign` FOREIGN KEY (`mode_paiement_id`) REFERENCES `mode_paiement` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mode_paiement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mode_paiement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `en_ligne` tinyint(1) NOT NULL DEFAULT '0',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `note_produit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `note_produit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produit_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `avis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` smallint NOT NULL DEFAULT '5',
  `statut` smallint NOT NULL DEFAULT '2',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `note_produit_produit_id_foreign` (`produit_id`),
  KEY `note_produit_client_id_foreign` (`client_id`),
  CONSTRAINT `note_produit_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `note_produit_produit_id_foreign` FOREIGN KEY (`produit_id`) REFERENCES `produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifier_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifier_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `user_envoyeur_id` bigint unsigned NOT NULL,
  `message_lu` tinyint(1) NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifier_user_user_id_foreign` (`user_id`),
  KEY `notifier_user_user_envoyeur_id_foreign` (`user_envoyeur_id`),
  CONSTRAINT `notifier_user_user_envoyeur_id_foreign` FOREIGN KEY (`user_envoyeur_id`) REFERENCES `users` (`id`),
  CONSTRAINT `notifier_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paiement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paiement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `devis_id` bigint unsigned DEFAULT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant_total` double NOT NULL DEFAULT '0',
  `montant_restant` double NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `user_valide_id` bigint unsigned DEFAULT NULL,
  `user_valide2_id` bigint unsigned DEFAULT NULL,
  `date_validation_1` timestamp NULL DEFAULT NULL,
  `date_validation_2` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `service_id` bigint unsigned DEFAULT NULL,
  `service` enum('COMMANDE','LOCATION','LIVRAISON') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facture_id` bigint unsigned DEFAULT NULL,
  `agence_id` bigint unsigned DEFAULT NULL,
  `caissier_id` bigint unsigned DEFAULT NULL,
  `numero_recu` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paiement_client_id_foreign` (`client_id`),
  KEY `paiement_devis_id_foreign` (`devis_id`),
  KEY `paiement_code_index` (`code`),
  KEY `paiement_service_id_index` (`service_id`),
  KEY `paiement_facture_id_index` (`facture_id`),
  KEY `paiement_agence_id_index` (`agence_id`),
  KEY `paiement_caissier_id_index` (`caissier_id`),
  KEY `paiement_user_valide_id_index` (`user_valide_id`),
  KEY `paiement_user_valide2_id_index` (`user_valide2_id`),
  CONSTRAINT `paiement_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `paiement_devis_id_foreign` FOREIGN KEY (`devis_id`) REFERENCES `devis` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paiement_apporteur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paiement_apporteur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_paiement` date NOT NULL,
  `commission_id` bigint unsigned NOT NULL,
  `apporteur_id` bigint unsigned NOT NULL,
  `montant` bigint NOT NULL DEFAULT '0',
  `mode_paiement_id` bigint unsigned DEFAULT NULL,
  `reference` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `user_valide_id` bigint unsigned DEFAULT NULL,
  `user_valide2_id` bigint unsigned DEFAULT NULL,
  `date_validation_1` timestamp NULL DEFAULT NULL,
  `date_validation_2` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paiement_apporteur_commission_id_index` (`commission_id`),
  KEY `paiement_apporteur_apporteur_id_index` (`apporteur_id`),
  KEY `paiement_apporteur_user_valide_id_index` (`user_valide_id`),
  KEY `paiement_apporteur_user_valide2_id_index` (`user_valide2_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paiement_fournisseur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paiement_fournisseur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_paiement` date NOT NULL,
  `enlevement_id` bigint unsigned NOT NULL,
  `fournisseur_id` bigint unsigned NOT NULL,
  `montant` bigint NOT NULL DEFAULT '0',
  `mode_paiement_id` bigint unsigned DEFAULT NULL,
  `reference` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `user_valide_id` bigint unsigned DEFAULT NULL,
  `user_valide2_id` bigint unsigned DEFAULT NULL,
  `date_validation_1` timestamp NULL DEFAULT NULL,
  `date_validation_2` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paiement_fournisseur_enlevement_id_index` (`enlevement_id`),
  KEY `paiement_fournisseur_fournisseur_id_index` (`fournisseur_id`),
  KEY `paiement_fournisseur_user_valide_id_index` (`user_valide_id`),
  KEY `paiement_fournisseur_user_valide2_id_index` (`user_valide2_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paiement_livreur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paiement_livreur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_paiement` date NOT NULL,
  `livraison_id` bigint unsigned NOT NULL,
  `livreur_id` bigint unsigned NOT NULL,
  `montant` bigint NOT NULL DEFAULT '0',
  `mode_paiement_id` bigint unsigned DEFAULT NULL,
  `reference` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `user_valide_id` bigint unsigned DEFAULT NULL,
  `user_valide2_id` bigint unsigned DEFAULT NULL,
  `date_validation_1` timestamp NULL DEFAULT NULL,
  `date_validation_2` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paiement_livreur_livraison_id_index` (`livraison_id`),
  KEY `paiement_livreur_livreur_id_index` (`livreur_id`),
  KEY `paiement_livreur_user_valide_id_index` (`user_valide_id`),
  KEY `paiement_livreur_user_valide2_id_index` (`user_valide2_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `indicatif` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pays_nom_unique` (`nom`),
  UNIQUE KEY `pays_code_unique` (`code`),
  KEY `pays_indicatif_index` (`indicatif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `uuid` varchar(155) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL DEFAULT '0',
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `preuve_operation_banque`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `preuve_operation_banque` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `commande_id` bigint unsigned NOT NULL,
  `reference` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `num_compte` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `banque` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_operation` date NOT NULL,
  `note_supp` text COLLATE utf8mb4_unicode_ci,
  `fichier` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `est_valide` tinyint NOT NULL DEFAULT '0',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `preuve_operation_banque_client_id_foreign` (`client_id`),
  KEY `preuve_operation_banque_commande_id_foreign` (`commande_id`),
  KEY `preuve_operation_banque_reference_index` (`reference`),
  KEY `preuve_operation_banque_num_compte_index` (`num_compte`),
  CONSTRAINT `preuve_operation_banque_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `preuve_operation_banque_commande_id_foreign` FOREIGN KEY (`commande_id`) REFERENCES `commande` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `preuve_operations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `preuve_operations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prix_personnalises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prix_personnalises` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `produit_id` bigint unsigned NOT NULL,
  `prix` double NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prix_personnalises_client_id_produit_id_unique` (`client_id`,`produit_id`),
  KEY `prix_personnalises_client_id_index` (`client_id`),
  KEY `prix_personnalises_produit_id_index` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom` varchar(155) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abreviation` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unite` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `prix_moyen` double NOT NULL DEFAULT '0',
  `prix_reduction` double NOT NULL DEFAULT '0',
  `meilleur_note` smallint NOT NULL DEFAULT '5',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `unite_produit_id` bigint unsigned NOT NULL,
  `type_affaire` enum('LOCATION','VENTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `prix_fournisseur` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `produit_id_index` (`id`),
  KEY `produit_reference_index` (`reference`),
  KEY `produit_unite_produit_id_foreign` (`unite_produit_id`),
  CONSTRAINT `produit_unite_produit_id_foreign` FOREIGN KEY (`unite_produit_id`) REFERENCES `unite_produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projet` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sous_titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `date_debut` datetime NOT NULL DEFAULT '2026-04-13 12:32:25',
  `date_fin` datetime NOT NULL DEFAULT '2026-04-13 12:32:25',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reduction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reduction` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `debut` date NOT NULL,
  `fin` date NOT NULL,
  `est_utilise` tinyint(1) NOT NULL DEFAULT '0',
  `taux_reduction` smallint NOT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `devis_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reduction_client_id_foreign` (`client_id`),
  KEY `reduction_code_index` (`code`),
  CONSTRAINT `reduction_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reduction_appliquees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reduction_appliquees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commande_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `taux_reduction` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reduction_appliquees_commande_id_foreign` (`commande_id`),
  KEY `reduction_appliquees_user_id_foreign` (`user_id`),
  CONSTRAINT `reduction_appliquees_commande_id_foreign` FOREIGN KEY (`commande_id`) REFERENCES `commande` (`id`),
  CONSTRAINT `reduction_appliquees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `regions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `long` double(8,2) NOT NULL,
  `lat` double(8,2) NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `regions_nom_unique` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `relance_client_terme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `relance_client_terme` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date_relance` date NOT NULL,
  `facture_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `type_relance` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `niveau` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reponse_client` text COLLATE utf8mb4_unicode_ci,
  `action_suivante` text COLLATE utf8mb4_unicode_ci,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `relance_client_terme_client_id_index` (`client_id`),
  KEY `relance_client_terme_facture_id_index` (`facture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `retour_produit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `retour_produit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `motif` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `detail_commande_id` bigint unsigned NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `user_id` bigint unsigned NOT NULL,
  `user_paie_id` bigint unsigned NOT NULL,
  `observation_reception` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `rembourse` tinyint(1) NOT NULL DEFAULT '0',
  `date_retour` datetime NOT NULL DEFAULT '2026-04-13 12:32:26',
  `date_reception` datetime DEFAULT NULL,
  `date_rembourssement` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `retour_produit_client_id_foreign` (`client_id`),
  KEY `retour_produit_detail_commande_id_foreign` (`detail_commande_id`),
  KEY `retour_produit_user_id_foreign` (`user_id`),
  KEY `retour_produit_user_paie_id_foreign` (`user_paie_id`),
  CONSTRAINT `retour_produit_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `retour_produit_detail_commande_id_foreign` FOREIGN KEY (`detail_commande_id`) REFERENCES `detail_commande` (`id`),
  CONSTRAINT `retour_produit_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `retour_produit_user_paie_id_foreign` FOREIGN KEY (`user_paie_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `statut_metier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `statut_metier` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `domaine` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `badge_class` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bg-light text-dark',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordre` int unsigned NOT NULL DEFAULT '0',
  `statut` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `statut_metier_domaine_libelle_unique` (`domaine`,`libelle`),
  KEY `statut_metier_domaine_index` (`domaine`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_produit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_produit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fournisseur_id` bigint unsigned NOT NULL,
  `produit_id` bigint unsigned NOT NULL,
  `qte` double(8,2) DEFAULT NULL,
  `prix` double DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `seuil_alert` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `stock_produit_fournisseur_id_foreign` (`fournisseur_id`),
  KEY `stock_produit_produit_id_foreign` (`produit_id`),
  CONSTRAINT `stock_produit_fournisseur_id_foreign` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseur` (`id`),
  CONSTRAINT `stock_produit_produit_id_foreign` FOREIGN KEY (`produit_id`) REFERENCES `produit` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_sav`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_sav` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `detail_commande_id` bigint unsigned NOT NULL,
  `objet` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `est_traite` tinyint(1) NOT NULL DEFAULT '0',
  `solution_trouvee` mediumtext COLLATE utf8mb4_unicode_ci,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_sav_numero_unique` (`numero`),
  KEY `ticket_sav_client_id_foreign` (`client_id`),
  KEY `ticket_sav_user_id_foreign` (`user_id`),
  KEY `ticket_sav_detail_commande_id_foreign` (`detail_commande_id`),
  CONSTRAINT `ticket_sav_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `ticket_sav_detail_commande_id_foreign` FOREIGN KEY (`detail_commande_id`) REFERENCES `detail_commande` (`id`),
  CONSTRAINT `ticket_sav_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tva_commande`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tva_commande` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `commande_id` bigint unsigned NOT NULL,
  `montant` double NOT NULL DEFAULT '1',
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_affaire` enum('LOCATION','VENTE') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tva_commande_client_id_foreign` (`client_id`),
  KEY `tva_commande_commande_id_foreign` (`commande_id`),
  CONSTRAINT `tva_commande_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`),
  CONSTRAINT `tva_commande_commande_id_foreign` FOREIGN KEY (`commande_id`) REFERENCES `commande` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `type_livraison`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `type_livraison` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `type_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `type_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `type_vehicule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `type_vehicule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `libelle` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `type_vehicule_livreur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `type_vehicule_livreur` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `libelle` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacite_tonnes` decimal(8,2) DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `type_vehicule_livreur_libelle_unique` (`libelle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unite_produit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unite_produit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `abreviation` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unite_produit_abreviation_unique` (`abreviation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom_prenoms` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pays_id` bigint unsigned NOT NULL DEFAULT '0',
  `ville_id` bigint unsigned NOT NULL DEFAULT '0',
  `type_user_id` bigint unsigned NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_login_unique` (`login`),
  KEY `users_type_user_id_foreign` (`type_user_id`),
  CONSTRAINT `users_type_user_id_foreign` FOREIGN KEY (`type_user_id`) REFERENCES `type_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `immatriculation` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_vehicule_id` bigint unsigned NOT NULL,
  `livreur_id` bigint unsigned NOT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT '1',
  `capacite` double(8,2) NOT NULL,
  `marque` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modele` varchar(70) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicule_type_vehicule_id_foreign` (`type_vehicule_id`),
  KEY `vehicule_livreur_id_foreign` (`livreur_id`),
  KEY `vehicule_immatriculation_index` (`immatriculation`),
  CONSTRAINT `vehicule_livreur_id_foreign` FOREIGN KEY (`livreur_id`) REFERENCES `livreur` (`id`),
  CONSTRAINT `vehicule_type_vehicule_id_foreign` FOREIGN KEY (`type_vehicule_id`) REFERENCES `type_vehicule` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `matricule` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `poids` double(8,2) NOT NULL,
  `capacite` double(8,2) NOT NULL,
  `marque` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `livreur_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicules_livreur_id_foreign` (`livreur_id`),
  CONSTRAINT `vehicules_livreur_id_foreign` FOREIGN KEY (`livreur_id`) REFERENCES `livreur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verifiable` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ville`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ville` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pays_id` bigint unsigned NOT NULL,
  `region_id` bigint unsigned DEFAULT NULL,
  `statut` smallint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ville_pays_id_foreign` (`pays_id`),
  CONSTRAINT `ville_pays_id_foreign` FOREIGN KEY (`pays_id`) REFERENCES `pays` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `livreur_bon_en_attente`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `livreur_bon_en_attente` AS select `env`.`code_enleve` AS `code_enleve`,`env`.`qte` AS `qte`,`frs`.`nom_prenoms` AS `fournisseur`,`frs`.`deleted_at` AS `frs_supprime`,`prod`.`nom` AS `produit`,`vh`.`nom` AS `nom_vehicule`,`vh`.`marque` AS `marque`,`vh`.`capacite` AS `capacite`,`liv`.`created_at` AS `date_reception`,`liv`.`date_livraison` AS `date_prevu`,`liv`.`livre_par` AS `qui_livre` from ((((`enlevement` `env` join `livraison` `liv` on((`liv`.`id` = `env`.`livraison_id`))) join `fournisseur` `frs` on((`env`.`fournisseur_id` = `frs`.`id`))) join `vehicule` `vh` on((`liv`.`vehicule_id` = `vh`.`id`))) join `produit` `prod` on((`env`.`produit_id` = `prod`.`id`))) where ((`liv`.`livreur_id` = 1) and (`liv`.`livre_par` = 'LIVREUR') and (`liv`.`accepte` <> 3) and (`env`.`qte_servi` is null)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2013_07_09_081021_create_pays_table',1),(2,'2013_07_09_081028_create_villes_table',1),(3,'2013_07_12_114328_create_type_users_table',1),(4,'2014_10_12_000000_create_users_table',1),(5,'2019_12_14_000001_create_personal_access_tokens_table',1),(6,'2020_09_30_230137_create_type_livraisons_table',1),(7,'2020_10_16_161629_create_unite_produits_table',1),(8,'2023_07_09_081054_create_produits_table',1),(9,'2023_07_12_120555_create_categories_table',1),(10,'2023_07_12_122046_create_mode_paiements_table',1),(11,'2024_07_01_102639_create_clients_table',1),(12,'2024_07_08_121223_create_adresse_livraisons_table',1),(13,'2024_07_09_081008_create_categorie_produits_table',1),(14,'2024_07_09_081135_create_apporteurs_table',1),(15,'2024_07_09_081201_create_livreurs_table',1),(16,'2024_07_09_081225_create_fournisseurs_table',1),(17,'2024_07_09_081237_create_devis_table',1),(18,'2024_07_09_081504_create_commandes_table',1),(19,'2024_07_09_081512_create_livraisons_table',1),(20,'2024_07_09_081523_create_reductions_table',1),(21,'2024_07_09_081539_create_paiements_table',1),(22,'2024_07_09_081549_create_enlevements_table',1),(23,'2024_07_09_081608_create_stock_produits_table',1),(24,'2024_07_09_081622_create_detail_devis_table',1),(25,'2024_07_12_120939_create_image_produits_table',1),(26,'2024_07_12_122719_create_ligne_paiements_table',1),(27,'2024_07_12_125041_create_detail_commandes_table',1),(28,'2024_07_12_130222_create_bannieres_table',1),(29,'2024_07_12_130807_create_note_produits_table',1),(30,'2024_07_12_131057_create_contacts_table',1),(31,'2024_07_22_160938_create_verification_codes_table',1),(32,'2024_08_01_084109_create_code_resets_table',1),(33,'2024_08_14_103518_add_livreur_id_to_enelevement_table',1),(34,'2024_08_15_091027_add_columns_to_enlevement',1),(35,'2024_08_15_160208_add_solde_column_to_fournisseur',1),(36,'2024_08_20_152539_add_token_to_user_table',1),(37,'2024_08_23_095400_add_solde_to_livreur_table',1),(38,'2024_08_24_173655_add_livreur_validation_and_fournisseur_validation_to_enlevement_table',1),(39,'2024_09_02_115610_add_icon_column_to_categorie_table',1),(40,'2024_09_07_122303_create_likes_table',1),(41,'2024_09_11_094745_create_permission_tables',1),(42,'2024_09_11_112353_create_admins_table',1),(43,'2024_09_14_103106_add_qte_livree_column_to_detail_commande_table',1),(44,'2024_09_27_160816_create_demande_paiements_table',1),(45,'2024_09_30_113051_create_comptes_table',1),(46,'2024_09_30_113052_create_client_comptes_table',1),(47,'2024_09_30_222226_create_demande_compte_client_a_termes_table',1),(48,'2024_09_30_222810_create_demande_livraisons_table',1),(49,'2024_09_30_223258_create_detail_livraisons_table',1),(50,'2024_09_30_223929_create_demande_paiements_table',1),(51,'2024_09_30_224827_create_notifier_users_table',1),(52,'2024_09_30_225322_create_projets_table',1),(53,'2024_09_30_230515_add_type_livraison_to_commande',1),(54,'2024_09_30_230813_create_configurations_table',1),(55,'2024_09_30_232123_create_retour_produits_table',1),(56,'2024_09_30_233113_add_non_tva_to_client',1),(57,'2024_10_05_151615_add_type_livraison_id_to_livraison',1),(58,'2024_10_05_190330_add_client_a_terme_to_client',1),(59,'2024_10_10_093840_create_vehicules_table',1),(60,'2024_10_16_161646_create_cout_livraisons_table',1),(61,'2024_10_16_162129_add_type_livraison_to_demande_livraison',1),(62,'2024_10_16_162516_add_unite_produit_id_to_produit',1),(63,'2024_10_16_164848_add_unite_produit_id_to_detail_livraison',1),(64,'2024_10_17_110154_add_unite_min_and_unite_max_to_cout_livraison',1),(65,'2024_10_17_132640_remove_poids_vehicule_and_nombre_voyage_to_detail_livraison',1),(66,'2024_10_19_132259_add_type_code_to_code_resets',1),(67,'2024_10_29_073558_create_type_vehicules_table',1),(68,'2024_10_29_083135_remove_adresse_livraison_id_to_devis',1),(69,'2024_10_29_083533_create_vehicules_table',1),(70,'2024_10_29_084944_add_vehicule_id_to_livraison',1),(71,'2024_10_29_085316_add_vehicule_id_to_enlevement',1),(72,'2024_10_29_090446_add_disponible_to_vehicule',1),(73,'2024_11_07_202714_add_capacite_to_vehicule',1),(74,'2024_11_09_145519_add_montant_point_to_configuration',1),(75,'2024_11_09_150055_remove_devis_id_to_reduction',1),(76,'2024_11_09_151309_add_type_affaire_to_produit',1),(77,'2024_11_09_151801_create_locations_table',1),(78,'2024_11_09_151806_create_detail_locations_table',1),(79,'2024_11_09_153328_create_ticket_s_a_v_s_table',1),(80,'2024_11_09_154502_create_interval_commissions_table',1),(81,'2024_11_09_154541_create_commission_apporteurs_table',1),(82,'2024_11_09_155049_create_tva_commandes_table',1),(83,'2024_11_11_085528_add_pourcentage_to_apporteur',1),(84,'2024_11_26_120238_create_blogs_table',1),(85,'2024_11_27_150843_create_blog_commentaires_table',1),(86,'2024_12_03_104236_create_demande_annulation_commandes_table',1),(87,'2024_12_04_105535_create_interval_points_table',1),(88,'2024_12_04_105702_add_point_to_client',1),(89,'2024_12_05_161554_add_type_affaire_to_tva_commande',1),(90,'2024_12_05_163639_add_nombre_jour_to_detail_location',1),(91,'2024_12_05_165519_add_type_affaire_to_commission_apporteur',1),(92,'2024_12_05_165542_add_type_affaire_to_demande_annulation_commande',1),(93,'2024_12_08_075933_remove_type_livraison_id_to_livraison',1),(94,'2024_12_08_102956_add_type_livraison_id_to_livraison_and_make_nullable',1),(95,'2025_01_04_095710_create_reduction_appliquees_table',1),(96,'2025_01_27_195534_add_user_id_to_detail_livraison',1),(97,'2025_01_27_201037_add_user_id_to_ligne_paiement',1),(98,'2025_02_03_080130_add_prix_fournisseur_to_produit',1),(99,'2025_02_03_080245_add_prix_fournisseur_to_enlevement',1),(100,'2025_02_03_080554_create_logs_table',1),(101,'2025_02_03_094936_add_code_paiement_to_ligne_paiement',1),(102,'2025_02_07_101448_create_facture_table',1),(103,'2025_02_07_101449_add_facture_id_to_enlevement_table',1),(104,'2025_02_07_185225_add_commande_id_to_facture',1),(105,'2025_02_12_133930_add_seuil_alert_to_stock_produit',1),(106,'2025_02_12_134334_create_bl_clients_table',1),(107,'2025_02_12_134624_add_accepte_to_livraison',1),(108,'2025_02_12_143818_add_service_id_to_ligne_paiement',1),(109,'2025_02_12_145514_add_date_affectation_livreur_to_livraison',1),(110,'2025_02_17_080325_add_commande_id_to_bl_client',1),(111,'2025_02_22_104704_create_logs_table',1),(112,'2025_02_26_160639_add_numero_compte_to_demande_paiement',1),(113,'2025_03_05_082406_add_service_id_to_paiement',1),(114,'2025_03_06_145351_add_user_valide2_id_to_demande_paiement',1),(115,'2025_03_06_153614_add_elements_to_configuration',1),(116,'2025_03_06_153731_add_qte_servi_to_enlevement',1),(117,'2025_03_06_153901_add_cout_livraison_to_livreur',1),(118,'2025_03_07_084641_add_gestionnaire_id_to_enlevement',1),(119,'2025_03_08_182805_add_service_to_facture',1),(120,'2025_03_08_191425_add_facture_id_to_paiement',1),(121,'2025_04_26_134923_create_regions_table',1),(122,'2025_06_18_150053_create_preuve_operations_table',1),(123,'2026_03_14_034543_create_sessions_table',1),(124,'2026_03_26_100000_create_prix_personnalises_table',1),(125,'2026_03_27_151223_add_documents_to_client_table',1),(126,'2026_04_10_100000_add_fne_fields_to_configuration_table',1),(127,'2026_04_10_100001_add_numero_fne_to_facture_table',1),(128,'2026_04_10_100002_add_regime_imposition_to_client_table',1),(129,'2024_09_30_221754_create_comptes_table',2),(130,'2025_06_16_104320_create_preuve_operation_banques_table',2),(131,'2026_04_13_160000_add_missing_columns_to_all_tables',2),(132,'2026_04_13_170000_add_user_id_to_reduction_table',3),(133,'2026_04_13_180000_add_prix_km_to_configuration_table',4),(134,'2026_04_14_100000_add_region_id_to_ville_table',5),(135,'2026_04_14_110000_add_missing_columns_to_devis_table',6),(136,'2026_04_14_120000_add_cout_livraison_to_detail_devis_table',7),(137,'2026_04_14_130000_add_est_livrable_to_commande_table',8),(138,'2026_04_14_140000_make_adresse_livraison_nullable_on_commande',9),(139,'2026_04_14_150000_add_cout_livraison_to_detail_commande_table',10),(140,'2026_04_14_160000_add_tonne_moyenne_cout_liv_fixe_to_configuration',11),(141,'2026_04_14_170000_make_devis_id_nullable_on_paiement',12),(142,'2026_04_14_180000_make_reference_nullable_on_ligne_paiement',13),(143,'2026_04_14_190000_make_moyen_paiement_nullable_on_ligne_paiement',14),(144,'2026_04_14_200000_enlarge_numero_on_devis',15),(145,'2026_04_14_210000_add_devis_id_to_reduction',16),(146,'2026_04_14_220000_add_location_fields_to_detail_devis',17),(147,'2026_04_17_100000_add_gestionnaire_livre_par_to_livraison',18),(148,'2026_04_17_110000_make_optional_fk_nullable_globally',19),(149,'2026_04_17_120000_drop_livreur_fk_on_livraison',20),(150,'2026_04_29_140000_create_historique_prix_livraison_livreur_table',21),(151,'2026_04_29_120000_add_fne_certification_fields_to_facture_table',22),(152,'2026_05_05_100000_add_libelle_to_devis_table',23),(153,'2026_05_05_120000_add_geo_to_livreur_table',24),(154,'2026_05_08_100000_add_creance_terme_fields_to_client_table',24),(155,'2026_05_08_110000_add_creance_terme_fields_to_facture_table',24),(156,'2026_05_08_120000_create_relance_client_terme_table',24),(157,'2026_05_08_130000_add_creance_terme_params_to_configuration_table',24),(158,'2026_05_08_140000_create_agence_table',25),(159,'2026_05_08_150000_add_comptant_fields_to_commande_table',25),(160,'2026_05_08_160000_add_encaissement_fields_to_paiement_table',25),(161,'2026_05_08_170000_add_comptant_params_to_configuration',25),(162,'2026_05_08_180000_add_dette_fields_to_fournisseur_table',26),(163,'2026_05_08_190000_add_dette_fields_to_enlevement_table',26),(164,'2026_05_08_200000_create_paiement_fournisseur_table',26),(165,'2026_05_08_210000_add_dette_fields_to_livreur_table',27),(166,'2026_05_08_220000_add_dette_fields_to_livraison_table',27),(167,'2026_05_08_230000_create_paiement_livreur_table',27),(168,'2026_05_08_240000_add_livreur_params_to_configuration',27),(169,'2026_05_08_250000_add_dette_fields_to_apporteur_table',28),(170,'2026_05_08_260000_add_dette_fields_to_commission_apporteur_table',28),(171,'2026_05_08_270000_create_paiement_apporteur_table',28),(172,'2026_05_08_280000_add_apporteur_params_to_configuration',28),(173,'2026_05_11_100000_create_type_vehicule_livreur_table',29),(174,'2026_05_11_110000_create_statut_metier_table',29),(175,'2026_05_13_000000_add_deleted_at_to_likes_table',30),(176,'2026_05_16_120000_fix_bl_client_fichier_path',31),(177,'2026_05_18_100000_add_double_validation_to_paiements',32),(178,'2026_05_18_120000_repair_validation_fields_on_paiements',33),(179,'2026_05_19_120000_add_numero_piece_and_mode_paiement_to_apporteur_table',34),(180,'2026_05_20_120000_add_validation_fields_to_demande_client_terme',35),(181,'2026_05_20_130000_enlarge_objet_in_demande_client_terme',36),(182,'2026_05_24_140000_enlarge_affichage_in_adresse_livraison',37),(183,'2026_06_02_000000_add_mode_tarification_to_livreur',38),(184,'2026_06_02_000100_add_zone_intervention_to_apporteur',39),(185,'2026_06_02_000200_add_documents_to_fournisseur_table',40),(186,'2026_06_02_000300_add_termes_conditions_to_configuration',41),(187,'2026_06_18_100000_add_en_cours_to_etat_livraison_enums',42);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

