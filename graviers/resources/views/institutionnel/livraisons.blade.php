@extends('client.main')
@section('title','Informations sur les livraisons')

@section('cssPart')
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/pages-institutionnelles.css') }}">
@endsection

@section('content')
@include('client.navMobile')

<main class="page-inst">

    @include('institutionnel._entete', [
        'icone'    => 'local_shipping',
        'chip'     => 'Livraison',
        'titre'    => 'Informations sur <em>les livraisons</em>',
        'accroche' => 'Comment vos matériaux arrivent sur votre chantier : conditionnement,
                       délais, coût du transport et suivi de la commande.',
    ])

    <div class="page-inst__corps">

        <div class="page-inst__carte">
            <h2><i class="material-icons md-inventory_2"></i> Comment vos matériaux sont livrés</h2>
            <p>Selon le produit commandé, la livraison se fait sous l'un des conditionnements suivants :</p>
            <div class="page-inst__grille">
                @forelse ($typesLivraison as $type)
                    <div class="page-inst__tuile">
                        <i class="material-icons md-local_shipping"></i>
                        <strong>{{ $type->libelle }}</strong>
                        <span>
                            @if (stripos($type->libelle, 'vrac') !== false)
                                Déchargement direct par camion-benne, pour le sable, le gravier et le tout-venant.
                            @elseif (stripos($type->libelle, 'big') !== false)
                                Sacs de grande contenance, manipulables au chariot ou à la grue.
                            @else
                                Sacs unitaires, pour le ciment et les petites quantités.
                            @endif
                        </span>
                    </div>
                @empty
                    <div class="page-inst__tuile">
                        <i class="material-icons md-local_shipping"></i>
                        <strong>Livraison par camion</strong>
                        <span>Le conditionnement est précisé au moment de la commande.</span>
                    </div>
                @endforelse
            </div>
            <p class="mt-3">
                Vous pouvez également choisir le <strong>retrait sur place</strong> : la marchandise est
                préparée et tenue à votre disposition en agence, sans frais de transport.
            </p>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-timeline"></i> Le parcours d'une livraison</h2>
            <ol class="page-inst__etapes">
                <li>
                    <strong>Commande enregistrée</strong>
                    Vous choisissez vos produits, votre adresse de livraison et votre mode de règlement.
                </li>
                <li>
                    <strong>Préparation en agence</strong>
                    Une fois la commande réglée ou validée, nos équipes préparent la marchandise et
                    l'affectent à un livreur.
                </li>
                <li>
                    <strong>Livraison en cours</strong>
                    Le camion part vers votre chantier. La commande passe en « en cours de livraison » :
                    tant que le camion n'est pas arrivé, elle n'est jamais comptée comme livrée.
                </li>
                <li>
                    <strong>Réception et code de confirmation</strong>
                    À l'arrivée, un <strong>code de livraison</strong> vous est demandé. Il atteste que
                    vous avez bien reçu la marchandise et déclenche la clôture de la commande.
                </li>
            </ol>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-payments"></i> Coût du transport</h2>
            <p>
                Le coût de la livraison n'est pas forfaitaire : il est calculé à partir de la
                <strong>distance</strong> entre l'agence de départ et l'adresse de livraison, et du
                <strong>tonnage</strong> à transporter. Le montant exact vous est affiché
                <strong>avant validation</strong> de la commande, sur le récapitulatif, et repris sur la facture.
            </p>
            <ul>
                <li>Aucun frais de transport n'est ajouté après la commande.</li>
                <li>Le retrait sur place n'entraîne aucun frais de livraison.</li>
                <li>La TVA en vigueur s'applique au montant net de la commande, remise déduite.</li>
            </ul>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-visibility"></i> Suivre votre commande</h2>
            <p>
                Depuis votre espace client, la rubrique <strong>« Mes commandes »</strong> indique à
                tout moment où en est chaque commande : en attente, en traitement, en cours de
                livraison ou livrée. La rubrique <strong>« Demande de livraisons »</strong> vous permet
                par ailleurs de faire transporter des matériaux que vous possédez déjà.
            </p>
            <p class="page-inst__note">
                <strong>Livraison partielle :</strong> lorsqu'une commande porte sur un gros volume,
                elle peut être livrée en plusieurs passages. Chaque passage est enregistré séparément,
                et le solde restant à livrer reste visible dans votre espace.
            </p>
        </div>

        <div class="page-inst__carte page-inst__faq">
            <h2><i class="material-icons md-help_outline"></i> Questions fréquentes</h2>
            <details>
                <summary>Puis-je modifier l'adresse de livraison après la commande ?</summary>
                <p>Tant que la commande n'est pas affectée à un livreur, contactez-nous : nous pouvons
                   la corriger. Une fois le camion parti, la livraison se fait à l'adresse enregistrée.</p>
            </details>
            <details>
                <summary>Que se passe-t-il si personne n'est présent à la réception ?</summary>
                <p>Le livreur ne peut pas clôturer la livraison sans le code de confirmation. La
                   commande reste « en cours de livraison » et un nouveau passage est organisé.</p>
            </details>
            <details>
                <summary>La marchandise ne correspond pas à ma commande, que faire ?</summary>
                <p>Ouvrez une demande depuis <strong>« Retour de produits »</strong> dans votre espace
                   client, ou un <strong>ticket de service après-vente</strong> si le produit a déjà
                   été livré. Votre demande est traitée par nos équipes et vous en suivez l'avancement.</p>
            </details>
            <details>
                <summary>Livrez-vous en dehors d'Abidjan ?</summary>
                <p>Les zones desservies dépendent de l'agence la plus proche. Indiquez votre adresse
                   lors de la commande : si elle n'est pas couverte, contactez-nous pour étudier
                   une solution.</p>
            </details>
        </div>

        <div class="page-inst__cta">
            <h2><i class="material-icons md-support_agent"></i> Une question sur une livraison ?</h2>
            <p>Notre équipe vous répond et suit votre dossier jusqu'à la réception de la marchandise.</p>
            <a class="page-inst__bouton" href="{{ route('contact', ['sujet' => 'Question sur une livraison']) }}">Nous écrire</a>
            <a class="page-inst__bouton page-inst__bouton--clair" href="{{ route('client.index') }}">Voir les produits</a>
        </div>

    </div>
</main>

@endsection
