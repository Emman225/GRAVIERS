@extends('client.main')
@section('title','Centre d\'aide')

@section('cssPart')
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/pages-institutionnelles.css') }}">
@endsection

@section('content')
@include('client.navMobile')

<main class="page-inst">

    @include('institutionnel._entete', [
        'icone'    => 'support_agent',
        'chip'     => 'Assistance',
        'titre'    => "Centre <em>d'aide</em>",
        'accroche' => 'Les réponses aux questions les plus courantes sur la commande,
                       le paiement, la livraison et votre compte.',
    ])

    <div class="page-inst__corps">

        <div class="page-inst__carte">
            <h2><i class="material-icons md-shopping_cart"></i> Commander</h2>
            <div class="page-inst__faq">
                <details>
                    <summary>Faut-il un compte pour commander ?</summary>
                    <p>Vous pouvez parcourir le catalogue librement, mais la commande demande un compte :
                       il nous faut une adresse de livraison et un moyen de vous joindre. La création
                       est gratuite et prend une minute.</p>
                </details>
                <details>
                    <summary>Quelle différence entre un devis et une commande ?</summary>
                    <p>Le devis fige un prix sans vous engager : vous pouvez le conserver, le faire
                       valider en interne, puis le transformer en commande quand vous le souhaitez.
                       Une commande, elle, déclenche la préparation de la marchandise.</p>
                </details>
                <details>
                    <summary>Puis-je modifier ou annuler une commande ?</summary>
                    <p>Tant qu'elle n'est pas préparée, oui : depuis « Mes commandes », faites une
                       demande d'annulation. Elle est examinée par nos équipes, et vous en voyez le
                       résultat — accord ou refus motivé — dans votre espace.</p>
                </details>
                <details>
                    <summary>À quoi servent les points de fidélité ?</summary>
                    <p>Chaque commande vous en rapporte. Ils se convertissent en réduction sur une
                       commande ultérieure, que vous appliquez au moment de valider votre panier.</p>
                </details>
            </div>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-credit_card"></i> Payer</h2>
            <div class="page-inst__faq">
                <details>
                    <summary>Quels moyens de paiement acceptez-vous ?</summary>
                    <p>Le paiement en ligne par mobile money, et le règlement en agence. Le choix se
                       fait au moment de valider la commande.</p>
                </details>
                <details>
                    <summary>J'ai payé mais ma commande n'apparaît pas comme réglée.</summary>
                    <p>La confirmation d'un paiement en ligne peut demander quelques instants. Si
                       l'écart persiste, contactez-nous en indiquant la référence de votre paiement :
                       nous vérifions auprès de l'opérateur et régularisons.</p>
                </details>
                <details>
                    <summary>Qu'est-ce qu'un compte à terme ?</summary>
                    <p>Réservé aux entreprises, il permet d'être livré puis de régler dans un délai
                       convenu, dans la limite d'un plafond de crédit accordé. La demande se fait
                       depuis votre espace client et fait l'objet d'un examen.</p>
                </details>
                <details>
                    <summary>Où retrouver mes factures ?</summary>
                    <p>Dans votre espace client, rubrique « Mes paiements » : chaque facture est
                       consultable et téléchargeable en PDF.</p>
                </details>
            </div>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-local_shipping"></i> Être livré</h2>
            <div class="page-inst__faq">
                <details>
                    <summary>Comment est calculé le coût de la livraison ?</summary>
                    <p>Selon la distance à parcourir et le tonnage à transporter. Le montant vous est
                       affiché avant validation de la commande.
                       <a href="{{ route('infosLivraisons') }}">Voir le détail</a>.</p>
                </details>
                <details>
                    <summary>Puis-je venir chercher la marchandise moi-même ?</summary>
                    <p>Oui, en choisissant le retrait sur place : la marchandise est préparée et tenue
                       à votre disposition en agence, sans frais de transport.</p>
                </details>
                <details>
                    <summary>Comment savoir où en est ma livraison ?</summary>
                    <p>La rubrique « Mes commandes » indique l'état exact : en attente, en traitement,
                       en cours de livraison, ou livrée.</p>
                </details>
            </div>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-build"></i> Après la livraison</h2>
            <div class="page-inst__faq">
                <details>
                    <summary>Le produit livré ne me convient pas.</summary>
                    <p>Ouvrez une demande depuis « Retour de produits ». Indiquez le motif : nos équipes
                       l'examinent et vous répondent dans votre espace client.</p>
                </details>
                <details>
                    <summary>J'ai un problème avec un produit déjà livré.</summary>
                    <p>Ouvrez un ticket de service après-vente depuis votre espace. Il est confié à un
                       responsable, et vous suivez son traitement étape par étape.</p>
                </details>
            </div>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-account_circle"></i> Mon compte</h2>
            <div class="page-inst__faq">
                <details>
                    <summary>J'ai oublié mon mot de passe.</summary>
                    <p>Depuis la page de connexion, cliquez sur « Mot de passe oublié ». Vous recevrez
                       un lien de réinitialisation par courriel.</p>
                </details>
                <details>
                    <summary>Je n'ai pas reçu le code de confirmation à l'inscription.</summary>
                    <p>Vérifiez le dossier « courrier indésirable ». Si le code n'arrive toujours pas,
                       recommencez l'inscription avec la même adresse : un nouveau code vous sera envoyé.</p>
                </details>
                <details>
                    <summary>Comment modifier mes coordonnées ?</summary>
                    <p>Dans votre espace client, rubrique « Mon profil ».</p>
                </details>
            </div>
        </div>

        <div class="page-inst__cta">
            <h2><i class="material-icons md-forum"></i> Vous n'avez pas trouvé votre réponse ?</h2>
            <p>Écrivez-nous en décrivant votre situation : nous vous répondons et suivons votre demande.</p>
            <a class="page-inst__bouton" href="{{ route('contact') }}">Nous contacter</a>
            <a class="page-inst__bouton page-inst__bouton--clair" href="{{ route('infosLivraisons') }}">Infos livraisons</a>
        </div>

    </div>
</main>

@endsection
