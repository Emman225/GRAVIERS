@php
    use Illuminate\Support\Carbon;

    // approuve : 0 = en attente, 1 = accordé, 2 = refusé.
    // (ClientController::demandeClientATerme écrit 0 ; UserController écrit 1 à
    //  l'approbation et 2 au refus.)
    $etat = $demande ? (int) $demande->approuve : null;

    // Libellés des documents, pour retrouver ce qui a été envoyé.
    $libellesDocs = [
        'rccm'     => 'RCCM / Registre de commerce',
        'bilan'    => 'Attestation de revenus / bilan',
        'piece_id' => "Pièce d'identité du dirigeant",
        'autre'    => 'Autre document',
    ];
    $documents = $demande && is_array($demande->documents_path) ? $demande->documents_path : [];
@endphp

@extends('client.main')
@section('title', 'Devenir client à terme')

@section('cssPart')
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-demande-terme.css?v=1.0') }}">
@endsection

@section('content')
    <main class="main">

        <div class="page-header breadcrumb-wrap">
            <div class="container">
                <div class="breadcrumb">
                    <a href="{{ route('client.index') }}" rel="nofollow"><i class="fi-rs-home mr-5"></i>Accueil</a>
                    <span></span> Mon compte
                    <span></span> Compte à terme
                </div>
            </div>
        </div>

        {{-- ===== BANDEAU ===== --}}
        <div class="dct-hero">
            <div class="dct-hero__inner">
                <span class="dct-hero__chip"><i class="fi-rs-credit-card"></i> Compte professionnel</span>
                <h1 class="dct-hero__title">
                    @if (!empty($reserveEntreprise))
                        Le compte à terme
                    @elseif ($etat === 1)
                        Vous êtes client à terme
                    @elseif ($etat === 0)
                        Votre demande est à l'étude
                    @elseif ($etat === 2)
                        Votre demande n'a pas été retenue
                    @else
                        Devenir client à terme
                    @endif
                </h1>
                <p class="dct-hero__subtitle">
                    Le compte à terme vous permet d'enlever vos produits immédiatement et de régler
                    la facture de façon échelonnée, dans la limite d'un plafond accordé.
                </p>
            </div>
        </div>

        <div class="container">
            <div class="dct-carte">

                @if (session('success'))
                    <div class="dct-carte__corps" style="padding-bottom:0;">
                        <div class="dct-alerte dct-alerte--succes" id="notify">{{ session('success') }}</div>
                    </div>
                @endif
                @if (session('error'))
                    <div class="dct-carte__corps" style="padding-bottom:0;">
                        <div class="dct-alerte dct-alerte--erreur" id="notify">{{ session('error') }}</div>
                    </div>
                @endif
                @if (session('info'))
                    <div class="dct-carte__corps" style="padding-bottom:0;">
                        <div class="dct-alerte dct-alerte--info" id="notify">{{ session('info') }}</div>
                    </div>
                @endif

                {{-- ================= DEMANDE ACCORDÉE ================= --}}
                @if ($etat === 1)
                    <div class="dct-carte__corps">
                        <div class="dct-etat">
                            <div class="dct-etat__icone dct-etat__icone--valide"><i class="fi-rs-check"></i></div>
                            <div class="dct-etat__titre">Votre compte à terme est actif</div>
                            <p class="dct-etat__texte">
                                Vous pouvez désormais commander et régler vos factures de manière échelonnée,
                                dans la limite des conditions accordées ci-dessous.
                            </p>

                            {{-- Les conditions accordées (plafond, délai) étaient enregistrées
                                 mais n'apparaissaient nulle part côté client : il ignorait
                                 jusqu'où il pouvait aller. --}}
                            <div class="dct-recap">
                                <div class="dct-recap__item">
                                    <div class="dct-recap__libelle">Plafond de crédit</div>
                                    <div class="dct-recap__valeur">
                                        {{ $demande->plafond_credit !== null
                                            ? number_format($demande->plafond_credit, 0, ',', ' ').' FCFA'
                                            : 'Non précisé' }}
                                    </div>
                                </div>
                                <div class="dct-recap__item">
                                    <div class="dct-recap__libelle">Délai de paiement</div>
                                    <div class="dct-recap__valeur">
                                        {{ $demande->delai_paiement ? $demande->delai_paiement.' jours' : 'Non précisé' }}
                                    </div>
                                </div>
                                <div class="dct-recap__item">
                                    <div class="dct-recap__libelle">Accordé le</div>
                                    <div class="dct-recap__valeur">
                                        {{ $demande->decided_at ? $demande->decided_at->isoFormat('LL') : '—' }}
                                    </div>
                                </div>
                            </div>

                            @if ($demande->commentaire_admin)
                                <div class="dct-encadre dct-encadre--succes">
                                    <p class="dct-encadre__titre">Message de notre équipe</p>
                                    <p>{{ $demande->commentaire_admin }}</p>
                                </div>
                            @endif

                            <a href="{{ route('client.index') }}" class="dct-bouton">
                                <i class="fi-rs-shopping-bag"></i> Continuer mes achats
                            </a>
                        </div>
                    </div>

                {{-- ================= DEMANDE EN ATTENTE ================= --}}
                @elseif ($etat === 0)
                    <div class="dct-carte__corps">
                        <div class="dct-etat">
                            <div class="dct-etat__icone dct-etat__icone--attente"><i class="fi-rs-clock"></i></div>
                            <div class="dct-etat__titre">Demande en cours d'examen</div>
                            <p class="dct-etat__texte">
                                Notre équipe étudie votre dossier. Vous recevrez un e-mail dès qu'une décision
                                sera prise. Aucune action n'est attendue de votre part.
                            </p>

                            <div class="dct-etapes">
                                <span class="dct-etape is-faite"><i class="fi-rs-check"></i> Demande envoyée</span>
                                <span class="dct-etape is-encours"><i class="fi-rs-clock"></i> Étude du dossier</span>
                                <span class="dct-etape"><i class="fi-rs-envelope"></i> Décision par e-mail</span>
                            </div>

                            {{-- L'écran n'affichait qu'un titre : le client ne savait plus
                                 ni quand il avait envoyé sa demande, ni ce qu'elle contenait. --}}
                            <div class="dct-recap">
                                <div class="dct-recap__item">
                                    <div class="dct-recap__libelle">Objet</div>
                                    <div class="dct-recap__valeur">{{ $demande->objet }}</div>
                                </div>
                                <div class="dct-recap__item">
                                    <div class="dct-recap__libelle">Envoyée le</div>
                                    <div class="dct-recap__valeur">
                                        {{ $demande->created_at?->isoFormat('LL') }}
                                    </div>
                                </div>
                                <div class="dct-recap__item">
                                    <div class="dct-recap__libelle">Pièces jointes</div>
                                    <div class="dct-recap__valeur">{{ count($documents) }}</div>
                                </div>
                            </div>

                            @if (count($documents))
                                <div class="dct-encadre dct-encadre--info">
                                    <p class="dct-encadre__titre">Documents transmis</p>
                                    <ul class="dct-docs">
                                        @foreach ($documents as $cle => $chemin)
                                            <li><i class="fi-rs-file"></i> {{ $libellesDocs[$cle] ?? ucfirst($cle) }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <a href="{{ route('client.monCompte') }}" class="dct-bouton dct-bouton--secondaire">
                                <i class="fi-rs-user"></i> Retour à mon compte
                            </a>
                        </div>
                    </div>

                {{-- ================= DEMANDE REFUSÉE ================= --}}
                @elseif ($etat === 2)
                    <div class="dct-carte__corps">
                        <div class="dct-etat">
                            <div class="dct-etat__icone dct-etat__icone--refus"><i class="fi-rs-cross-circle"></i></div>
                            <div class="dct-etat__titre">Demande non retenue</div>
                            <p class="dct-etat__texte">
                                Votre demande du {{ $demande->created_at?->isoFormat('LL') }} n'a pas pu aboutir.
                                Vous pouvez en soumettre une nouvelle après avoir tenu compte du motif ci-dessous.
                            </p>

                            {{-- Une demande refusée tombait dans la branche « en cours de
                                 traitement » : le client lisait que son dossier était à
                                 l'étude, ne voyait jamais le motif du refus, et ne pouvait
                                 pas refaire de demande — alors que le traitement de l'envoi
                                 l'autorise explicitement (approuve == 2). --}}
                            @if ($demande->motif_refus)
                                <div class="dct-encadre dct-encadre--refus">
                                    <p class="dct-encadre__titre">Motif communiqué par notre équipe</p>
                                    <p>{{ $demande->motif_refus }}</p>
                                </div>
                            @endif

                            <a href="#formulaire" class="dct-bouton">
                                <i class="fi-rs-refresh"></i> Soumettre une nouvelle demande
                            </a>
                        </div>
                    </div>
                @endif

                {{-- ============ RÉSERVÉ AUX ENTREPRISES ============ --}}
                {{-- Un particulier ne peut fournir ni RCCM ni bilan : il envoyait
                     jusqu'ici un dossier vide, que rien n'empêchait d'approuver.
                     On l'explique au lieu de lui présenter un formulaire
                     inutilisable. Le contrôle est aussi refait côté serveur. --}}
                @if (!empty($reserveEntreprise))
                    <div class="dct-carte__corps">
                        <div class="dct-etat">
                            <div class="dct-etat__icone dct-etat__icone--attente"><i class="fi-rs-building"></i></div>
                            <div class="dct-etat__titre">Une offre réservée aux professionnels</div>
                            <p class="dct-etat__texte">
                                Le compte à terme s'adresse aux entreprises : son ouverture repose sur un
                                registre de commerce et des états financiers, que votre compte particulier
                                ne permet pas de fournir.
                            </p>

                            <div class="dct-encadre dct-encadre--info">
                                <p class="dct-encadre__titre">Votre activité est professionnelle ?</p>
                                <p>
                                    Contactez-nous pour faire évoluer votre compte vers un profil entreprise.
                                    La demande de compte à terme vous sera alors ouverte.
                                </p>
                            </div>

                            <a href="{{ route('contact') }}" class="dct-bouton">
                                <i class="fi-rs-headset"></i> Nous contacter
                            </a>
                            <a href="{{ route('client.monCompte') }}" class="dct-bouton dct-bouton--secondaire">
                                <i class="fi-rs-user"></i> Retour à mon compte
                            </a>
                        </div>
                    </div>
                @endif

                {{-- ================= FORMULAIRE ================= --}}
                {{-- Affiché quand aucune demande n'existe, et après un refus. --}}
                @if (empty($reserveEntreprise) && ($etat === null || $etat === 2))
                    <div class="dct-carte__entete" id="formulaire">
                        <h2 class="dct-carte__titre">
                            {{ $etat === 2 ? 'Nouvelle demande' : 'Formulaire de demande' }}
                        </h2>
                        <p class="dct-carte__aide">
                            Renseignez votre demande et joignez les pièces justificatives.
                            Les champs marqués d'une <span style="color:#dc2626;">*</span> sont obligatoires.
                        </p>
                    </div>

                    <div class="dct-carte__corps">

                        @if ($etat === null)
                            <div class="dct-avantages">
                                <div class="dct-avantage">
                                    <div class="dct-avantage__icone"><i class="fi-rs-shopping-cart"></i></div>
                                    <div>
                                        <p class="dct-avantage__titre">Enlevez maintenant</p>
                                        <p class="dct-avantage__texte">Vos produits sont livrés sans attendre le règlement complet.</p>
                                    </div>
                                </div>
                                <div class="dct-avantage">
                                    <div class="dct-avantage__icone"><i class="fi-rs-calendar"></i></div>
                                    <div>
                                        <p class="dct-avantage__titre">Payez à votre rythme</p>
                                        <p class="dct-avantage__texte">Réglez vos factures de façon échelonnée, selon le délai accordé.</p>
                                    </div>
                                </div>
                                <div class="dct-avantage">
                                    <div class="dct-avantage__icone"><i class="fi-rs-shield-check"></i></div>
                                    <div>
                                        <p class="dct-avantage__titre">Plafond dédié</p>
                                        <p class="dct-avantage__texte">Un montant de crédit est fixé avec vous à l'ouverture du compte.</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="dct-alerte dct-alerte--erreur">
                                <ul>
                                    @foreach ($errors->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="post" action="{{ route('client.demandeClientATerme') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="dct-section-titre">
                                <span class="dct-section-numero">1</span> Votre demande
                            </div>
                            <p class="dct-section-aide">Expliquez votre activité et pourquoi un compte à terme vous serait utile.</p>

                            <div class="dct-champ">
                                <label class="dct-label" for="objet">Objet <span class="dct-requis">*</span></label>
                                <input required type="text" id="objet" name="objet" maxlength="255"
                                       class="dct-input" value="{{ old('objet') }}"
                                       placeholder="Ex. : Ouverture d'un compte à terme pour mon entreprise">
                                <small class="dct-aide">Titre court de votre demande — 255 caractères au maximum.</small>
                            </div>

                            <div class="dct-champ">
                                <label class="dct-label" for="description">Description et justification <span class="dct-requis">*</span></label>
                                {{-- La contrainte des 20 caractères minimum existe côté serveur
                                     mais n'était annoncée nulle part : le client découvrait
                                     l'erreur après l'envoi. --}}
                                <textarea required id="description" name="description" rows="6" minlength="20"
                                          class="dct-input"
                                          placeholder="Décrivez votre activité, votre volume d'achat habituel et vos besoins.">{{ old('description') }}</textarea>
                                <small class="dct-aide">20 caractères minimum. Plus votre demande est détaillée, plus l'examen est rapide.</small>
                            </div>

                            <div class="dct-section-titre">
                                <span class="dct-section-numero">2</span> Pièces justificatives
                            </div>
                            <p class="dct-section-aide">
                                Formats acceptés : PDF, JPG, PNG, DOC ou DOCX — 5 Mo maximum par fichier.
                            </p>

                            <div class="dct-fichiers">
                                <div class="dct-champ">
                                    <label class="dct-label" for="doc_rccm">RCCM / Registre de commerce</label>
                                    <input type="file" id="doc_rccm" name="documents[rccm]" class="dct-input"
                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                </div>
                                <div class="dct-champ">
                                    <label class="dct-label" for="doc_bilan">Attestation de revenus / bilan</label>
                                    <input type="file" id="doc_bilan" name="documents[bilan]" class="dct-input"
                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                </div>
                                <div class="dct-champ">
                                    <label class="dct-label" for="doc_piece">Pièce d'identité du dirigeant</label>
                                    <input type="file" id="doc_piece" name="documents[piece_id]" class="dct-input"
                                           accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="dct-champ">
                                    <label class="dct-label" for="doc_autre">
                                        Autre document <span class="dct-facultatif">(facultatif)</span>
                                    </label>
                                    <input type="file" id="doc_autre" name="documents[autre]" class="dct-input"
                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                </div>
                            </div>

                            <div class="dct-encadre dct-encadre--info" style="margin-top:8px;">
                                <p class="dct-encadre__titre">Après l'envoi</p>
                                <p>
                                    Notre équipe examine votre dossier et vous répond par e-mail. En cas d'accord,
                                    un plafond de crédit et un délai de paiement vous seront communiqués.
                                </p>
                            </div>

                            <button type="submit" class="dct-bouton">
                                <i class="fi-rs-paper-plane"></i> Envoyer ma demande
                            </button>
                        </form>
                    </div>
                @endif

            </div>
        </div>
    </main>
@endsection

@section('jspart')
    <script>
        // Le script visait l'identifiant « notification », qui n'existe sur aucune
        // de ces alertes (elles portent « notify ») : le message ne disparaissait
        // jamais. Il s'efface désormais réellement au bout de six secondes.
        setTimeout(function () {
            document.querySelectorAll('#notify').forEach(function (el) {
                el.style.transition = 'opacity .4s ease';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 400);
            });
        }, 6000);
    </script>
@endsection
