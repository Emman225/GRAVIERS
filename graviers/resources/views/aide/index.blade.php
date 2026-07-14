@extends('layout.main')
@section('title','Centre d\'aide')

@php
    $firstName = explode(' ', $user->nom_prenoms ?? 'Utilisateur')[0];

    // FAQ adaptée au type d'utilisateur connecté
    $userType = (int) $user->type_user_id;
    $faqsCommunes = [
        [
            'q' => 'Comment modifier mon mot de passe ?',
            'a' => 'Cliquez sur votre nom dans le coin supérieur droit, choisissez <strong>Paramètre du compte</strong>, puis utilisez la section <em>Sécurité</em>. Vous devrez fournir votre ancien mot de passe pour valider le changement.',
        ],
        [
            'q' => 'Comment changer ma photo de profil ?',
            'a' => 'Rendez-vous dans <strong>Mon profil</strong> (clic sur votre nom en haut à droite), puis dans la carte <em>Photo de profil</em> cliquez sur <strong>Changer la photo</strong>. Formats acceptés : JPG, PNG, WEBP (max 2 Mo).',
        ],
        [
            'q' => 'Je ne reçois pas mes emails de notification, que faire ?',
            'a' => 'Vérifiez d\'abord votre dossier <strong>spam</strong>. Si le problème persiste, vérifiez que votre adresse email est correcte dans <strong>Mon profil</strong>, puis contactez le support.',
        ],
        [
            'q' => 'Comment me déconnecter ?',
            'a' => 'Cliquez sur votre nom en haut à droite et choisissez <strong>Déconnexion</strong> en bas du menu déroulant.',
        ],
    ];

    $faqsParRole = match($userType) {
        \Help::$USER_LIVREUR => [
            [
                'q' => 'Comment accepter ou refuser un bon d\'enlèvement ?',
                'a' => 'Allez dans <strong>Bons d\'enlèvement › Bons en attente</strong>. Cliquez sur <em>Accepter</em> ou <em>Refuser</em> dans la colonne Action. Une fois accepté, vous verrez le code du bon.',
            ],
            [
                'q' => 'Comment demander un paiement ?',
                'a' => 'Depuis votre tableau de bord, cliquez sur <strong>Demander un paiement</strong> dans la carte Solde. Saisissez le montant souhaité (inférieur ou égal à votre solde), votre numéro de compte et le mode de paiement.',
            ],
            [
                'q' => 'Comment ajouter un nouveau véhicule ?',
                'a' => 'Menu <strong>Livraisons › Ajouter un véhicule</strong>. Renseignez l\'immatriculation, la marque, le modèle, le type et la capacité.',
            ],
        ],
        \Help::$USER_FOURNISSEUR => [
            [
                'q' => 'Comment valider un bon d\'enlèvement ?',
                'a' => 'Menu <strong>Bon d\'enlèvement › Bons en attente</strong>, cliquez sur le bon concerné pour voir le détail et le valider en saisissant la quantité réellement servie.',
            ],
            [
                'q' => 'Comment mettre à jour mon stock ?',
                'a' => 'Menu <strong>Stock</strong>, cliquez sur <em>Modifier</em> à côté du produit dont vous voulez ajuster la quantité ou le prix.',
            ],
            [
                'q' => 'Pourquoi un produit s\'affiche en rouge dans mon stock ?',
                'a' => 'Cela signifie que la quantité disponible est sous le <strong>seuil d\'alerte</strong> que vous avez défini. Pensez à réapprovisionner.',
            ],
        ],
        \Help::$USER_APPORTEUR => [
            [
                'q' => 'Comment retrouver mes filleul(e)s ?',
                'a' => 'Menu <strong>Mes filleul(e)s</strong>. Vous y verrez tous les clients parrainés avec votre code, leur statut et le total de commissions générées.',
            ],
            [
                'q' => 'Comment partager mon code parrain ?',
                'a' => 'Votre code parrain est affiché sur votre tableau de bord et dans le bandeau de bienvenue. Communiquez-le à un nouveau client lors de son inscription pour qu\'il vous soit affecté.',
            ],
            [
                'q' => 'Quand reçoit-on les commissions ?',
                'a' => 'Les commissions sont créditées sur votre solde après validation des commandes de vos filleul(e)s. Vous pouvez ensuite demander un paiement depuis votre tableau de bord.',
            ],
        ],
        \Help::$USER_GESTIONNAIRE, \Help::$USER_ADMIN, \Help::$USER_SA => [
            [
                'q' => 'Comment créer un compte agent / livreur / apporteur / fournisseur ?',
                'a' => 'Menu correspondant (Livreurs › Création de compte, Apporteur d\'affaire, Fournisseurs › Création de compte). Renseignez les informations demandées, le compte recevra ses identifiants par email.',
            ],
            [
                'q' => 'Comment valider une demande de paiement ?',
                'a' => 'Menu <strong>Demandes de paiement</strong>, choisissez le type (Livreur, Apporteur, Fournisseur), cliquez sur la demande pour la traiter.',
            ],
            [
                'q' => 'Où configurer la TVA et les paramètres globaux ?',
                'a' => 'Menu <strong>Paramètre</strong> dans la sidebar. Tous les réglages globaux (TVA, prix personnalisés, configurations) s\'y trouvent.',
            ],
        ],
        default => [],
    };
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HERO ===== --}}
    <div class="aide-hero mb-4">
        <div class="aide-hero-content">
            <div class="aide-hero-icon"><i class="material-icons md-support_agent"></i></div>
            <h1 class="aide-hero-title">Bonjour {{ $firstName }}, comment pouvons-nous vous aider&nbsp;?</h1>
            <p class="aide-hero-subtitle">Trouvez des réponses, des tutoriels et contactez notre équipe support en un clic.</p>
            <div class="aide-search">
                <i class="material-icons md-search"></i>
                <input type="text" id="aideSearch" placeholder="Rechercher dans la FAQ — ex. mot de passe, paiement, profil..." />
            </div>
        </div>
        <div class="aide-hero-decoration"></div>
    </div>

    {{-- ===== QUICK ACTIONS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <a href="#faq" class="aide-quick-card">
                <div class="aide-quick-icon" style="background: linear-gradient(135deg, #1c57a3, #134380)">
                    <i class="material-icons md-quiz"></i>
                </div>
                <div class="aide-quick-text">
                    <div class="aide-quick-title">Questions fréquentes</div>
                    <div class="aide-quick-meta">{{ count($faqsCommunes) + count($faqsParRole) }} réponses prêtes</div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="#tutoriels" class="aide-quick-card">
                <div class="aide-quick-icon" style="background: linear-gradient(135deg, #10b981, #047857)">
                    <i class="material-icons md-play_circle_outline"></i>
                </div>
                <div class="aide-quick-text">
                    <div class="aide-quick-title">Tutoriels vidéo</div>
                    <div class="aide-quick-meta">Apprendre par l'exemple</div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="#contact" class="aide-quick-card">
                <div class="aide-quick-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706)">
                    <i class="material-icons md-mail"></i>
                </div>
                <div class="aide-quick-text">
                    <div class="aide-quick-title">Contacter le support</div>
                    <div class="aide-quick-meta">Réponse sous 24h</div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="https://wa.me/2250709910850" target="_blank" rel="noopener" class="aide-quick-card">
                <div class="aide-quick-icon" style="background: linear-gradient(135deg, #25D366, #128C7E)">
                    <i class="material-icons md-chat"></i>
                </div>
                <div class="aide-quick-text">
                    <div class="aide-quick-title">Discussion WhatsApp</div>
                    <div class="aide-quick-meta">Support instantané</div>
                </div>
            </a>
        </div>
    </div>

    {{-- ===== FAQ ===== --}}
    <div class="card dash-card mb-4" id="faq">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title">
                <i class="material-icons md-quiz text-primary"></i>
                Questions fréquentes
                <span class="badge bg-primary-light text-primary ms-2" style="font-size:0.7rem">{{ $typeLabel }}</span>
            </h5>
        </div>
        <div class="card-body">
            @if(!empty($faqsParRole))
                <h6 class="aide-faq-section">Spécifique à votre rôle : {{ $typeLabel }}</h6>
                <div class="aide-faq-list mb-4">
                    @foreach($faqsParRole as $idx => $faq)
                        <details class="aide-faq-item">
                            <summary>
                                <span>{{ $faq['q'] }}</span>
                                <i class="material-icons md-expand_more"></i>
                            </summary>
                            <div class="aide-faq-answer">{!! $faq['a'] !!}</div>
                        </details>
                    @endforeach
                </div>
            @endif

            <h6 class="aide-faq-section">Questions générales</h6>
            <div class="aide-faq-list">
                @foreach($faqsCommunes as $idx => $faq)
                    <details class="aide-faq-item">
                        <summary>
                            <span>{{ $faq['q'] }}</span>
                            <i class="material-icons md-expand_more"></i>
                        </summary>
                        <div class="aide-faq-answer">{!! $faq['a'] !!}</div>
                    </details>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== TUTORIELS ===== --}}
    <div class="card dash-card mb-4" id="tutoriels">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title">
                <i class="material-icons md-play_circle_outline text-success"></i>
                Tutoriels & guides
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="aide-tuto-card">
                        <div class="aide-tuto-thumb" style="background: linear-gradient(135deg, #1c57a3, #134380)">
                            <i class="material-icons md-rocket_launch"></i>
                        </div>
                        <div class="aide-tuto-body">
                            <h6 class="aide-tuto-title">Démarrer en 5 minutes</h6>
                            <p class="aide-tuto-meta">Découvrez les bases de votre espace en quelques étapes simples.</p>
                            <span class="badge bg-light text-muted"><i class="material-icons md-schedule" style="font-size:14px;vertical-align:middle"></i> 5 min</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="aide-tuto-card">
                        <div class="aide-tuto-thumb" style="background: linear-gradient(135deg, #10b981, #047857)">
                            <i class="material-icons md-dashboard"></i>
                        </div>
                        <div class="aide-tuto-body">
                            <h6 class="aide-tuto-title">Maîtriser le tableau de bord</h6>
                            <p class="aide-tuto-meta">Comprendre les KPI et les indicateurs de votre activité.</p>
                            <span class="badge bg-light text-muted"><i class="material-icons md-schedule" style="font-size:14px;vertical-align:middle"></i> 8 min</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="aide-tuto-card">
                        <div class="aide-tuto-thumb" style="background: linear-gradient(135deg, #f59e0b, #d97706)">
                            <i class="material-icons md-account_balance_wallet"></i>
                        </div>
                        <div class="aide-tuto-body">
                            <h6 class="aide-tuto-title">Demandes de paiement</h6>
                            <p class="aide-tuto-meta">Comment faire une demande, suivre son statut et recevoir le paiement.</p>
                            <span class="badge bg-light text-muted"><i class="material-icons md-schedule" style="font-size:14px;vertical-align:middle"></i> 6 min</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== CONTACT ===== --}}
    <div class="row g-4 mb-4" id="contact">
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-mail text-primary"></i>
                        Nous contacter
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Vous n'avez pas trouvé votre réponse ? Envoyez-nous un message, nous vous répondons sous 24h ouvrées.</p>
                    <form method="post" action="#">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="premium-field-label"><i class="material-icons md-person"></i> Votre nom</label>
                                <input class="form-control" type="text" value="{{ $user->nom_prenoms }}" readonly />
                            </div>
                            <div class="col-md-6">
                                <label class="premium-field-label"><i class="material-icons md-mail"></i> Email de réponse</label>
                                <input class="form-control" type="email" value="{{ $user->email }}" readonly />
                            </div>
                            <div class="col-12">
                                <label class="premium-field-label" for="sujet"><i class="material-icons md-label"></i> Sujet</label>
                                <select id="sujet" class="form-select" name="sujet" required>
                                    <option value="">— Choisir un sujet —</option>
                                    <option>Problème technique</option>
                                    <option>Question sur mon compte</option>
                                    <option>Problème de paiement</option>
                                    <option>Demande de fonctionnalité</option>
                                    <option>Autre</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="premium-field-label" for="message"><i class="material-icons md-edit_note"></i> Votre message</label>
                                <textarea id="message" class="form-control" name="message" rows="5" required placeholder="Décrivez votre demande avec autant de détails que possible..."></textarea>
                            </div>
                            <div class="col-12 text-end">
                                <button class="btn btn-primary" type="submit">
                                    <i class="material-icons md-send"></i> Envoyer le message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-contact_support text-primary"></i>
                        Autres canaux
                    </h5>
                </div>
                <div class="card-body">
                    <div class="aide-contact-row">
                        <div class="aide-contact-icon" style="background: linear-gradient(135deg, #1c57a3, #134380)">
                            <i class="material-icons md-call"></i>
                        </div>
                        <div>
                            <div class="aide-contact-label">Téléphone</div>
                            <a href="tel:+2250709910850" class="aide-contact-value">+225 07 09 91 08 50</a>
                            <small class="text-muted d-block">Lun–Ven · 8h – 18h</small>
                        </div>
                    </div>
                    <div class="aide-contact-row">
                        <div class="aide-contact-icon" style="background: linear-gradient(135deg, #25D366, #128C7E)">
                            <i class="material-icons md-chat"></i>
                        </div>
                        <div>
                            <div class="aide-contact-label">WhatsApp</div>
                            <a href="https://wa.me/2250709910850" target="_blank" rel="noopener" class="aide-contact-value">Démarrer une discussion</a>
                            <small class="text-muted d-block">Réponse rapide en journée</small>
                        </div>
                    </div>
                    <div class="aide-contact-row">
                        <div class="aide-contact-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706)">
                            <i class="material-icons md-mail"></i>
                        </div>
                        <div>
                            <div class="aide-contact-label">Email</div>
                            <a href="mailto:support@mongravier.com" class="aide-contact-value">support@mongravier.com</a>
                            <small class="text-muted d-block">Réponse sous 24h</small>
                        </div>
                    </div>
                    <div class="aide-contact-row">
                        <div class="aide-contact-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9)">
                            <i class="material-icons md-place"></i>
                        </div>
                        <div>
                            <div class="aide-contact-label">Adresse</div>
                            <div class="aide-contact-value">Abidjan, Côte d'Ivoire</div>
                            <small class="text-muted d-block">Siège social</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
<style>
    /* ===== HERO ===== */
    .aide-hero {
        position: relative;
        background: linear-gradient(135deg, #1c57a3 0%, #134380 100%);
        color: white;
        border-radius: 16px;
        padding: 40px 32px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(28, 87, 163, 0.18);
    }
    .aide-hero-content { position: relative; z-index: 2; max-width: 720px; }
    .aide-hero-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: rgba(255,255,255,0.18);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 14px;
    }
    .aide-hero-icon .material-icons { font-size: 32px; color: #fff; }
    .aide-hero-title { font-size: 1.6rem; font-weight: 700; margin: 0 0 8px; line-height: 1.25; }
    .aide-hero-subtitle { opacity: 0.9; margin: 0 0 22px; font-size: 0.95rem; }
    .aide-hero-decoration {
        position: absolute;
        right: -60px;
        top: -60px;
        width: 280px;
        height: 280px;
        background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
        border-radius: 50%;
        z-index: 1;
    }
    .aide-search {
        position: relative;
        background: white;
        border-radius: 12px;
        padding: 4px 6px 4px 48px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    }
    .aide-search .material-icons {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
        font-size: 22px;
    }
    .aide-search input {
        width: 100%;
        border: 0;
        outline: 0;
        background: transparent;
        padding: 12px 14px;
        font-size: 0.95rem;
        color: #111827;
    }

    /* ===== QUICK CARDS ===== */
    .aide-quick-card {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        text-decoration: none;
        color: inherit;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        height: 100%;
    }
    .aide-quick-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15,23,42,0.08);
        border-color: #cbd5e1;
        color: inherit;
    }
    .aide-quick-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: white;
    }
    .aide-quick-icon .material-icons { font-size: 26px; }
    .aide-quick-title { font-weight: 600; color: #111827; font-size: 0.95rem; }
    .aide-quick-meta { color: #6b7280; font-size: 0.8rem; margin-top: 2px; }

    /* ===== FAQ ===== */
    .aide-faq-section {
        color: #6b7280;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        margin: 0 0 12px;
    }
    .aide-faq-list { display: flex; flex-direction: column; gap: 8px; }
    .aide-faq-item {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        transition: background 0.12s ease, border-color 0.12s ease;
    }
    .aide-faq-item[open] { background: #ffffff; border-color: #1c57a3; }
    .aide-faq-item summary {
        list-style: none;
        cursor: pointer;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        font-weight: 600;
        color: #111827;
        font-size: 0.92rem;
    }
    .aide-faq-item summary::-webkit-details-marker { display: none; }
    .aide-faq-item summary .material-icons {
        color: #6b7280;
        transition: transform 0.2s ease;
    }
    .aide-faq-item[open] summary .material-icons { transform: rotate(180deg); color: #1c57a3; }
    .aide-faq-answer {
        padding: 0 18px 16px 18px;
        color: #4b5563;
        font-size: 0.9rem;
        line-height: 1.6;
        border-top: 1px solid #f1f5f9;
        margin-top: -1px;
        padding-top: 14px;
    }

    /* ===== TUTORIELS ===== */
    .aide-tuto-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        height: 100%;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .aide-tuto-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15,23,42,0.08);
    }
    .aide-tuto-thumb {
        height: 110px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    .aide-tuto-thumb .material-icons { font-size: 48px; opacity: 0.95; }
    .aide-tuto-body { padding: 16px; }
    .aide-tuto-title { color: #111827; font-weight: 600; margin: 0 0 6px; font-size: 0.95rem; }
    .aide-tuto-meta { color: #6b7280; font-size: 0.82rem; margin: 0 0 10px; line-height: 1.5; }

    /* ===== CONTACT ===== */
    .aide-contact-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .aide-contact-row:last-child { border-bottom: 0; }
    .aide-contact-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
    }
    .aide-contact-icon .material-icons { font-size: 22px; }
    .aide-contact-label {
        color: #6b7280;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
    }
    .aide-contact-value {
        color: #111827;
        font-weight: 600;
        text-decoration: none;
        font-size: 0.95rem;
    }
    .aide-contact-value:hover { color: #1c57a3; }

    @media (max-width: 768px) {
        .aide-hero { padding: 28px 22px; }
        .aide-hero-title { font-size: 1.3rem; }
        .aide-hero-decoration { display: none; }
    }
</style>
@endsection

@section('jsParts')
<script>
    // Filtre live de la FAQ
    (function () {
        var input = document.getElementById('aideSearch');
        if (!input) return;
        var items = document.querySelectorAll('.aide-faq-item');
        input.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            items.forEach(function (item) {
                var match = item.textContent.toLowerCase().indexOf(q) > -1;
                item.style.display = match ? '' : 'none';
                if (q && match) item.open = true;
                if (!q) item.open = false;
            });
        });
    })();
</script>
@endsection
