<?php

namespace Tests\Unit;

use Retour;
use PHPUnit\Framework\TestCase;

class RetourTest extends TestCase
{
    public function test_can_be_instantiated(): void
    {
        $retour = new Retour();
        $this->assertInstanceOf(Retour::class, $retour);
    }

    public function test_properties_default_to_null(): void
    {
        $retour = new Retour();

        $this->assertNull($retour->code);
        $this->assertNull($retour->token);
        $this->assertNull($retour->type);
        $this->assertNull($retour->message);
        $this->assertNull($retour->data);
        $this->assertNull($retour->photo);
        $this->assertNull($retour->configs);
        $this->assertNull($retour->nom);
        $this->assertNull($retour->email);
        $this->assertNull($retour->livreur);
        $this->assertNull($retour->apporteur);
        $this->assertNull($retour->tva);
        $this->assertNull($retour->code_parrain);
        $this->assertNull($retour->nomBrownPoint);
        $this->assertNull($retour->montantPoint);
        $this->assertNull($retour->device);
        $this->assertNull($retour->cat);
    }

    public function test_properties_are_writable(): void
    {
        $retour = new Retour();

        $retour->code = 200;
        $retour->token = 'abc123';
        $retour->type = 'success';
        $retour->message = 'Operation reussie';
        $retour->data = ['key' => 'value'];
        $retour->photo = 'photo.jpg';
        $retour->configs = ['config1' => true];
        $retour->nom = 'Jean Dupont';
        $retour->email = 'jean@example.com';
        $retour->livreur = 'Livreur1';
        $retour->apporteur = 'Apporteur1';
        $retour->tva = 18;
        $retour->code_parrain = 'PAR001';
        $retour->nomBrownPoint = 'PointA';
        $retour->montantPoint = 500;
        $retour->device = 'android';
        $retour->cat = 'categorie1';

        $this->assertEquals(200, $retour->code);
        $this->assertEquals('abc123', $retour->token);
        $this->assertEquals('success', $retour->type);
        $this->assertEquals('Operation reussie', $retour->message);
        $this->assertEquals(['key' => 'value'], $retour->data);
        $this->assertEquals('photo.jpg', $retour->photo);
        $this->assertEquals(['config1' => true], $retour->configs);
        $this->assertEquals('Jean Dupont', $retour->nom);
        $this->assertEquals('jean@example.com', $retour->email);
        $this->assertEquals('Livreur1', $retour->livreur);
        $this->assertEquals('Apporteur1', $retour->apporteur);
        $this->assertEquals(18, $retour->tva);
        $this->assertEquals('PAR001', $retour->code_parrain);
        $this->assertEquals('PointA', $retour->nomBrownPoint);
        $this->assertEquals(500, $retour->montantPoint);
        $this->assertEquals('android', $retour->device);
        $this->assertEquals('categorie1', $retour->cat);
    }
}
