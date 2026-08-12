<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel de la liste des abonnés à la lettre d'information.
 */
class NewsletterExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    private Collection $abonnes;

    public function __construct(Collection $abonnes)
    {
        $this->abonnes = $abonnes;
    }

    public function collection(): Collection
    {
        return $this->abonnes->values()->map(function ($abonne, $index) {
            return [
                $index + 1,
                $abonne->email,
                $abonne->statut == 1 ? 'Abonné' : 'Désabonné',
                $abonne->origine ?: '—',
                $abonne->created_at ? $abonne->created_at->format('d/m/Y H:i') : '—',
            ];
        });
    }

    public function headings(): array
    {
        return ['N°', 'Adresse e-mail', 'Statut', 'Origine', 'Inscrit le'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
