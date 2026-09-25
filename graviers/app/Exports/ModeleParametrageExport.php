<?php

namespace App\Exports;

use App\Models\Apporteur;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\CompteComptable;
use App\Models\Fournisseur;
use App\Models\JournalComptable;
use App\Models\Livreur;
use App\Models\Produit;
use App\Models\RubriqueComptable;
use App\Services\Comptabilite\ImportParametrage;
use App\Services\Comptabilite\ParametrageComptable;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * LE CLASSEUR DE PARAMÉTRAGE (lot 129, 26/09/2026).
 *
 * Le même fichier sert de modèle ET de sauvegarde : il part DÉJÀ REMPLI de ce
 * qui est réglé aujourd'hui. L'entreprise corrige et complète au lieu de tout
 * taper, et ce qu'elle renvoie se relit par ImportParametrage.
 *
 * Les colonnes d'une feuille sont l'ordre que l'import attend : ne pas les
 * déplacer. Les noms des feuilles, eux, sont reconnus sans tenir compte des
 * accents ni de la casse.
 */
class ModeleParametrageExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            new FeuilleDeParametrage('Mode d\'emploi', ['Feuille', 'Ce qu\'elle règle', 'Ce qu\'il ne faut pas changer'], self::modeDEmploi()),
            new FeuilleDeParametrage('Comptes', ['Numéro', 'Intitulé', 'Nature'], self::comptes()),
            new FeuilleDeParametrage('Grandes familles', ['Grande famille', 'Compte général'], self::familles()),
            new FeuilleDeParametrage('Produits', ['Référence', 'Produit', 'Grande famille', 'Compte analytique'], self::produits()),
            new FeuilleDeParametrage('Rubriques', ['Code', 'Rubrique', 'Compte général', 'Compte analytique'], self::rubriques()),
            new FeuilleDeParametrage('Comptes tiers', ['Type', 'Identifiant', 'Nom', 'Compte tiers'], self::tiers()),
            new FeuilleDeParametrage('Journaux', ['Code', 'Libellé', 'Type', 'Compte de trésorerie'], self::journaux()),
            new FeuilleDeParametrage('Modes de règlement', ['Mode de règlement', 'Code journal'], self::modes()),
        ];
    }

    private static function modeDEmploi(): array
    {
        $longueur = ParametrageComptable::longueurCompte();

        return [
            ['Comptes', 'Le plan de comptes : les comptes généraux (chiffres seulement, ' . $longueur . ' chiffres) et les comptes analytiques (lettres, chiffres et tirets).', 'Rien : c\'est la seule feuille où l\'on peut créer librement.'],
            ['Grandes familles', 'Le compte général de vente de chaque catégorie du catalogue.', 'La colonne « Grande famille » : le nom vient du catalogue.'],
            ['Produits', 'La grande famille comptable et le compte analytique de chaque article.', 'La colonne « Référence » : c\'est elle qui retrouve le produit.'],
            ['Rubriques', 'Les comptes de la TVA, de l\'AIRSI, du transport, des remises, des avances, des cautions, des partenaires.', 'La colonne « Code » : ces codes sont ceux du module.'],
            ['Comptes tiers', 'Le compte tiers de chaque client, fournisseur, livreur et apporteur.', 'Les colonnes « Type » et « Identifiant ».'],
            ['Journaux', 'Les journaux et, pour ceux de trésorerie, leur compte.', 'Rien, mais un code déjà employé garde ses écritures.'],
            ['Modes de règlement', 'Le journal de trésorerie où entre chaque moyen de paiement.', 'La colonne « Mode de règlement » : le libellé vient du site.'],
            ['', '', ''],
            ['RÈGLE 1', 'L\'import ne supprime jamais rien. Il crée ce qui manque et corrige ce que le fichier nomme ; une ligne effacée du fichier laisse le réglage en place.', ''],
            ['RÈGLE 2', 'Une ligne qu\'on ne sait pas rattacher est refusée avec sa cause, et le reste du fichier passe quand même.', ''],
            ['RÈGLE 3', 'On analyse avant d\'appliquer : rien n\'est écrit tant que le compte rendu n\'est pas validé.', ''],
            ['RÈGLE 4', 'Une feuille laissée vide, ou retirée du classeur, n\'est pas touchée du tout.', ''],
        ];
    }

    private static function comptes(): array
    {
        return CompteComptable::orderBy('nature')->orderBy('numero')->get()
            ->map(fn ($c) => [$c->numero, $c->libelle, CompteComptable::NATURES[$c->nature] ?? $c->nature])->all();
    }

    private static function familles(): array
    {
        $numeros = CompteComptable::pluck('numero', 'id');

        return ParametrageComptable::familles()
            ->map(fn ($f) => [$f->nom, $numeros[$f->compte_comptable_id] ?? ''])->values()->all();
    }

    private static function produits(): array
    {
        $numeros = CompteComptable::pluck('numero', 'id');
        $familles = Categorie::pluck('nom', 'id');

        return Produit::where('statut', 1)->orderBy('nom')->get()
            ->map(fn ($p) => [
                (string) $p->reference,
                (string) $p->nom,
                $familles[$p->categorie_comptable_id] ?? '',
                $numeros[$p->compte_analytique_id] ?? '',
            ])->all();
    }

    private static function rubriques(): array
    {
        $numeros = CompteComptable::pluck('numero', 'id');

        return RubriqueComptable::toutes()
            ->map(fn ($r) => [
                $r->code,
                $r->libelle,
                $numeros[$r->compte_comptable_id] ?? '',
                $r->accepteUnAnalytique() ? ($numeros[$r->compte_analytique_id] ?? '') : '',
            ])->values()->all();
    }

    private static function tiers(): array
    {
        $lignes = [];
        foreach (ImportParametrage::TIERS as $type => $classe) {
            foreach ($classe::orderBy('id')->get() as $tiers) {
                $nom = $type === 'client'
                    ? ($tiers->display_name ?: 'Client n° ' . $tiers->id)
                    : \App\Services\Comptabilite\MoteurTresorerie::nomDuPartenaire($type, $tiers);
                $lignes[] = [$type, (string) $tiers->id, $nom, (string) $tiers->compte_tiers];
            }
        }

        return $lignes;
    }

    private static function journaux(): array
    {
        $numeros = CompteComptable::pluck('numero', 'id');

        return JournalComptable::orderBy('code')->get()
            ->map(fn ($j) => [$j->code, $j->libelle, $j->type, $numeros[$j->compte_comptable_id] ?? ''])->all();
    }

    private static function modes(): array
    {
        $codes = JournalComptable::pluck('code', 'id');

        return collect(DB::table('mode_paiement')->whereNull('deleted_at')->where('statut', 1)->orderBy('libelle')->get())
            ->map(fn ($m) => [$m->libelle, $codes[$m->journal_comptable_id] ?? ''])->all();
    }
}

/** Une feuille du classeur : un titre, des en-têtes, des lignes. */
class FeuilleDeParametrage implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    private string $titre;
    private array $entetes;
    private array $lignes;

    public function __construct(string $titre, array $entetes, array $lignes)
    {
        $this->titre = $titre;
        $this->entetes = $entetes;
        $this->lignes = $lignes;
    }

    public function title(): string
    {
        return $this->titre;
    }

    public function headings(): array
    {
        return $this->entetes;
    }

    public function array(): array
    {
        return $this->lignes;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
