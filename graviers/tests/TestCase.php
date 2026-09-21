<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * LA SUITE NE S'ARRÊTE PLUS AU BOUT DE 120 SECONDES.
     *
     * Trois contrôleurs appellent `@set_time_limit(120)` — une précaution
     * légitime pour une requête web qui génère un PDF. Mais la limite vaut
     * pour TOUT le processus : dès qu'un essai touchait l'un d'eux, le compte
     * à rebours repartait, et la suite entière mourait 120 secondes plus tard
     * sur « Maximum execution time exceeded » — à un endroit chaque fois
     * différent, donc sans rapport visible avec la cause.
     *
     * Elle ne s'est manifestée que le 01/09/2026, quand la suite a dépassé
     * cette durée. On lève la limite avant chaque essai : un lanceur d'essais
     * n'est pas une requête web.
     */
    protected function setUp(): void
    {
        parent::setUp();

        @set_time_limit(0);
    }
}
