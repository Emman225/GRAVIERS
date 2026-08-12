@extends('client.main')
@section('title','Politique et confidentialité')

@section('cssPart')
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/pages-institutionnelles.css') }}">
@endsection

@section('content')
@include('client.navMobile')

<main class="page-inst">

    @include('institutionnel._entete', [
        'icone'      => 'privacy_tip',
        'chip'       => 'Document légal',
        'titre'      => 'Politique <em>et confidentialité</em>',
        'accroche'   => 'Quelles données nous recueillons, pourquoi, combien de temps
                         nous les conservons, et quels droits vous pouvez exercer.',
        'miseAJour'  => true,
    ])

    <div class="page-inst__corps">

        <div class="page-inst__carte">
            <h2><i class="material-icons md-badge"></i> 1. Qui traite vos données</h2>
            <p>
                Le responsable du traitement est <strong>{{ $raisonSociale }}</strong>,
                exploitant de la plateforme accessible à l'adresse
                <a href="{{ route('client.index') }}">{{ request()->getHost() }}</a>.
            </p>
            <p>
                Pour toute question relative à vos données personnelles, écrivez à
                <a href="mailto:info@fneconnect.net">info@fneconnect.net</a> ou utilisez notre
                <a href="{{ route('contact') }}">formulaire de contact</a>.
            </p>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-storage"></i> 2. Les données que nous recueillons</h2>
            <p>Nous ne collectons que ce qui est nécessaire au fonctionnement du service :</p>
            <table class="page-inst__tableau">
                <thead>
                    <tr><th>Données</th><th>Quand</th><th>Pourquoi</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Nom, prénoms, adresse e-mail, numéro de téléphone</td>
                        <td>À la création de votre compte</td>
                        <td>Vous identifier, vous joindre au sujet de vos commandes</td>
                    </tr>
                    <tr>
                        <td>Adresse de livraison et localisation du chantier</td>
                        <td>À la commande ou à la demande de livraison</td>
                        <td>Acheminer la marchandise et calculer le coût du transport</td>
                    </tr>
                    <tr>
                        <td>Historique des commandes, devis, factures et règlements</td>
                        <td>Tout au long de la relation</td>
                        <td>Assurer le suivi, la facturation et les obligations comptables</td>
                    </tr>
                    <tr>
                        <td>Coordonnées d'entreprise (raison sociale, RCCM, NCC)</td>
                        <td>Pour un compte professionnel ou à terme</td>
                        <td>Établir des factures conformes et instruire une demande de crédit</td>
                    </tr>
                    <tr>
                        <td>Adresse e-mail seule</td>
                        <td>À l'inscription à la lettre d'information</td>
                        <td>Vous envoyer nos actualités, jusqu'à votre désinscription</td>
                    </tr>
                    <tr>
                        <td>Données de connexion (session, journal technique)</td>
                        <td>À chaque visite</td>
                        <td>Sécuriser l'accès à votre compte et diagnostiquer les incidents</td>
                    </tr>
                </tbody>
            </table>
            <p class="page-inst__note">
                <strong>Nous ne conservons aucune donnée bancaire.</strong> Les paiements en ligne sont
                traités par notre prestataire de paiement : votre numéro de carte ou de compte mobile
                money ne transite jamais par nos serveurs et n'y est jamais enregistré.
            </p>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-gavel"></i> 3. Sur quel fondement</h2>
            <ul>
                <li><strong>L'exécution du contrat</strong> : traiter et livrer vos commandes, éditer vos factures.</li>
                <li><strong>Une obligation légale</strong> : conserver les pièces comptables et fiscales.</li>
                <li><strong>Votre consentement</strong> : la lettre d'information, que vous pouvez retirer à tout moment.</li>
                <li><strong>Notre intérêt légitime</strong> : sécuriser la plateforme et prévenir les usages frauduleux.</li>
            </ul>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-share"></i> 4. Qui peut y accéder</h2>
            <p>Vos données ne sont ni vendues, ni louées, ni cédées à des fins publicitaires. Elles sont accessibles :</p>
            <ul>
                <li>à nos équipes internes, selon leur rôle et pour les seuls besoins de leur mission ;</li>
                <li>au livreur chargé de votre commande, pour la seule adresse de livraison ;</li>
                <li>à notre prestataire de paiement, pour la seule opération de règlement ;</li>
                <li>à l'administration fiscale et aux autorités, lorsque la loi l'exige.</li>
            </ul>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-schedule"></i> 5. Combien de temps</h2>
            <ul>
                <li><strong>Compte client</strong> : tant que le compte est actif.</li>
                <li><strong>Commandes, factures et règlements</strong> : pendant la durée légale de conservation des pièces comptables.</li>
                <li><strong>Lettre d'information</strong> : jusqu'à votre désinscription.</li>
                <li><strong>Journaux techniques</strong> : le temps nécessaire au diagnostic et à la sécurité.</li>
            </ul>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-verified_user"></i> 6. Vos droits</h2>
            <p>
                Conformément à la réglementation ivoirienne relative à la protection des données à
                caractère personnel, vous disposez d'un droit d'accès, de rectification, d'opposition
                et de suppression de vos données.
            </p>
            <ul>
                <li><strong>Consulter et corriger</strong> vos informations depuis la rubrique « Mon profil » de votre espace client.</li>
                <li><strong>Vous désinscrire</strong> de la lettre d'information en répondant à l'un de nos envois.</li>
                <li><strong>Demander la suppression</strong> de votre compte en nous écrivant. Les pièces comptables déjà émises sont conservées, la loi nous l'imposant.</li>
            </ul>
            <p>
                Nous répondons à toute demande dans un délai raisonnable. Si la réponse ne vous
                satisfait pas, vous pouvez saisir l'autorité de protection des données compétente.
            </p>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-cookie"></i> 7. Cookies</h2>
            <p>
                Le site dépose les cookies strictement nécessaires à son fonctionnement : maintien de
                votre session une fois connecté, conservation de votre panier d'une page à l'autre, et
                protection des formulaires contre la soumission frauduleuse. Ils ne servent ni au
                profilage ni à la publicité ciblée. Les refuser empêcherait la connexion et la commande.
            </p>
        </div>

        <div class="page-inst__carte">
            <h2><i class="material-icons md-lock"></i> 8. Sécurité</h2>
            <p>
                Les échanges avec le site sont chiffrés. Les mots de passe ne sont jamais stockés en
                clair. Les accès au back-office sont nominatifs et limités selon le rôle de chacun.
                Malgré ce soin, aucun système n'étant infaillible, nous vous invitons à choisir un mot
                de passe solide et à ne pas le réutiliser ailleurs.
            </p>
        </div>

        <div class="page-inst__cta">
            <h2><i class="material-icons md-alternate_email"></i> Une question sur vos données ?</h2>
            <p>Écrivez-nous : nous vous répondons et donnons suite à votre demande.</p>
            <a class="page-inst__bouton" href="{{ route('contact', ['sujet' => 'Données personnelles']) }}">Nous écrire</a>
            <a class="page-inst__bouton page-inst__bouton--clair" href="{{ route('termesConditions') }}">Termes &amp; conditions</a>
        </div>

    </div>
</main>

@endsection
