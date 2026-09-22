<?php

namespace App\Exports;

use App\Services\Comptabilite\FormatSage;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Le classeur d'écritures à importer dans le logiciel comptable.
 * Les colonnes et leur contenu viennent de FormatSage : c'est là que le
 * gabarit se règle, pas ici.
 */
class EcrituresComptablesExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    private Collection $ecritures;

    public function __construct(Collection $ecritures)
    {
        $this->ecritures = $ecritures;
    }

    public function array(): array
    {
        return FormatSage::lignes($this->ecritures);
    }

    public function headings(): array
    {
        return FormatSage::entetes();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
