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
 * Le plan comptable au format d'import de Sage (gabarit du 26/09/2026).
 * Les colonnes viennent de FormatSage : c'est là que le gabarit se règle.
 */
class PlanComptableSageExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    private Collection $comptes;

    public function __construct(Collection $comptes)
    {
        $this->comptes = $comptes;
    }

    public function array(): array
    {
        return FormatSage::plan($this->comptes);
    }

    public function headings(): array
    {
        return FormatSage::entetesDuPlan();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
