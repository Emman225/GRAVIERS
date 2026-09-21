<?php

namespace Tests\Unit;

use App\Services\CodeBarres;
use PHPUnit\Framework\TestCase;

/** Code 128 sans bibliothèque (lot 111, 19/09/2026). */
class CodeBarresTest extends TestCase
{
    public function test_les_motifs_sont_ceux_de_la_norme(): void
    {
        $motifs = (new \ReflectionClassConstant(CodeBarres::class, 'MOTIFS'))->getValue();
        $this->assertCount(107, $motifs);
        $this->assertCount(107, array_unique($motifs), 'Aucun motif en double.');
        foreach ($motifs as $i => $m) {
            $this->assertSame($i === 106 ? 13 : 11, array_sum(str_split($m)), "Motif $i : nombre de modules.");
        }
    }

    public function test_la_somme_de_controle_est_modulo_103(): void
    {
        // « PJJ123C » en jeu B : 104 + 48·1 + 42·2 + 42·3 + 17·4 + 18·5 + 19·6 + 35·7 = 879 → 879 mod 103 = 55.
        $s = CodeBarres::symboles('PJJ123C');
        $this->assertSame([104, 48, 42, 42, 17, 18, 19, 35, 55, 106], $s);
    }

    public function test_les_modules_sont_identiques_a_ceux_d_un_encodeur_de_reference(): void
    {
        // Référence produite par python-barcode 0.16.1 (Code128, jeu B) — encodeur indépendant.
        $reference = '1101001000011101110110101101110001011011100010011100110110011100101100101110010001000110111010001101100011101011';
        $this->assertSame($reference, CodeBarres::modules('PJJ123C'));
        $this->assertSame(112, strlen($reference));
    }

    public function test_un_numero_de_bon_donne_une_image_et_un_numero_lisible(): void
    {
        $this->assertSame('', CodeBarres::modules(''));
        $this->assertSame('ENL777003', CodeBarres::nettoyer(" ENL777003\n"));
        $modules = CodeBarres::modules('ENL777003');
        $this->assertSame((1 + 9 + 1) * 11 + 13, strlen($modules));
        $this->assertStringStartsWith('11010010000', $modules, 'Départ B.');
        $this->assertStringEndsWith('1100011101011', $modules, 'Arrêt.');
        if (function_exists('imagecreatetruecolor')) {
            $this->assertStringStartsWith('data:image/png;base64,', CodeBarres::image('ENL777003'));
        }
    }
}
