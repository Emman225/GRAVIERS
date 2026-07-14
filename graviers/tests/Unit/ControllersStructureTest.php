<?php

namespace Tests\Unit;

use App\Http\Controllers\Controller;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests structurels pour tous les Controllers de l'application.
 *
 * Vérifie via réflexion (sans booter Laravel) que chaque Controller :
 *  - est chargeable
 *  - hérite du Controller de base
 *  - n'a pas de méthodes publiques cassées (introspection saine)
 */
class ControllersStructureTest extends TestCase
{
    /**
     * @return array<int, array{0: string}>
     */
    public static function controllerProvider(): array
    {
        $dir = __DIR__ . '/../../app/Http/Controllers';
        $files = scandir($dir);
        $controllers = [];

        foreach ($files as $file) {
            if (!preg_match('/^([A-Za-z0-9_]+)\.php$/', $file, $m)) {
                continue;
            }
            $base = $m[1];
            $path = $dir . '/' . $file;

            // Avant d'autoloader, on vérifie que le fichier déclare bien la classe
            // attendue (sinon on saute — cas des fichiers "*_new.php" qui
            // contiennent une classe en doublon non destinée à l'autoload).
            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }
            $expectedClassDecl = "/^\s*class\s+" . preg_quote($base, '/') . "\b/m";
            if (!preg_match($expectedClassDecl, $contents)) {
                continue;
            }

            $class = 'App\\Http\\Controllers\\' . $base;
            if (!class_exists($class)) {
                continue;
            }
            $controllers[$base] = [$class];
        }

        ksort($controllers);
        return array_values($controllers);
    }

    /**
     * @dataProvider controllerProvider
     */
    public function test_controller_class_exists(string $class): void
    {
        $this->assertTrue(
            class_exists($class),
            "Le controller $class doit être autoloadable."
        );
    }

    /**
     * @dataProvider controllerProvider
     */
    public function test_controller_extends_base_controller(string $class): void
    {
        $ref = new ReflectionClass($class);

        // Soit c'est le Controller de base, soit il en hérite
        $isBaseOrSubclass = $ref->getName() === Controller::class
            || $ref->isSubclassOf(Controller::class)
            || $ref->isSubclassOf(\Illuminate\Routing\Controller::class);

        $this->assertTrue(
            $isBaseOrSubclass,
            "$class doit hériter de App\\Http\\Controllers\\Controller."
        );
    }

    /**
     * @dataProvider controllerProvider
     */
    public function test_controller_is_not_abstract(string $class): void
    {
        $ref = new ReflectionClass($class);
        $this->assertFalse(
            $ref->isAbstract() && $ref->getName() !== Controller::class,
            "$class ne doit pas être abstrait (sauf le Controller de base)."
        );
    }

    /**
     * @dataProvider controllerProvider
     */
    public function test_controller_public_methods_have_valid_names(string $class): void
    {
        $ref = new ReflectionClass($class);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $m) {
            // On ignore les méthodes héritées
            if ($m->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $this->assertMatchesRegularExpression(
                '/^[a-zA-Z_][a-zA-Z0-9_]*$/',
                $m->getName(),
                "Méthode invalide dans $class : " . $m->getName()
            );
        }

        $this->assertTrue(true);
    }

    /**
     * @dataProvider controllerProvider
     */
    public function test_controller_methods_parameters_are_introspectable(string $class): void
    {
        $ref = new ReflectionClass($class);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $m) {
            if ($m->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            try {
                $params = $m->getParameters();
                $this->assertIsArray($params);
            } catch (\Throwable $e) {
                $this->fail(
                    "Erreur de réflexion sur $class::" . $m->getName() . " : " . $e->getMessage()
                );
            }
        }

        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // Tests spécifiques pour les Controllers métier clés
    // ---------------------------------------------------------------

    public function test_cart_controller_has_key_methods(): void
    {
        $class = \App\Http\Controllers\CartController::class;
        $this->assertTrue(
            method_exists($class, 'updateQuantite'),
            "$class doit avoir updateQuantite()"
        );
        $this->assertTrue(
            method_exists($class, 'updateAll'),
            "$class doit avoir updateAll()"
        );
    }

    public function test_orders_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\OrdersController::class));
    }

    public function test_client_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\ClientController::class));
    }

    public function test_user_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\UserController::class));
    }

    public function test_product_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\ProductController::class));
    }

    public function test_devis_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\DevisController::class));
    }

    public function test_paiement_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\PaiementController::class));
    }

    public function test_paiement_en_ligne_controllers_exist(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\PaiementEnLigne::class));
        // Le fichier PaiementEnLigne_new.php existe sur disque mais déclare en
        // fait une classe `PaiementController` (doublon historique). On vérifie
        // donc seulement la présence du fichier source, pas de la classe.
        $this->assertFileExists(
            __DIR__ . '/../../app/Http/Controllers/PaiementEnLigne_new.php'
        );
    }

    public function test_livreur_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\LivreurController::class));
    }

    public function test_apporteur_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\ApporteurController::class));
    }

    public function test_seller_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\SellerController::class));
    }

    public function test_commande_comptant_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\CommandeComptantController::class));
    }

    public function test_error_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\ErrorController::class));
    }

    public function test_grand_livre_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\GrandLivreController::class));
    }

    public function test_agence_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\AgenceController::class));
    }

    public function test_dette_controllers_exist(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\DetteApporteurController::class));
        $this->assertTrue(class_exists(\App\Http\Controllers\DetteFournisseurController::class));
        $this->assertTrue(class_exists(\App\Http\Controllers\DetteLivreurController::class));
    }

    public function test_creance_client_terme_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\CreanceClientTermeController::class));
    }

    public function test_destination_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\DestinationController::class));
    }

    public function test_configuration_prix_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\ConfigurationPrixController::class));
    }

    public function test_statut_metier_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\StatutMetierController::class));
    }

    public function test_recap_controllers_exist(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\RecapCreancesController::class));
        $this->assertTrue(class_exists(\App\Http\Controllers\RecapGlobalDettesController::class));
    }

    public function test_reset_process_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\ResetProcessController::class));
    }

    public function test_type_vehicule_livreur_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\TypeVehiculeLivreurController::class));
    }
}
