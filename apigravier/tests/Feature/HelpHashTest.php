<?php

namespace Tests\Feature;

use Help;
use Tests\TestCase;

class HelpHashTest extends TestCase
{
    public function test_hash_password_returns_non_empty_string(): void
    {
        $hash = Help::HashPassword('secret123');
        $this->assertNotEmpty($hash);
        $this->assertIsString($hash);
    }

    public function test_hash_verifier_returns_true_for_correct_password(): void
    {
        $password = 'monMotDePasse';
        $hash = Help::HashPassword($password);
        $this->assertTrue(Help::HashVerifier($password, $hash));
    }

    public function test_hash_verifier_returns_false_for_wrong_password(): void
    {
        $hash = Help::HashPassword('correct');
        $this->assertFalse(Help::HashVerifier('wrong', $hash));
    }
}
