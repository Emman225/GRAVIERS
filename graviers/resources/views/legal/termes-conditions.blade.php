@extends('client.main')
@section('title','Termes et conditions')

@section('content')
@include('client.navMobile')

<main class="legal-page">
    {{-- ===== HERO ===== --}}
    <section class="legal-hero">
        <div class="legal-hero__bg" aria-hidden="true"></div>
        <div class="legal-hero__overlay" aria-hidden="true"></div>
        <div class="legal-hero__inner">
            <span class="legal-hero__chip">
                <i class="material-icons md-gavel"></i> Document légal
            </span>
            <h1 class="legal-hero__title">Termes et conditions</h1>
            <p class="legal-hero__subtitle">
                Les règles d'utilisation de la plateforme <strong>GRAVIER.COM</strong>.
                En créant un compte ou en passant commande, vous acceptez les conditions ci-dessous.
            </p>
            <div class="legal-hero__meta">
                <span><i class="material-icons md-event"></i> Dernière mise à jour : <strong>{{ \Carbon\Carbon::now()->locale('fr')->isoFormat('D MMMM YYYY') }}</strong></span>
                <span><i class="material-icons md-verified"></i> Version 1.0</span>
            </div>
        </div>
    </section>

    {{-- ===== CONTENU ===== --}}
    <section class="legal-body">
        <div class="legal-body__inner">

            {{-- TOC (sommaire) --}}
            <aside class="legal-toc">
                <div class="legal-toc__title">
                    <i class="material-icons md-list"></i> Sommaire
                </div>
                <ol>
                    <li><a href="#section-1">Préambule</a></li>
                    <li><a href="#section-2">Définitions</a></li>
                    <li><a href="#section-3">Acceptation des conditions</a></li>
                    <li><a href="#section-4">Inscription & compte utilisateur</a></li>
                    <li><a href="#section-5">Services proposés</a></li>
                    <li><a href="#section-6">Commande & livraison</a></li>
                    <li><a href="#section-7">Tarifs & paiement</a></li>
                    <li><a href="#section-8">Annulation & remboursement</a></li>
                    <li><a href="#section-9">Protection des données</a></li>
                    <li><a href="#section-10">Propriété intellectuelle</a></li>
                    <li><a href="#section-11">Responsabilité</a></li>
                    <li><a href="#section-12">Force majeure</a></li>
                    <li><a href="#section-13">Modification des CGU</a></li>
                    <li><a href="#section-14">Droit applicable</a></li>
                    <li><a href="#section-15">Contact</a></li>
                </ol>
            </aside>

            <article class="legal-article">

            @if(!empty($config?->termes_conditions))
                {{-- Contenu paramétrable saisi depuis Paramètres > Termes & conditions --}}
                <div class="legal-section legal-section--custom">
                    {!! $config->termes_conditions !!}
                </div>
            @else

                <section id="section-1" class="legal-section">
                    <h2><span class="legal-section__num">1</span> Préambule</h2>
                    <p>
                        Les présentes conditions générales d'utilisation (CGU) régissent l'usage de la plateforme
                        <strong>GRAVIER.COM</strong>, accessible via le site web et les applications mobiles, exploitée
                        par DALAKOUN SARL, dont le siège social est situé à Abidjan, Côte d'Ivoire.
                    </p>
                    <p>
                        La plateforme met en relation des clients (particuliers et entreprises) avec des fournisseurs
                        de matériaux de construction (sable, gravier, ciment, blocs, etc.) et des livreurs partenaires
                        pour faciliter la commande et la livraison de ces matériaux sur les chantiers.
                    </p>
                </section>

                <section id="section-2" class="legal-section">
                    <h2><span class="legal-section__num">2</span> Définitions</h2>
                    <ul class="legal-def-list">
                        <li><strong>Plateforme</strong> : le service en ligne accessible à l'adresse gravier.com et via les applications mobiles associées.</li>
                        <li><strong>Utilisateur</strong> : toute personne physique ou morale ayant créé un compte sur la plateforme.</li>
                        <li><strong>Client</strong> : utilisateur passant des commandes pour son usage personnel ou professionnel.</li>
                        <li><strong>Fournisseur</strong> : entreprise inscrite proposant des matériaux à la vente.</li>
                        <li><strong>Livreur</strong> : prestataire indépendant assurant le transport des matériaux.</li>
                        <li><strong>Apporteur d'affaires</strong> : utilisateur recommandant la plateforme et percevant une commission sur les commandes de ses filleul(e)s.</li>
                        <li><strong>Commande</strong> : opération d'achat passée par un client via la plateforme.</li>
                    </ul>
                </section>

                <section id="section-3" class="legal-section">
                    <h2><span class="legal-section__num">3</span> Acceptation des conditions</h2>
                    <p>
                        L'utilisation de la plateforme implique l'acceptation pleine et entière des présentes CGU.
                        En cochant la case « J'accepte les termes et conditions » lors de l'inscription, l'utilisateur
                        reconnaît avoir lu et compris ces conditions et s'engage à les respecter.
                    </p>
                    <div class="legal-callout legal-callout--info">
                        <i class="material-icons md-info"></i>
                        <span>Si vous n'acceptez pas tout ou partie de ces conditions, vous ne pouvez pas utiliser la plateforme.</span>
                    </div>
                </section>

                <section id="section-4" class="legal-section">
                    <h2><span class="legal-section__num">4</span> Inscription & compte utilisateur</h2>
                    <p>L'inscription est gratuite et nécessite de fournir des informations exactes et à jour :</p>
                    <ul>
                        <li>Nom et prénom (ou raison sociale pour les entreprises)</li>
                        <li>Adresse email valide</li>
                        <li>Numéro(s) de contact</li>
                        <li>Adresse de livraison</li>
                        <li>Documents légaux pour les entreprises (RCCM, NCC, DFE)</li>
                    </ul>
                    <p>
                        L'utilisateur est seul responsable de la confidentialité de son mot de passe et de toute activité
                        effectuée depuis son compte. En cas de soupçon d'utilisation frauduleuse, il doit en informer
                        immédiatement le support.
                    </p>
                    <div class="legal-callout legal-callout--warning">
                        <i class="material-icons md-warning"></i>
                        <span>Toute déclaration mensongère ou usurpation d'identité entraîne la suspension immédiate du compte.</span>
                    </div>
                </section>

                <section id="section-5" class="legal-section">
                    <h2><span class="legal-section__num">5</span> Services proposés</h2>
                    <p>La plateforme propose les services suivants :</p>
                    <ul>
                        <li>Consultation du catalogue de matériaux de construction</li>
                        <li>Passage de commandes en ligne</li>
                        <li>Suivi de livraison en temps réel</li>
                        <li>Gestion d'un espace personnel sécurisé</li>
                        <li>Programme de parrainage</li>
                        <li>Service client par téléphone, email et WhatsApp</li>
                    </ul>
                    <p>
                        GRAVIER.COM se réserve le droit d'ajouter, modifier ou supprimer des services à tout moment,
                        sans préavis ni indemnité.
                    </p>
                </section>

                <section id="section-6" class="legal-section">
                    <h2><span class="legal-section__num">6</span> Commande & livraison</h2>
                    <p>
                        Toute commande validée par le client constitue un engagement ferme. Un email de confirmation
                        est envoyé après validation. La livraison est assurée par un livreur partenaire dans le délai
                        indiqué lors de la commande, sous réserve des disponibilités du fournisseur.
                    </p>
                    <ul>
                        <li>Le client doit être présent ou désigner un représentant à l'adresse indiquée</li>
                        <li>Un code de réception est fourni au client pour valider la livraison</li>
                        <li>En cas d'absence, des frais de représentation peuvent s'appliquer</li>
                        <li>Les quantités effectivement livrées font foi pour la facturation</li>
                    </ul>
                </section>

                <section id="section-7" class="legal-section">
                    <h2><span class="legal-section__num">7</span> Tarifs & paiement</h2>
                    <p>
                        Les prix sont indiqués en francs CFA (FCFA), toutes taxes comprises (TVA incluse au taux en vigueur).
                        Les frais de livraison sont calculés en fonction du volume, de la distance et du véhicule requis.
                    </p>
                    <p><strong>Modes de paiement acceptés :</strong></p>
                    <ul>
                        <li>Mobile Money (Orange Money, MTN Money, Moov Money, Wave)</li>
                        <li>Carte bancaire</li>
                        <li>Paiement à la livraison (espèces)</li>
                        <li>Virement bancaire (clients à terme uniquement)</li>
                    </ul>
                    <p>
                        Pour les clients à terme, un délai de paiement peut être accordé selon les conditions définies
                        avec l'administrateur. Tout retard de paiement peut entraîner la suspension du compte et l'application
                        de pénalités de retard.
                    </p>
                </section>

                <section id="section-8" class="legal-section">
                    <h2><span class="legal-section__num">8</span> Annulation & remboursement</h2>
                    <p>
                        Une commande peut être annulée sans frais tant qu'elle n'est pas en cours de préparation par le
                        fournisseur. Passé ce stade, l'annulation peut entraîner des frais.
                    </p>
                    <ul>
                        <li><strong>Avant validation du bon d'enlèvement</strong> : annulation gratuite</li>
                        <li><strong>Après chargement</strong> : 30 % du montant retenu pour couvrir les frais engagés</li>
                        <li><strong>Après livraison</strong> : annulation impossible sauf défaut de conformité avéré</li>
                    </ul>
                    <p>
                        Les remboursements sont effectués sous 7 jours ouvrés via le même moyen de paiement utilisé
                        lors de la commande.
                    </p>
                </section>

                <section id="section-9" class="legal-section">
                    <h2><span class="legal-section__num">9</span> Protection des données personnelles</h2>
                    <p>
                        GRAVIER.COM s'engage à respecter la confidentialité des données personnelles collectées,
                        conformément à la législation ivoirienne en vigueur (loi n° 2013-450 sur la protection des données
                        à caractère personnel).
                    </p>
                    <p>Les données collectées sont utilisées exclusivement pour :</p>
                    <ul>
                        <li>La gestion de votre compte et de vos commandes</li>
                        <li>Le traitement des paiements et livraisons</li>
                        <li>La communication relative au service (notifications, factures)</li>
                        <li>L'amélioration de l'expérience utilisateur</li>
                    </ul>
                    <p>
                        Vous disposez d'un droit d'accès, de rectification, de portabilité et de suppression de vos données.
                        Pour exercer ces droits, contactez-nous à <a href="mailto:rgpd@gravier.com">rgpd@gravier.com</a>.
                    </p>
                </section>

                <section id="section-10" class="legal-section">
                    <h2><span class="legal-section__num">10</span> Propriété intellectuelle</h2>
                    <p>
                        L'ensemble du contenu de la plateforme (textes, logos, images, code, design) est protégé par
                        le droit d'auteur et reste la propriété exclusive de DALAKOUN SARL ou de ses partenaires.
                        Toute reproduction, diffusion ou utilisation sans autorisation préalable est interdite.
                    </p>
                </section>

                <section id="section-11" class="legal-section">
                    <h2><span class="legal-section__num">11</span> Responsabilité</h2>
                    <p>
                        GRAVIER.COM agit comme intermédiaire entre clients, fournisseurs et livreurs. La plateforme
                        ne peut être tenue pour responsable :
                    </p>
                    <ul>
                        <li>De la qualité intrinsèque des matériaux fournis par les fournisseurs</li>
                        <li>Des retards causés par des circonstances indépendantes (météo, trafic, force majeure)</li>
                        <li>De l'usage que fait le client des matériaux livrés</li>
                        <li>Des dommages indirects résultant de l'utilisation de la plateforme</li>
                    </ul>
                    <p>
                        Notre responsabilité est plafonnée au montant de la commande concernée.
                    </p>
                </section>

                <section id="section-12" class="legal-section">
                    <h2><span class="legal-section__num">12</span> Force majeure</h2>
                    <p>
                        GRAVIER.COM ne peut être tenu pour responsable d'un manquement à ses obligations en cas de
                        force majeure : catastrophes naturelles, conflits armés, grèves générales, pandémies, coupures
                        réseau majeures, ou toute autre circonstance imprévisible et irrésistible.
                    </p>
                </section>

                <section id="section-13" class="legal-section">
                    <h2><span class="legal-section__num">13</span> Modification des CGU</h2>
                    <p>
                        Les présentes CGU peuvent être modifiées à tout moment. Les utilisateurs seront informés
                        des changements majeurs par email ou via une notification sur la plateforme. La poursuite
                        de l'utilisation après notification vaut acceptation des nouvelles conditions.
                    </p>
                </section>

                <section id="section-14" class="legal-section">
                    <h2><span class="legal-section__num">14</span> Droit applicable & juridiction</h2>
                    <p>
                        Les présentes CGU sont régies par le droit ivoirien. Tout litige relatif à leur interprétation
                        ou exécution sera soumis aux tribunaux compétents d'Abidjan, après tentative préalable de
                        règlement amiable.
                    </p>
                </section>

                <section id="section-15" class="legal-section">
                    <h2><span class="legal-section__num">15</span> Nous contacter</h2>
                    <div class="legal-contact-grid">
                        <div class="legal-contact">
                            <div class="legal-contact__icon" style="background: linear-gradient(135deg, #1c57a3, #134380)">
                                <i class="material-icons md-mail"></i>
                            </div>
                            <div>
                                <div class="legal-contact__label">Email</div>
                                <a href="mailto:support@gravier.com" class="legal-contact__value">support@gravier.com</a>
                            </div>
                        </div>
                        <div class="legal-contact">
                            <div class="legal-contact__icon" style="background: linear-gradient(135deg, #25D366, #128C7E)">
                                <i class="material-icons md-chat"></i>
                            </div>
                            <div>
                                <div class="legal-contact__label">WhatsApp</div>
                                <a href="https://wa.me/2250709910850" target="_blank" rel="noopener" class="legal-contact__value">+225 07 09 91 08 50</a>
                            </div>
                        </div>
                        <div class="legal-contact">
                            <div class="legal-contact__icon" style="background: linear-gradient(135deg, #f59e0b, #d97706)">
                                <i class="material-icons md-call"></i>
                            </div>
                            <div>
                                <div class="legal-contact__label">Téléphone</div>
                                <a href="tel:+2250709910850" class="legal-contact__value">+225 07 09 91 08 50</a>
                            </div>
                        </div>
                        <div class="legal-contact">
                            <div class="legal-contact__icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9)">
                                <i class="material-icons md-place"></i>
                            </div>
                            <div>
                                <div class="legal-contact__label">Adresse</div>
                                <div class="legal-contact__value">Abidjan, Côte d'Ivoire</div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- CTA retour --}}
                <div class="legal-cta">
                    <p>Vous avez lu et accepté nos conditions ?</p>
                    <a href="{{ route('client.register') }}" class="legal-cta__btn">
                        <i class="material-icons md-person_add"></i> Créer mon compte
                    </a>
                    <a href="javascript:history.back()" class="legal-cta__link">
                        <i class="material-icons md-arrow_back"></i> Retour
                    </a>
                </div>

            @endif

            </article>
        </div>
    </section>
</main>

<style>
    /* ===== HERO ===== */
    .legal-hero {
        position: relative;
        padding: 80px 20px 60px;
        text-align: center;
        color: #fff;
        overflow: hidden;
        isolation: isolate;
        background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
    }
    .legal-hero__bg {
        position: absolute; inset: 0; z-index: -2;
        background-image: url("{{ asset('frontend/assets/imgs/banner/hero-gravier.png') }}");
        background-size: cover;
        background-position: center;
        filter: saturate(1.1) brightness(0.85);
    }
    .legal-hero__overlay {
        position: absolute; inset: 0; z-index: -1;
        background:
            linear-gradient(180deg, rgba(10, 37, 64, 0.65) 0%, rgba(10, 37, 64, 0.82) 100%),
            radial-gradient(circle at 75% 25%, rgba(251, 146, 60, 0.25), transparent 55%);
    }
    .legal-hero__inner { max-width: 760px; margin: 0 auto; }
    .legal-hero__chip {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.25);
        padding: 7px 14px;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        margin-bottom: 18px;
    }
    .legal-hero__chip i { font-size: 18px; }
    .legal-hero__title,
    h1.legal-hero__title {
        font-size: 2.4rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        line-height: 1.1;
        margin: 0 0 14px;
        color: #ffffff !important;
        text-shadow: 0 2px 18px rgba(0,0,0,0.35);
    }
    .legal-hero__subtitle {
        font-size: 1rem;
        line-height: 1.6;
        color: rgba(255,255,255,0.92);
        margin: 0 0 22px;
        text-shadow: 0 1px 8px rgba(0,0,0,0.3);
    }
    .legal-hero__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
        justify-content: center;
        font-size: 0.85rem;
        color: rgba(255,255,255,0.85);
    }
    .legal-hero__meta span { display: inline-flex; align-items: center; gap: 6px; }
    .legal-hero__meta i { font-size: 16px; color: #fbbf24; }

    /* ===== BODY ===== */
    .legal-body {
        background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
        padding: 60px 20px 80px;
    }
    .legal-body__inner {
        max-width: 1100px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 36px;
        align-items: start;
    }

    /* ===== TOC ===== */
    .legal-toc {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 22px 20px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        position: sticky;
        top: 90px;
    }
    .legal-toc__title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        color: #0a2540;
        font-size: 0.9rem;
        margin-bottom: 14px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .legal-toc__title i { color: #1c57a3; font-size: 20px; }
    .legal-toc ol {
        list-style: none;
        counter-reset: toc;
        padding: 0; margin: 0;
        display: flex; flex-direction: column; gap: 4px;
    }
    .legal-toc ol li { counter-increment: toc; }
    .legal-toc ol li a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        color: #4b5563;
        font-size: 0.88rem;
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.15s ease;
    }
    .legal-toc ol li a::before {
        content: counter(toc, decimal-leading-zero);
        flex: 0 0 28px;
        font-size: 0.72rem;
        font-weight: 700;
        color: #9ca3af;
        font-variant-numeric: tabular-nums;
    }
    .legal-toc ol li a:hover {
        background: #eff6ff;
        color: #1c57a3;
    }
    .legal-toc ol li a:hover::before { color: #1c57a3; }

    /* ===== ARTICLE ===== */
    .legal-article {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 38px 40px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
    }

    .legal-section {
        padding-top: 14px;
        scroll-margin-top: 90px;
    }
    .legal-section + .legal-section {
        margin-top: 30px;
        padding-top: 30px;
        border-top: 1px solid #f1f5f9;
    }
    .legal-section h2,
    .legal-article h2.legal-section h2 {
        display: flex;
        align-items: center;
        gap: 14px;
        font-size: 1.35rem;
        font-weight: 700;
        color: #0a2540 !important;
        margin: 0 0 14px;
        letter-spacing: -0.01em;
    }
    .legal-section__num {
        flex: 0 0 38px;
        width: 38px; height: 38px;
        border-radius: 10px;
        background: linear-gradient(135deg, #1c57a3, #134380);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.92rem;
        font-weight: 700;
        box-shadow: 0 4px 10px rgba(28, 87, 163, 0.25);
    }
    .legal-section p {
        color: #374151;
        line-height: 1.75;
        font-size: 0.95rem;
        margin: 0 0 12px;
    }
    .legal-section p strong { color: #111827; }
    .legal-section ul {
        margin: 8px 0 14px;
        padding-left: 0;
        list-style: none;
    }
    .legal-section ul li {
        position: relative;
        padding-left: 28px;
        color: #374151;
        line-height: 1.7;
        font-size: 0.93rem;
        margin-bottom: 8px;
    }
    .legal-section ul li::before {
        content: "";
        position: absolute;
        left: 8px;
        top: 11px;
        width: 6px; height: 6px;
        border-radius: 50%;
        background: linear-gradient(135deg, #fb923c, #ea580c);
    }
    .legal-def-list li { padding-left: 0; }
    .legal-def-list li::before { display: none; }
    .legal-def-list li strong { color: #1c57a3; display: inline-block; min-width: 145px; }

    .legal-section a { color: #ea580c; font-weight: 600; text-decoration: none; }
    .legal-section a:hover { text-decoration: underline; }

    /* ===== CALLOUT ===== */
    .legal-callout {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 12px;
        margin: 14px 0;
        font-size: 0.9rem;
        line-height: 1.5;
        border-left: 4px solid;
    }
    .legal-callout i { font-size: 22px; flex-shrink: 0; }
    .legal-callout--info {
        background: #eff6ff;
        color: #1e40af;
        border-left-color: #3b82f6;
    }
    .legal-callout--info i { color: #3b82f6; }
    .legal-callout--warning {
        background: #fffbeb;
        color: #92400e;
        border-left-color: #f59e0b;
    }
    .legal-callout--warning i { color: #f59e0b; }

    /* ===== CONTACT GRID ===== */
    .legal-contact-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
        margin-top: 8px;
    }
    .legal-contact {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        transition: all 0.15s ease;
    }
    .legal-contact:hover { background: #ffffff; border-color: #cbd5e1; transform: translateY(-1px); }
    .legal-contact__icon {
        width: 42px; height: 42px;
        border-radius: 12px;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .legal-contact__icon i { font-size: 22px; }
    .legal-contact__label {
        color: #6b7280;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
    }
    .legal-contact__value {
        color: #111827;
        font-weight: 600;
        text-decoration: none;
        font-size: 0.92rem;
    }
    .legal-contact__value:hover { color: #1c57a3; }

    /* ===== CTA ===== */
    .legal-cta {
        margin-top: 42px;
        padding: 28px;
        background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
        border-radius: 16px;
        text-align: center;
        border: 1px solid #fde68a;
    }
    .legal-cta p {
        margin: 0 0 14px;
        color: #92400e;
        font-weight: 600;
        font-size: 1rem;
    }
    .legal-cta__btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 22px;
        background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
        color: #fff !important;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 8px 20px rgba(234, 88, 12, 0.35);
        transition: all 0.2s ease;
        margin-right: 8px;
    }
    .legal-cta__btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 26px rgba(234, 88, 12, 0.45);
        color: #fff;
    }
    .legal-cta__btn i { font-size: 20px; }
    .legal-cta__link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #92400e;
        font-weight: 600;
        text-decoration: none;
        padding: 10px 16px;
    }
    .legal-cta__link:hover { color: #78350f; text-decoration: underline; }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 991px) {
        .legal-body__inner {
            grid-template-columns: 1fr;
        }
        .legal-toc {
            position: static;
            order: -1;
        }
        .legal-toc ol {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
        }
        .legal-article { padding: 28px 24px; }
    }
    @media (max-width: 575px) {
        .legal-hero { padding: 50px 18px 40px; }
        .legal-hero__title { font-size: 1.7rem; }
        .legal-hero__subtitle { font-size: 0.9rem; }
        .legal-body { padding: 30px 12px 50px; }
        .legal-article { padding: 22px 18px; border-radius: 14px; }
        .legal-toc ol { grid-template-columns: 1fr; }
        .legal-section h2 { font-size: 1.15rem; gap: 10px; }
        .legal-section__num { flex: 0 0 32px; width: 32px; height: 32px; font-size: 0.85rem; }
        .legal-contact-grid { grid-template-columns: 1fr; }
    }
</style>
@endsection
