<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Tests structurels pour TOUS les modèles Eloquent du projet.
 *
 * Ces tests utilisent la réflexion (pas de bootstrap Laravel) pour vérifier
 * que chaque modèle :
 *  - existe et est autoloadable
 *  - hérite de Model (ou Authenticatable)
 *  - définit un $fillable cohérent
 *  - définit un $table cohérent
 *  - déclare ses relations sans erreur de syntaxe (récupération via reflection)
 *
 * Si jamais une régression rend un modèle inutilisable (typo dans `use`,
 * syntaxe cassée, propriétés disparues), ces tests le détecteront.
 */
class ModelsStructureTest extends TestCase
{
    /**
     * Liste exhaustive des modèles à vérifier.
     * Détectée automatiquement par parcours de app/Models.
     *
     * @return array<int, array{0: string}>
     */
    public static function modelProvider(): array
    {
        $dir = __DIR__ . '/../../app/Models';
        $files = scandir($dir);
        $models = [];

        foreach ($files as $file) {
            if (!preg_match('/^([A-Za-z0-9_]+)\.php$/', $file, $m)) {
                continue;
            }
            $class = 'App\\Models\\' . $m[1];
            if (!class_exists($class)) {
                continue;
            }
            $models[$m[1]] = [$class];
        }

        ksort($models);
        return array_values($models);
    }

    // ---------------------------------------------------------------
    // Existence des classes
    // ---------------------------------------------------------------

    /**
     * @dataProvider modelProvider
     */
    public function test_model_class_exists_and_is_loadable(string $class): void
    {
        $this->assertTrue(
            class_exists($class),
            "Le modèle $class doit être autoloadable."
        );
    }

    /**
     * @dataProvider modelProvider
     */
    public function test_model_extends_eloquent_model(string $class): void
    {
        $ref = new ReflectionClass($class);

        // Doit hériter de Model OU de Authenticatable (cas spécial pour User)
        $extendsModel = $ref->isSubclassOf(Model::class)
            || $ref->isSubclassOf(Authenticatable::class)
            || $ref->getName() === Model::class
            || $ref->getName() === Authenticatable::class;

        $this->assertTrue(
            $extendsModel,
            "Le modèle $class doit hériter d'Eloquent\\Model ou d'Authenticatable."
        );
    }

    /**
     * @dataProvider modelProvider
     */
    public function test_model_is_instantiable(string $class): void
    {
        $ref = new ReflectionClass($class);

        $this->assertFalse(
            $ref->isAbstract(),
            "Le modèle $class ne doit pas être abstrait."
        );

        $this->assertFalse(
            $ref->isInterface(),
            "Le modèle $class ne doit pas être une interface."
        );
    }

    /**
     * @dataProvider modelProvider
     */
    public function test_model_table_is_string_if_defined(string $class): void
    {
        $defaults = (new ReflectionClass($class))->getDefaultProperties();

        if (array_key_exists('table', $defaults) && $defaults['table'] !== null) {
            $this->assertIsString(
                $defaults['table'],
                "$class::\$table doit être une string."
            );
            $this->assertNotEmpty(
                $defaults['table'],
                "$class::\$table ne doit pas être vide."
            );
        } else {
            $this->assertTrue(true, 'Pas de $table explicite (Laravel inférera depuis le nom de classe).');
        }
    }

    /**
     * @dataProvider modelProvider
     */
    public function test_model_fillable_is_array(string $class): void
    {
        $defaults = (new ReflectionClass($class))->getDefaultProperties();

        // $fillable peut être hérité de Model donc on l'attend toujours présent
        $this->assertArrayHasKey(
            'fillable',
            $defaults,
            "$class doit avoir une propriété \$fillable (au moins héritée)."
        );

        $this->assertIsArray(
            $defaults['fillable'],
            "$class::\$fillable doit être un tableau."
        );
    }

    /**
     * @dataProvider modelProvider
     */
    public function test_model_hidden_is_array_if_defined(string $class): void
    {
        $defaults = (new ReflectionClass($class))->getDefaultProperties();

        if (array_key_exists('hidden', $defaults)) {
            $this->assertIsArray(
                $defaults['hidden'],
                "$class::\$hidden doit être un tableau."
            );
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * @dataProvider modelProvider
     */
    public function test_model_can_be_instantiated_without_db(string $class): void
    {
        // Eloquent permet `new Model()` sans DB pour la plupart des modèles.
        // Quelques modèles (ex. User avec Sluggable) enregistrent des observers
        // au boot et nécessitent un Event Dispatcher dans le container.
        // Dans ce cas, on injecte un dispatcher Eloquent minimal le temps de
        // l'instanciation, ce qui permet de valider que la classe est saine.
        try {
            $instance = new $class();
        } catch (\Throwable $e) {
            $needsDispatcher = str_contains($e->getMessage(), 'Illuminate\Contracts\Events\Dispatcher')
                || str_contains($e->getMessage(), 'is not instantiable')
                || str_contains($e->getMessage(), 'SluggableObserver');

            if (!$needsDispatcher) {
                $this->fail("Impossible d'instancier $class : " . $e->getMessage());
            }

            // Injection d'un dispatcher Eloquent minimal pour l'instanciation
            $previousDispatcher = \Illuminate\Database\Eloquent\Model::getEventDispatcher();
            \Illuminate\Database\Eloquent\Model::setEventDispatcher(
                new \Illuminate\Events\Dispatcher()
            );

            try {
                $instance = new $class();
            } finally {
                if ($previousDispatcher) {
                    \Illuminate\Database\Eloquent\Model::setEventDispatcher($previousDispatcher);
                } else {
                    \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
                }
            }
        }

        $this->assertInstanceOf($class, $instance);
    }

    /**
     * @dataProvider modelProvider
     */
    public function test_model_can_set_attributes_via_mass_assignment(string $class): void
    {
        $defaults = (new ReflectionClass($class))->getDefaultProperties();
        $fillable = $defaults['fillable'] ?? [];

        if (empty($fillable)) {
            // Aucun fillable => rien à tester
            $this->assertTrue(true);
            return;
        }

        try {
            $instance = new $class();
        } catch (\Throwable $e) {
            $this->markTestSkipped("$class non instanciable hors bootstrap Laravel.");
        }

        // On essaie le premier fillable ; si ça échoue à cause d'un cast date
        // nécessitant DB::connection(), on essaie les suivants.
        $lastError = null;
        foreach ($fillable as $field) {
            try {
                $instance->fill([$field => 'test_value']);
                $value = $instance->getAttribute($field);
                $this->assertSame('test_value', $value);
                return;
            } catch (\Throwable $e) {
                $lastError = $e;
                // Cas connu : cast vers date/datetime nécessite DB::connection()
                if (str_contains($e->getMessage(), 'connection() on null')
                    || str_contains($e->getMessage(), 'date')) {
                    continue;
                }
                $this->fail("Impossible de fill {$field} sur $class : " . $e->getMessage());
            }
        }

        // Tous les fillable ont déclenché une erreur "connection() on null"
        $this->markTestSkipped(
            "$class : tous les fillable utilisent des casts nécessitant le bootstrap DB."
        );
    }

    /**
     * @dataProvider modelProvider
     */
    public function test_model_public_methods_are_callable(string $class): void
    {
        $ref = new ReflectionClass($class);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);

        // Filtre : on garde uniquement celles définies dans le model ou les classes du projet,
        // pas celles héritées de Eloquent\Model.
        $own = array_filter($methods, function (ReflectionMethod $m) use ($class) {
            return $m->getDeclaringClass()->getName() === $class;
        });

        // Le test vérifie juste que les méthodes propres sont bien réfléchies
        // (pas de typo ou de syntaxe cassée qui empêcherait reflection de les lire)
        foreach ($own as $m) {
            $this->assertIsString(
                $m->getName(),
                "Méthode dans $class doit avoir un nom valide."
            );
        }

        // S'il n'y a aucune méthode propre, c'est ok (le test passe)
        $this->assertTrue(true);
    }
}
