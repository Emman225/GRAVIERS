<?php

namespace App\Console\Commands;

use App\Models\CompteComptable;
use App\Models\Configuration;
use App\Models\JournalComptable;
use App\Models\RubriqueComptable;
use App\Services\Comptabilite\ParametrageComptable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * LE PARAMÉTRAGE DE DÉPART (module « Écritures comptables », phase 6, lot 123).
 *
 * Pose un plan de comptes d'usage — racines SYSCOHADA, complétées de zéros à
 * la longueur choisie dans les réglages —, rattache les rubriques de facture
 * et les journaux de trésorerie, et s'arrête là.
 *
 * TROIS RÈGLES :
 *  - elle ne DÉCIDE rien à la place du comptable : les numéros proposés se
 *    modifient à l'écran, un par un, et ce qui est déjà saisi n'est JAMAIS
 *    remplacé — elle ne crée que ce qui manque ;
 *  - elle ne touche ni aux grandes familles, ni aux produits, ni aux comptes
 *    tiers : ces choix-là regardent le catalogue et les clients de
 *    l'entreprise, pas une liste générique ;
 *  - rejouée, elle ne fait rien de plus.
 *
 *   php artisan comptabilite:plan-de-comptes-initial --simulation
 *   php artisan comptabilite:plan-de-comptes-initial
 */
class PlanDeComptesInitial extends Command
{
    protected $signature = 'comptabilite:plan-de-comptes-initial
                            {--simulation : montre ce qui serait créé, sans rien écrire}';

    protected $description = 'Pose un plan de comptes de départ (racines SYSCOHADA) et rattache rubriques et journaux. Ne remplace jamais une saisie existante.';

    /**
      * racine => libellé. Complétée de zéros à la longueur des réglages.
      *
      * Les racines suivent la nomenclature du comptable, lue dans sa balance Sage :
      * quatre chiffres de racine puis quatre de rang (40110000 fournisseurs,
      * 41100000 clients, 52110000 banque, 57110000 caisse). À six chiffres, la même
      * racine donne 401100, 411000, 521100 : la lecture reste la même.
      */
    private const COMPTES = [
        '4110' => 'Clients',
        '4011' => 'Fournisseurs',
        '4012' => 'Livreurs',
        '4013' => 'Apporteurs d\'affaires',
        '4431' => 'TVA facturée sur ventes',
        '4471' => 'AIRSI collecté',
        '7061' => 'Transport facturé',
        '7090' => 'Rabais, remises et ristournes accordés',
        '4191' => 'Clients, avances et acomptes reçus',
        '1650' => 'Dépôts et cautionnements reçus',
        '7580' => 'Produits divers (cautions retenues)',
        '6010' => 'Achats de marchandises',
        '6120' => 'Transports sur ventes (courses des livreurs)',
        '6320' => 'Rémunérations d\'intermédiaires (commissions des apporteurs)',
        '5211' => 'Banque',
        '5711' => 'Caisse principale',
        '5521' => 'Monnaie électronique (Mobile Money)',
    ];

    /** rubrique de facture => racine du compte général proposé. */
    private const RUBRIQUES = [
        RubriqueComptable::CLIENTS            => '4110',
        RubriqueComptable::FOURNISSEURS       => '4011',
        RubriqueComptable::LIVREURS           => '4012',
        RubriqueComptable::APPORTEURS         => '4013',
        RubriqueComptable::TVA_COLLECTEE      => '4431',
        RubriqueComptable::AIRSI              => '4471',
        RubriqueComptable::TRANSPORT          => '7061',
        RubriqueComptable::REMISES            => '7090',
        RubriqueComptable::AVANCES_CLIENTS    => '4191',
        RubriqueComptable::CAUTIONS           => '1650',
        RubriqueComptable::CAUTIONS_RETENUES  => '7580',
        RubriqueComptable::ACHATS_FOURNISSEURS => '6010',
        RubriqueComptable::CHARGES_LIVREURS   => '6120',
        RubriqueComptable::CHARGES_APPORTEURS => '6320',
    ];

    /** type de journal => racine de son compte de trésorerie. */
    private const TRESORERIE = [
        JournalComptable::TYPE_BANQUE       => '5211',
        JournalComptable::TYPE_CAISSE       => '5711',
        JournalComptable::TYPE_MOBILE_MONEY => '5521',
    ];

    public function handle(): int
    {
        $simulation = (bool) $this->option('simulation');
        $longueur = ParametrageComptable::longueurCompte();
        $this->line('Longueur des numéros de comptes : ' . $longueur . ' chiffres (réglages du paramétrage comptable).');

        // 1. Les comptes généraux.
        $numeros = [];
        $crees = 0;
        foreach (self::COMPTES as $racine => $libelle) {
            $numero = str_pad($racine, $longueur, '0');
            $numeros[$racine] = $numero;
            if (CompteComptable::withTrashed()->generaux()->where('numero', $numero)->exists()) {
                continue;
            }
            if (!$simulation) {
                CompteComptable::create(['nature' => CompteComptable::NATURE_GENERAL, 'numero' => $numero, 'libelle' => $libelle, 'statut' => 1]);
            }
            $this->line('  compte ' . $numero . ' — ' . $libelle);
            $crees++;
        }
        $this->info($crees . ' compte(s) général(aux) ' . ($simulation ? 'à créer' : 'créé(s)') . ', ' . (count(self::COMPTES) - $crees) . ' déjà présent(s).');

        // 2. Les rubriques de facture qui n'ont pas encore de compte.
        $rattachees = 0;
        foreach (RubriqueComptable::toutes() as $rubrique) {
            if ($rubrique->compte_comptable_id || !isset(self::RUBRIQUES[$rubrique->code])) {
                continue;
            }
            $compte = $simulation ? null : CompteComptable::generaux()->where('numero', $numeros[self::RUBRIQUES[$rubrique->code]])->first();
            if (!$simulation && !$compte) {
                continue;
            }
            if (!$simulation) {
                $rubrique->update(['compte_comptable_id' => $compte->id]);
            }
            $this->line('  rubrique « ' . $rubrique->libelle .' » → ' . $numeros[self::RUBRIQUES[$rubrique->code]]);
            $rattachees++;
        }
        $this->info($rattachees . ' rubrique(s) de facture ' . ($simulation ? 'à rattacher' : 'rattachée(s)') . '.');

        // 3. Les journaux de trésorerie sans compte.
        $journaux = 0;
        foreach (self::TRESORERIE as $type => $racine) {
            $journal = JournalComptable::where('type', $type)->orderBy('id')->first();
            if (!$journal || $journal->compte_comptable_id) {
                continue;
            }
            $compte = $simulation ? null : CompteComptable::generaux()->where('numero', $numeros[$racine])->first();
            if (!$simulation && !$compte) {
                continue;
            }
            if (!$simulation) {
                $journal->update(['compte_comptable_id' => $compte->id]);
            }
            $this->line('  journal ' . $journal->code . ' → compte de trésorerie ' . $numeros[$racine]);
            $journaux++;
        }
        $this->info($journaux . ' journal(aux) de trésorerie ' . ($simulation ? 'à compléter' : 'complété(s)') . '.');

        // 4. Le journal des cautions, s'il n'est pas encore choisi : la caisse.
        $configuration = Configuration::first();
        if ($configuration && !$configuration->journal_cautions_id) {
            $caisse = JournalComptable::where('type', JournalComptable::TYPE_CAISSE)->orderBy('id')->first();
            if ($caisse) {
                if (!$simulation) {
                    DB::table('configuration')->where('id', $configuration->id)->update(['journal_cautions_id' => $caisse->id]);
                }
                $this->info('Journal des cautions ' . ($simulation ? 'à régler sur' : 'réglé sur') . ' « ' . $caisse->designation . ' ».');
            }
        }

        // 5. Ce qui reste à faire, et que cette commande ne fera jamais à la place de quelqu'un.
        $this->newLine();
        $this->warn('Il reste à régler à l\'écran « Paramétrage comptable » :');
        $this->line('  - le compte général de chaque GRANDE FAMILLE du catalogue (onglet « Grandes familles ») ;');
        $this->line('  - la grande famille et le compte analytique de chaque PRODUIT (onglet « Produits », boutons « en un clic ») ;');
        $this->line('  - le COMPTE TIERS de chaque client, fournisseur, livreur et apporteur (onglet « Comptes tiers »,');
        $this->line('    bouton « Attribuer » : préfixe 4111 pour les clients, 4011 pour les fournisseurs, comme dans la balance Sage) ;');
        $this->line('  - le JOURNAL de chaque mode de règlement (onglet « Journaux et règlements ») ;');
        $this->line('  - et la vérification des numéros proposés ci-dessus par le comptable.');
        $this->newLine();
        $this->line('L\'onglet « Contrôle » du paramétrage dit à tout moment ce qui manque encore.');

        if ($simulation) {
            $this->newLine();
            $this->warn('SIMULATION : rien n\'a été écrit.');
        }

        return self::SUCCESS;
    }
}
