<?php

namespace Tests\Unit;

use Livewire\Component;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests structurels pour les composants Livewire.
 *
 * Pas de bootstrap Laravel - on vérifie la structure (héritage, méthodes
 * publiques, propriétés publiques) via réflexion.
 */
class LivewireComponentsTest extends TestCase
{
    /**
     * @return array<int, array{0: string}>
     */
    public static function livewireProvider(): array
    {
        $dir = __DIR__ . '/../../app/Livewire';
        $files = scandir($dir);
        $components = [];

        foreach ($files as $file) {
            if (!preg_match('/^([A-Za-z0-9_]+)\.php$/', $file, $m)) {
                continue;
            }
            $class = 'App\\Livewire\\' . $m[1];
            if (!class_exists($class)) {
                continue;
            }
            $components[$m[1]] = [$class];
        }

        ksort($components);
        return array_values($components);
    }

    /**
     * @dataProvider livewireProvider
     */
    public function test_livewire_component_class_exists(string $class): void
    {
        $this->assertTrue(class_exists($class), "$class doit exister.");
    }

    /**
     * @dataProvider livewireProvider
     */
    public function test_livewire_component_extends_component(string $class): void
    {
        $ref = new ReflectionClass($class);
        $this->assertTrue(
            $ref->isSubclassOf(Component::class),
            "$class doit hériter de Livewire\\Component."
        );
    }

    /**
     * @dataProvider livewireProvider
     */
    public function test_livewire_component_has_render_method(string $class): void
    {
        $ref = new ReflectionClass($class);
        $this->assertTrue(
            $ref->hasMethod('render'),
            "$class doit avoir une méthode render()."
        );

        $render = $ref->getMethod('render');
        $this->assertTrue(
            $render->isPublic(),
            "$class::render() doit être public."
        );
    }

    /**
     * @dataProvider livewireProvider
     */
    public function test_livewire_component_is_instantiable(string $class): void
    {
        $ref = new ReflectionClass($class);
        $this->assertFalse(
            $ref->isAbstract(),
            "$class ne doit pas être abstrait."
        );
    }

    /**
     * @dataProvider livewireProvider
     */
    public function test_livewire_component_public_methods_have_valid_names(string $class): void
    {
        $ref = new ReflectionClass($class);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $m) {
            if ($m->getDeclaringClass()->getName() !== $class) {
                continue; // hérité
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
     * @dataProvider livewireProvider
     */
    public function test_livewire_component_public_properties_listed(string $class): void
    {
        $ref = new ReflectionClass($class);
        $props = $ref->getProperties(\ReflectionProperty::IS_PUBLIC);

        // On vérifie juste que la liste se lit sans erreur (introspection saine).
        foreach ($props as $p) {
            $this->assertIsString($p->getName());
        }
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // Tests spécifiques pour les composants existants
    // ---------------------------------------------------------------

    public function test_add_cart_has_render(): void
    {
        $this->assertTrue(method_exists(\App\Livewire\AddCart::class, 'render'));
    }

    public function test_panier_has_render(): void
    {
        $this->assertTrue(method_exists(\App\Livewire\Panier::class, 'render'));
    }

    public function test_liste_facture_has_required_properties(): void
    {
        $ref = new ReflectionClass(\App\Livewire\ListeFacture::class);
        $this->assertTrue($ref->hasProperty('factures'));
        $this->assertTrue($ref->hasProperty('commande'));
        $this->assertTrue($ref->hasProperty('numero'));
    }

    public function test_liste_facture_has_mount_method(): void
    {
        $this->assertTrue(method_exists(\App\Livewire\ListeFacture::class, 'mount'));
    }

    public function test_liste_facture_has_voir_facture_method(): void
    {
        $this->assertTrue(method_exists(\App\Livewire\ListeFacture::class, 'voirFacture'));
    }

    public function test_select_vehicule_livreur_has_properties(): void
    {
        $ref = new ReflectionClass(\App\Livewire\SelectVehiculeLivreur::class);
        $this->assertTrue($ref->hasProperty('livreurs'));
        $this->assertTrue($ref->hasProperty('vehicules'));
        $this->assertTrue($ref->hasProperty('SelectedLivreur'));
        $this->assertTrue($ref->hasProperty('SelectedVehicule'));
    }

    public function test_select_vehicule_livreur_has_mount(): void
    {
        $this->assertTrue(method_exists(\App\Livewire\SelectVehiculeLivreur::class, 'mount'));
    }

    public function test_select_vehicule_livreur_has_updated_hook(): void
    {
        // Hook Livewire "updatedSelectedLivreur" pour le binding wire:model
        $this->assertTrue(
            method_exists(\App\Livewire\SelectVehiculeLivreur::class, 'updatedSelectedLivreur')
        );
    }
}
