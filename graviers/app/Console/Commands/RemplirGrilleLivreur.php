<?php

namespace App\Console\Commands;

use App\Models\CoutLivraison;
use App\Models\CoutLivraisonLivreur;
use App\Models\Livreur;
use App\Models\UniteProduit;
use Illuminate\Console\Command;

/**
 * Pré-remplit la grille d'un livreur à partir de celle du client.
 *
 * La grille client compte 96 tranches : les saisir une à une pour chaque
 * livreur découragerait n'importe qui, et la fonction resterait inutilisée.
 *
 * En dérivant la grille du livreur d'un POURCENTAGE du tarif client, la marge
 * devient garantie sur chaque tranche : à 60 % pour le livreur, DALAKOUN garde
 * 40 % partout, sans exception ni oubli. Chaque tranche reste corrigeable
 * ensuite à la main.
 */
class RemplirGrilleLivreur extends Command
{
    protected $signature = 'livreur:grille
                            {livreur? : Identifiant du livreur}
                            {--part= : Part revenant au livreur, en pourcentage}
                            {--apply : Écrit réellement ; sans cette option, simulation}
                            {--vider : Supprime la grille du livreur au lieu de la remplir}
                            {--comparer : Compare le tarif actuel du livreur avec la grille, sans rien écrire}
                            {--unite= : Ne traiter qu\'une unité (abréviation ou identifiant), les autres restent au tarif actuel}
                            {--reel : Pondère la comparaison par les courses réellement effectuées}
                            {--plancher= : Montant minimum versé au livreur sur une course, quel que soit le pourcentage}';

    protected $description = "Remplit la grille de facturation d'un livreur depuis la grille client";

    public function handle(): int
    {
        if (!CoutLivraisonLivreur::tableExiste()) {
            $this->error('La table cout_livraison_livreur n\'existe pas : lancez php artisan migrate --force.');

            return self::FAILURE;
        }

        $livreur = $this->livreur();

        if (!$livreur) {
            return self::FAILURE;
        }

        if ($this->option('vider')) {
            return $this->vider($livreur);
        }

        if ($this->option('comparer')) {
            return $this->comparer($livreur);
        }

        $part = $this->part($livreur);

        if ($part === null) {
            return self::FAILURE;
        }

        // Le MEME selecteur que la comparaison : sans cela, --unite filtrait
        // l apercu mais posait quand meme tout le catalogue.
        if ($this->uniteDemandee() === false) {
            return self::FAILURE;
        }

        $tranches = $this->tranchesClient();

        if ($tranches->isEmpty()) {
            $this->error('Aucune tranche client a deriver pour ce perimetre.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Livreur %s — part livreur %s %%, marge DALAKOUN %s %%',
            $livreur->user->nom_prenoms ?? ('#' . $livreur->id),
            $this->nombre($part),
            $this->nombre(100 - $part)
        ));
        $this->newLine();

        $lignes = [];
        $totalClient = 0.0;
        $totalLivreur = 0.0;

        foreach ($tranches as $t) {
            $prixClient  = (float) $t->prix_km;
            $prixLivreur = $this->montantLivreur($prixClient, $part, $this->plancher());

            $totalClient  += $prixClient;
            $totalLivreur += $prixLivreur;

            $lignes[] = [
                $t->uniteProduit->libelle ?? $t->unite_produit_id,
                $this->nombre($t->unite_min) . ' - ' . $this->nombre($t->unite_max),
                $this->nombre($t->distance_min_km) . ' - ' . $this->nombre($t->distance_max_km),
                $this->nombre($prixClient),
                $this->nombre($prixLivreur),
                $this->nombre($prixClient - $prixLivreur),
            ];
        }

        // Les dix premières suffisent à juger : la table entière ferait 96 lignes
        // que personne ne lirait.
        $this->table(
            ['Unité', 'Quantité', 'Distance (km)', 'Client', 'Livreur', 'DALAKOUN'],
            array_slice($lignes, 0, 10)
        );

        if (count($lignes) > 10) {
            $this->line(sprintf('… et %d autres tranches.', count($lignes) - 10));
        }

        $this->newLine();
        $this->line(sprintf(
            'Sur les %d tranches : le client paierait %s F au total, le livreur toucherait %s F, DALAKOUN garderait %s F.',
            count($lignes),
            $this->nombre($totalClient),
            $this->nombre($totalLivreur),
            $this->nombre($totalClient - $totalLivreur)
        ));

        if (!$this->option('apply')) {
            $this->newLine();
            $this->warn('Simulation : rien n\'a été écrit. Relancez avec --apply pour enregistrer.');

            return self::SUCCESS;
        }

        // On repart d'une grille propre — mais SEULEMENT sur ce qu'on refait.
        //
        // Effacer toute la grille alors qu'on ne traite qu'une unité
        // supprimerait le travail déjà fait sur les autres, et le livreur
        // repasserait sans prévenir sur son ancien tarif.
        $unite = $this->uniteDemandee();

        CoutLivraisonLivreur::where('livreur_id', $livreur->id)
            ->when($unite, fn ($q) => $q->where('unite_produit_id', $unite->id))
            ->delete();

        foreach ($tranches as $t) {
            CoutLivraisonLivreur::create([
                'livreur_id'       => $livreur->id,
                'unite_produit_id' => $t->unite_produit_id,
                'ville_id'         => null,
                'unite_min'        => $t->unite_min,
                'unite_max'        => $t->unite_max,
                'distance_min_km'  => $t->distance_min_km,
                'distance_max_km'  => $t->distance_max_km,
                'prix'             => $this->montantLivreur((float) $t->prix_km, $part, $this->plancher()),
            ]);
        }

        // La part n'est mémorisée que si elle vaut pour TOUT le catalogue :
        // affichée sur la fiche, elle ferait sinon croire à un taux unique là
        // où seule une unité a été traitée.
        if (!$unite) {
            $livreur->update(['part_grille' => $part]);
        }

        $plancher = $this->plancher();

        if ($plancher !== null) {
            $releves = $tranches->filter(
                fn ($t) => round((float) $t->prix_km * $part / 100) < min($plancher, (float) $t->prix_km)
            )->count();

            $ecretees = $tranches->filter(fn ($t) => (float) $t->prix_km < $plancher)->count();

            $this->newLine();
            $this->line(sprintf(
                'Plancher de %s F : %d tranche(s) relevée(s) au-dessus du pourcentage.',
                $this->nombre($plancher), $releves
            ));

            if ($ecretees > 0) {
                // Le dire plutôt que de le faire en silence : sur ces tranches
                // le plancher ne s'applique pas entièrement, et le livreur y
                // touche moins que le montant demandé.
                $this->warn(sprintf(
                    '%d tranche(s) sont facturées au client MOINS que ce plancher : le versement y '
                    . 'est ramené au prix client, pour ne pas livrer à perte.',
                    $ecretees
                ));
            }
        }

        $this->newLine();
        $this->info(sprintf('%d tranches enregistrées pour ce livreur.', count($tranches)));

        return self::SUCCESS;
    }

    /**
     * CE QUE LA GRILLE CHANGERAIT, POUR LUI ET POUR L'ENTREPRISE.
     *
     * Choisir une part au jugé revient à redistribuer de l'argent réel sans
     * savoir dans quel sens. Cette comparaison met en face, tranche par
     * tranche, ce que le livreur touche AUJOURD'HUI avec son mode de
     * tarification, et ce qu'il toucherait à la part demandée.
     *
     * La course représentative d'une tranche prend le MILIEU de ses bornes :
     * une tranche 20-60 t sur 0-20 km est jugée sur 40 t et 10 km. C'est une
     * approximation, dite comme telle — mais elle permet de comparer deux
     * modes de calcul qui n'ont sinon aucun point commun.
     */
    private function comparer(Livreur $livreur): int
    {
        $part = $this->part($livreur);

        if ($part === null) {
            return self::FAILURE;
        }

        if ($this->uniteDemandee() === false) {
            return self::FAILURE;
        }

        $tranches = $this->tranchesClient();

        if ($tranches->isEmpty()) {
            $this->error('Aucune tranche client a comparer pour ce perimetre.');

            return self::FAILURE;
        }

        $capacite = (float) (\App\Models\Configuration::first()->tonne_moyenne ?? 0);

        $this->info(sprintf(
            'Livreur %s — aujourd\'hui %s, comparé à une grille à %s %%',
            $livreur->user->nom_prenoms ?? ('#' . $livreur->id),
            $this->tarifActuel($livreur),
            $this->nombre($part)
        ));
        $this->line('Course représentative : le milieu de chaque tranche.');
        $this->newLine();

        [$courses, $classees, $totalLivraisons, $ecartees, $repartition] = $this->option('reel')
            ? $this->coursesParTranche($tranches)
            : [[], 0, 0, [], []];

        // Ponderer par zero course donnerait un tableau entierement a zero :
        // on le dit, et on revient a la comparaison non ponderee plutot que de
        // rendre un ecran vide de sens.
        $pondere = $this->option('reel') && $classees > 0;

        if ($this->option('reel')) {
            $this->line(sprintf(
                '%d livraison(s) rattachée(s) à une tranche, sur %d examinée(s).',
                $classees, $totalLivraisons
            ));

            // POURQUOI les autres n'ont pas compté. « Aucune course » peut
            // vouloir dire « nous n'en faisons pas » ou « la donnée manque » :
            // ce sont deux conclusions opposées, et le chiffre seul ne permet
            // pas de choisir entre elles.
            $motifs = [
                'autre_unite'   => 'portent une autre unité',
                'sans_unite'    => 'sans produit ni unité identifiable',
                'sans_distance' => 'sans distance enregistrée',
                'hors_bornes'   => 'hors des bornes de la grille',
            ];

            foreach ($motifs as $cle => $libelle) {
                if (($ecartees[$cle] ?? 0) > 0) {
                    $this->line(sprintf('   %d %s', $ecartees[$cle], $libelle));
                }
            }

            // QUELLES UNITÉS SONT RÉELLEMENT LIVRÉES.
            //
            // C'est la réponse à « par quelle grille commencer ». Sans elle, on
            // règle au jugé l'unité dont on parle, sans savoir si elle
            // représente la moitié de l'activité ou aucune course.
            if (!empty($repartition)) {
                $this->newLine();
                $this->line('Vos livraisons, par unité :');

                foreach ($repartition as $libelle => $nombre) {
                    $this->line(sprintf('   %-22s %d course(s)', $libelle, $nombre));
                }
            }

            if ($classees === 0) {
                $this->newLine();
                $this->warn('Comparaison NON pondérée : aucune course de référence.');
            }

            $this->newLine();
        }

        $lignes = [];
        $gagne = 0;
        $perd  = 0;
        $totalAujourdhui = 0.0;
        $totalGrille     = 0.0;
        $totalClient     = 0.0;

        foreach ($tranches as $t) {
            $quantite = ((float) $t->unite_min + (float) $t->unite_max) / 2;
            $distance = ((float) $t->distance_min_km + (float) $t->distance_max_km) / 2;
            $voyages  = Livreur::nombreDeVoyages($quantite, $capacite, $capacite, $t->unite_produit_id);

            $aujourdhui = (float) $livreur->tarificationLivraison($distance, 0, $voyages)['total'];
            $client     = (float) $t->prix_km;
            $grille     = $this->montantLivreur($client, $part, $this->plancher());

            // Ce que DALAKOUN garde, dans un cas comme dans l'autre.
            $margeAvant = $client - $aujourdhui;
            $margeApres = $client - $grille;

            // Une tranche jamais empruntée ne pèse rien dans la décision.
            $poids = $pondere ? (float) ($courses[$t->id] ?? 0) : 1.0;

            $totalAujourdhui += $aujourdhui * $poids;
            $totalGrille     += $grille * $poids;
            $totalClient     += $client * $poids;

            if ($margeApres > $margeAvant) {
                $gagne++;
            } elseif ($margeApres < $margeAvant) {
                $perd++;
            }

            $lignes[] = [
                $t->uniteProduit->libelle ?? $t->unite_produit_id,
                $pondere ? (string) ($courses[$t->id] ?? 0) : '—',
                // L'unité de la tranche, et non « t » : dans une tranche Sac,
                // « 100 » désigne cent SACS. Écrire « 100 t » faisait lire dix
                // fois la charge réelle, sur l'écran même qui sert à décider.
                $this->nombre($quantite) . ' ' . ($t->uniteProduit->abreviation ?? '')
                    . ' / ' . $this->nombre($distance) . ' km',
                $this->nombre($client),
                $this->nombre($aujourdhui),
                $this->nombre($grille),
                $this->nombre($margeAvant),
                $this->nombre($margeApres),
            ];
        }

        $this->table(
            ['Unité', 'Courses', 'Course type', 'Client', 'Livreur auj.', 'Livreur grille', 'DALAKOUN auj.', 'DALAKOUN grille'],
            array_slice($lignes, 0, 12)
        );

        if (count($lignes) > 12) {
            $this->line(sprintf('… et %d autres tranches.', count($lignes) - 12));
        }

        $this->newLine();
        $this->line(sprintf(
            'Sur %d tranches : DALAKOUN gagne sur %d, perd sur %d.',
            count($lignes), $gagne, $perd
        ));
        $this->line(sprintf(
            'Cumul — le livreur toucherait %s F au lieu de %s F ; DALAKOUN garderait %s F au lieu de %s F.',
            $this->nombre($totalGrille),
            $this->nombre($totalAujourdhui),
            $this->nombre($totalClient - $totalGrille),
            $this->nombre($totalClient - $totalAujourdhui)
        ));

        // LA PART NEUTRE : celle a laquelle le livreur toucherait exactement ce
        // qu il touche deja. C est le seul repere qui dise si la part choisie
        // est une hausse ou une baisse, et de combien.
        if ($totalClient > 0) {
            $neutre = $totalAujourdhui / $totalClient * 100;

            $this->newLine();
            $this->line(sprintf(
                'Part NEUTRE : %s %% — a ce taux, le livreur toucherait ce qu il touche aujourd hui.',
                $this->nombre(round($neutre, 1))
            ));

            $ecart = $part - $neutre;

            if (abs($ecart) >= 0.1) {
                $this->line(sprintf(
                    'A %s %%, sa remuneration %s de %s %% par rapport a aujourd hui.',
                    $this->nombre($part),
                    $ecart > 0 ? 'AUGMENTE' : 'BAISSE',
                    $this->nombre(round(abs($ecart) / max($neutre, 0.0001) * 100, 1))
                ));
            }
        }

        $this->partNeutreParUnite($livreur);

        $this->newLine();
        $this->line('Essayez plusieurs parts avant de trancher, puis --apply pour enregistrer.');

        return self::SUCCESS;
    }

    /**
     * Les tranches génériques de la grille client, éventuellement limitées à
     * une seule unité.
     *
     * Le filtre existe parce que la part juste n'est pas la même partout : sur
     * les tonnes, ce livreur touche déjà près d'un tiers du transport ; sur les
     * sacs et les barres, à peine 6 %. Appliquer un taux unique multiplierait sa
     * paie par six sur ces unités-là, du jour au lendemain.
     *
     * Il permet donc de commencer par le cœur de l'activité — le gravier, en
     * tonnes — et de traiter le reste quand la grille client de ces unités aura
     * été vérifiée.
     */
    private function tranchesClient()
    {
        $unite = $this->uniteDemandee();

        if ($unite === false) {
            return collect();
        }

        // Meme perimetre que le service : catalogue general, hors tarifs de
        // ville. L'option --unite ne fait que le restreindre.
        return CoutLivraison::with('uniteProduit')
            ->where(fn ($q) => $q->whereNull('ville_id')->orWhere('ville_id', '<=', 0))
            ->when($unite, fn ($q) => $q->where('unite_produit_id', $unite->id))
            ->orderBy('unite_produit_id')->orderBy('unite_min')->orderBy('distance_min_km')
            ->get();
    }

    /**
     * COMBIEN DE COURSES RÉELLES SONT TOMBÉES DANS CHAQUE TRANCHE.
     *
     * Sans cela, la comparaison pèse toutes les tranches pareil : celle des
     * 10 000 sacs, qui n'arrive peut-être jamais, compte autant que celle des
     * 200 sacs qui part toutes les semaines. La moyenne qui en sort ne décrit
     * aucune activité réelle — et c'est sur elle qu'on choisirait un taux.
     *
     * On compte donc les livraisons déjà effectuées, et on pondère.
     *
     * Renvoie [id de tranche => nombre de courses], et le total.
     */
    private function coursesParTranche($tranches): array
    {
        // La distance n'est PAS exigée d'entrée : une livraison sans distance
        // est une information en soi — elle dit que la donnée manque, pas que
        // la course n'a pas eu lieu. L'écarter en silence ferait conclure « nous
        // n'avons jamais livré de sacs » là où il faudrait lire « nos anciennes
        // livraisons n'ont pas de distance enregistrée ».
        // SEULES LES COURSES QUI ONT UN LIVREUR.
        //
        // Un « traitement sans livraison » cree aussi une ligne : le client
        // vient chercher lui-meme, aucun livreur n intervient, ni distance ni
        // remuneration ne sont ecrites. Les compter ici les ferait passer pour
        // des livraisons a la donnee manquante, et gonflerait le denominateur
        // d une decision qui ne concerne que le transport.
        $livraisons = \App\Models\Livraison::with('detailCommande.produit')
            ->where('statut', \Help::$STATUT_ACTIF)
            ->whereNotNull('livreur_id')
            ->get();

        $compte   = [];
        $classees = 0;

        $ecartees = [
            'sans_unite'    => 0,
            'sans_distance' => 0,
            'autre_unite'   => 0,
            'hors_bornes'   => 0,
        ];

        $unitesVisees = collect($tranches)->pluck('unite_produit_id')->unique()->all();

        foreach ($livraisons as $l) {
            $unite = $this->uniteDeLaLivraison($l);

            if (!$unite) {
                $ecartees['sans_unite']++;
                continue;
            }

            if (!in_array((int) $unite, array_map('intval', $unitesVisees), true)) {
                $ecartees['autre_unite']++;
                continue;
            }

            if ($l->distance_km === null) {
                $ecartees['sans_distance']++;
                continue;
            }

            $qte      = (float) ($l->qte ?? 0);
            $distance = (float) $l->distance_km;
            $trouvee  = false;

            foreach ($tranches as $t) {
                if ((int) $t->unite_produit_id !== (int) $unite) {
                    continue;
                }

                if ($qte >= (float) $t->unite_min && $qte <= (float) $t->unite_max
                    && $distance >= (float) $t->distance_min_km && $distance <= (float) $t->distance_max_km) {
                    $compte[$t->id] = ($compte[$t->id] ?? 0) + 1;
                    $classees++;
                    $trouvee = true;
                    break;
                }
            }

            if (!$trouvee) {
                $ecartees['hors_bornes']++;
            }
        }

        return [$compte, $classees, $livraisons->count(), $ecartees, $this->repartition($livraisons)];
    }

    /**
     * L'UNITÉ D'UNE LIVRAISON — et il faut savoir d'où elle vient.
     *
     * `detail_commande_id` ne pointe pas toujours vers une ligne de commande :
     * sur une location, il porte l'identifiant d'un detail_location ; sur une
     * demande de livraison, c'est `detail_livraison_id` qui compte. Chercher
     * partout dans detail_commande ne renvoie donc pas « rien » — cela renvoie
     * la ligne de commande qui porte par hasard le même numéro, et son produit
     * n'a aucun rapport.
     *
     * Le même aiguillage que « Bénéfices sur les livraisons », pour que les
     * deux écrans comptent les mêmes courses.
     */
    private function uniteDeLaLivraison(\App\Models\Livraison $l): ?int
    {
        if ($l->provenance === \Help::$LIVRAISON) {
            $detail = \App\Models\DetailLivraison::find($l->detail_livraison_id);

            return $detail?->unite_produit_id ?: null;
        }

        if ($l->provenance === \Help::$LOCATION) {
            $detail = \App\Models\DetailLocation::find($l->detail_commande_id);

            return $detail?->produit?->unite_produit_id ?: null;
        }

        $detail = \App\Models\DetailCommande::find($l->detail_commande_id);

        return $detail?->produit?->unite_produit_id ?: null;
    }

    /** Combien de livraisons par unité, toutes tranches confondues. */
    private function repartition($livraisons): array
    {
        $par = [];

        foreach ($livraisons as $l) {
            $unite   = $this->uniteDeLaLivraison($l);
            $libelle = $unite
                ? (UniteProduit::find($unite)->libelle ?? ('unité #' . $unite))
                : 'non identifiable';

            $par[$libelle] = ($par[$libelle] ?? 0) + 1;
        }

        arsort($par);

        return $par;
    }

    /** L'unité demandée : null si aucune, false si introuvable. */
    private function uniteDemandee()
    {
        $demande = $this->option('unite');

        if (!$demande) {
            return null;
        }

        $unite = is_numeric($demande)
            ? UniteProduit::find((int) $demande)
            : UniteProduit::whereRaw('UPPER(abreviation) = ?', [strtoupper(trim($demande))])->first();

        if (!$unite) {
            $this->error('Unité inconnue : ' . $demande);
            $this->line('Unités disponibles : ' . UniteProduit::orderBy('libelle')->get()
                ->map(fn ($u) => $u->abreviation . ' (' . $u->libelle . ')')->implode(', '));

            return false;
        }

        return $unite;
    }

    /**
     * LA PART NEUTRE, UNITÉ PAR UNITÉ.
     *
     * Un seul chiffre pour tout le catalogue mélange des situations opposées et
     * conduit à choisir un taux qui ne convient à aucune.
     */
    private function partNeutreParUnite(Livreur $livreur): void
    {
        $capacite = (float) (\App\Models\Configuration::first()->tonne_moyenne ?? 0);

        $par = [];

        foreach ($this->tranchesClient() as $t) {
            $libelle  = $t->uniteProduit->libelle ?? ('unité #' . $t->unite_produit_id);
            $quantite = ((float) $t->unite_min + (float) $t->unite_max) / 2;
            $distance = ((float) $t->distance_min_km + (float) $t->distance_max_km) / 2;
            $voyages  = Livreur::nombreDeVoyages($quantite, $capacite, $capacite, $t->unite_produit_id);

            $par[$libelle]['abreviation'] = $t->uniteProduit->abreviation ?? '';
            $par[$libelle]['client'] = ($par[$libelle]['client'] ?? 0) + (float) $t->prix_km;
            $par[$libelle]['auj']    = ($par[$libelle]['auj'] ?? 0)
                + (float) $livreur->tarificationLivraison($distance, 0, $voyages)['total'];
        }

        if (count($par) <= 1) {
            return;
        }

        $this->newLine();
        $this->line('Part NEUTRE par unité — le taux auquel il toucherait ce qu il touche deja :');

        $this->table(
            ['Unité', 'Abrév.', 'Client', 'Livreur auj.', 'Part neutre'],
            collect($par)->map(fn ($v, $libelle) => [
                $libelle,
                $v['abreviation'],
                $this->nombre($v['client']),
                $this->nombre($v['auj']),
                $v['client'] > 0 ? $this->nombre(round($v['auj'] / $v['client'] * 100, 1)) . ' %' : '—',
            ])->values()->all()
        );

        $this->line('Traitez une unité à la fois : --unite=T pour ne poser que les tonnes.');
    }

    private function livreur(): ?Livreur
    {
        $id = $this->argument('livreur');

        if ($id) {
            $livreur = Livreur::with('user')->find($id);

            if ($livreur) {
                return $livreur;
            }

            // Un identifiant faux et une commande sans argument posent la meme
            // question — « lequel ? ». Repondre « Livreur introuvable » et
            // s arreter la oblige a aller chercher les identifiants ailleurs.
            $this->error("Aucun livreur ne porte l'identifiant " . $id . '.');
            $this->newLine();
        }

        return $this->listerEtSortir();
    }

    /** Affiche les livreurs disponibles, avec l etat de leur grille. */
    private function listerEtSortir(): ?Livreur
    {
        $livreurs = Livreur::with('user')->orderBy('id')->get();

        if ($livreurs->isEmpty()) {
            $this->error("Aucun livreur n'est enregistre.");

            return null;
        }

        $this->line('Livreurs disponibles :');

        $this->table(
            ['#', 'Nom', 'Grille', 'Part', 'Tarif actuel'],
            $livreurs->map(fn (Livreur $l) => [
                $l->id,
                mb_substr($l->user->nom_prenoms ?? '?', 0, 28),
                $l->tranchesRenseignees() > 0 ? $l->tranchesRenseignees() . ' tranche(s)' : '—',
                $l->part_grille ? $this->nombre($l->part_grille) . ' %' : '—',
                $this->tarifActuel($l),
            ])->all()
        );

        $premier = $livreurs->first();

        $this->line('Exemple : php artisan livreur:grille ' . $premier->id . ' --part=60');

        return null;
    }

    /** Comment ce livreur est paye aujourd hui, en une ligne. */
    private function tarifActuel(Livreur $livreur): string
    {
        return match ($livreur->mode_tarification) {
            'km'    => $this->nombre($livreur->tarif_km) . ' F/km',
            'mixte' => $this->nombre($livreur->tarif_forfait_base) . ' F + '
                       . $this->nombre($livreur->tarif_km) . ' F/km',
            default => $this->nombre($livreur->cout_livraison) . ' F forfait',
        };
    }

    private function part(Livreur $livreur): ?float
    {
        $part = $this->option('part') !== null
            ? (float) $this->option('part')
            : (float) ($livreur->part_grille ?? 0);

        if ($part <= 0 || $part >= 100) {
            $this->error('La part du livreur doit être comprise entre 1 et 99 : --part=60');

            return null;
        }

        return $part;
    }

    private function vider(Livreur $livreur): int
    {
        $nb = CoutLivraisonLivreur::where('livreur_id', $livreur->id)->count();

        if ($nb === 0) {
            $this->line('Ce livreur n\'a aucune grille.');

            return self::SUCCESS;
        }

        if (!$this->option('apply')) {
            $this->warn(sprintf('Simulation : %d tranches seraient supprimées. Relancez avec --apply.', $nb));

            return self::SUCCESS;
        }

        CoutLivraisonLivreur::where('livreur_id', $livreur->id)->delete();
        $livreur->update(['part_grille' => null]);

        $this->info(sprintf('%d tranches supprimées. Ce livreur repasse sur son tarif habituel.', $nb));

        return self::SUCCESS;
    }

    /**
     * LE MONTANT VERSÉ AU LIVREUR POUR UNE TRANCHE.
     *
     * Le pourcentage suffit sur les gros chargements, mais pas sur les petits :
     * 40 % d'une course à 4 000 F font 1 600 F, quand ce livreur en touche déjà
     * 2 500. Appliquer le taux nu reviendrait à lui annoncer une baisse de 36 %
     * sur les seules courses qu'il fait réellement — toutes les siennes tombent
     * dans la première tranche.
     *
     * Le plancher garantit un minimum par course. Il ne peut jamais dépasser ce
     * que le client paie : au-delà, ce ne serait plus une marge réduite mais
     * une perte, et l'entreprise paierait pour livrer.
     */
    private function montantLivreur(float $prixClient, float $part, ?float $plancher): float
    {
        // LE CALCUL VIT DANS LE SERVICE, ET NULLE PART AILLEURS.
        //
        // Le back-office remplit desormais la meme grille : deux copies de
        // cette regle auraient diverge au premier ajustement, sur de l'argent
        // reellement verse.
        return \App\Services\GrilleLivreurGenerateur::montantLivreur(
            $prixClient, $part, $plancher);
    }

    /** Le plancher demandé, ou null. */
    private function plancher(): ?float
    {
        $valeur = $this->option('plancher');

        return ($valeur === null || $valeur === '') ? null : max(0, (float) $valeur);
    }

    private function nombre($valeur): string
    {
        return rtrim(rtrim(number_format((float) $valeur, 2, ',', ' '), '0'), ',');
    }
}
