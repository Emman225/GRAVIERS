@extends('client.main')
@section('title','Contact')
@section('content')

<style>
    .contact-hero{
        /* Lot 109 (17/09/2026) : palette de la charte (bleu nuit / bleu / jaune), cartes de
           coordonnées cliquables, WhatsApp, accès rapides ; contenu cohérent avec le pied de page. */
        background: linear-gradient(135deg, #0A2540 0%, #1C57A3 100%);
        color:#fff; padding:56px 0; text-align:center; position:relative; overflow:hidden;
    }
    .contact-hero::after{ content:""; position:absolute; right:-80px; top:-80px; width:320px; height:320px; border-radius:50%;
        background:rgba(255,179,0,.14); }
    .contact-hero h1{ color:#fff !important; font-weight:800; margin-bottom:8px; position:relative; }
    .contact-hero p{ color:#dce9f7 !important; margin:0; position:relative; }
    .contact-section{ padding:48px 0 56px; }
    .contact-info-card{
        background:#fff; border:1px solid #e9eef5; border-radius:14px; padding:20px 22px;
        display:flex; align-items:flex-start; gap:16px; margin-bottom:14px; transition:transform .15s ease, box-shadow .15s ease;
    }
    .contact-info-card:hover{ transform:translateY(-2px); box-shadow:0 10px 24px rgba(10,37,64,.08); }
    .contact-info-card .ico{
        width:48px; height:48px; min-width:48px; border-radius:12px; display:flex;
        align-items:center; justify-content:center; background:#e8f0fa; color:#1C57A3; font-size:22px;
    }
    .contact-info-card .ico.ico-whatsapp{ background:#e6f9ee; color:#1ebe5a; }
    .contact-info-card h6{ font-weight:700; margin:0 0 4px; color:#0A2540; }
    .contact-info-card p{ margin:0; color:#56637a; }
    .contact-info-card a{ color:#1C57A3; font-weight:600; }
    .contact-titre{ font-weight:800; color:#0A2540; margin-bottom:18px; }
    .contact-titre::after{ content:""; display:block; width:56px; height:4px; border-radius:2px; background:#FFB300; margin-top:8px; }
    .contact-form-wrap{
        background:#fff; border:1px solid #e9eef5; border-radius:16px; padding:32px;
        box-shadow:0 8px 24px rgba(10,37,64,.05);
    }
    .contact-form-wrap h3{ font-weight:800; color:#0A2540; margin-bottom:6px; }
    .contact-form-wrap .form-control{
        border:1px solid #d7dfe8; border-radius:10px; padding:12px 14px; margin-bottom:4px;
    }
    .contact-form-wrap .form-control:focus{ border-color:#1C57A3; box-shadow:0 0 0 .15rem rgba(28,87,163,.15); }
    .contact-form-wrap label{ font-weight:600; color:#3a4552; margin-bottom:6px; }
    .contact-field-error{ color:#d9534f; font-size:.85rem; display:block; margin-bottom:8px; }
    .contact-btn{
        background:#1C57A3; color:#fff; border:none; padding:13px 34px; border-radius:30px;
        font-weight:700; cursor:pointer; box-shadow:0 6px 16px rgba(28,87,163,.3);
    }
    .contact-btn:hover{ background:#0A2540; color:#fff; }
    .contact-acces{ display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:16px; margin-top:36px; }
    @media (max-width: 767px){ .contact-acces{ grid-template-columns:1fr; } }
    .contact-acces a{ display:flex; align-items:center; gap:14px; background:#f5f8fc; border:1px solid #e3eaf3; border-radius:14px;
        padding:16px 18px; color:#0A2540; font-weight:700; transition:background .15s ease; }
    .contact-acces a i{ font-size:22px; color:#1C57A3; }
    .contact-acces a small{ display:block; font-weight:400; color:#6b7a90; }
    .contact-acces a:hover{ background:#e8f0fa; }
    #contactMap{ height:340px; width:100%; border-radius:16px; z-index:0; }
</style>

<main class="main">

    <section class="contact-hero">
        <div class="container">
            <h1>Nous contacter</h1>
            <p>Une question, un devis, un conseil&nbsp;? Notre équipe vous répond.</p>
        </div>
    </section>

    <section class="contact-section">
        <div class="container">
            <div class="row">

                {{-- Coordonnées --}}
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <h3 class="contact-titre">Nos coordonnées</h3>

                    <div class="contact-info-card">
                        <div class="ico"><i class="fi-rs-marker"></i></div>
                        <div><h6>Adresse</h6><p>Abidjan – Yopougon, rue 12, avenue Jean Marshall, Côte d'Ivoire</p></div>
                    </div>
                    <div class="contact-info-card">
                        <div class="ico"><i class="fi-rs-smartphone"></i></div>
                        <div><h6>Téléphone</h6><p><a href="tel:+2250700130798">(+225) 07 00 13 07 98</a></p></div>
                    </div>
                    <div class="contact-info-card">
                        <div class="ico ico-whatsapp"><i class="fi-rs-comment"></i></div>
                        <div><h6>WhatsApp</h6><p><a href="https://wa.me/2250700130798" target="_blank" rel="noopener">Écrire au 07 00 13 07 98</a><br><small class="text-muted">Réponse rapide aux heures d'ouverture.</small></p></div>
                    </div>
                    <div class="contact-info-card">
                        <div class="ico"><i class="fi-rs-envelope"></i></div>
                        <div><h6>Email</h6><p><a href="mailto:{{ \Help::emailContact() }}">{{ \Help::emailContact() }}</a></p></div>
                    </div>
                    <div class="contact-info-card">
                        <div class="ico"><i class="fi-rs-clock"></i></div>
                        <div><h6>Horaires</h6><p>Du lundi au samedi, de 08:00 à 18:00</p></div>
                    </div>
                </div>

                {{-- Formulaire --}}
                <div class="col-lg-7">
                    <div class="contact-form-wrap">
                        <h3>Envoyez-nous un message</h3>
                        <p style="color:#777;margin-bottom:22px;">Nous vous répondrons dans les plus brefs délais.</p>

                        @if (session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        <form action="{{ route('contact.store') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <label>Nom et prénoms <span style="color:#d9534f">*</span></label>
                                    <input type="text" name="nom_prenoms" class="form-control" value="{{ old('nom_prenoms') }}" placeholder="Votre nom complet" required>
                                    @error('nom_prenoms') <span class="contact-field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label>Email <span style="color:#d9534f">*</span></label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="vous@exemple.com" required>
                                    @error('email') <span class="contact-field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label>Téléphone <span style="color:#d9534f">*</span></label>
                                    <input type="text" name="telephone" class="form-control" value="{{ old('telephone') }}" placeholder="07 00 00 00 00" maxlength="15" required>
                                    @error('telephone') <span class="contact-field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label>Sujet <span style="color:#d9534f">*</span></label>
                                    {{-- L'objet peut être pré-rempli depuis les pages
                                         institutionnelles (candidature livreur, question
                                         sur une livraison…) : le visiteur arrive avec son
                                         motif déjà renseigné. Une saisie en cours (old)
                                         reste prioritaire sur ce pré-remplissage. --}}
                                    <input type="text" name="sujet" class="form-control" value="{{ old('sujet', request('sujet')) }}" placeholder="Objet de votre message" maxlength="50" required>
                                    @error('sujet') <span class="contact-field-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-12">
                                    <label>Message <span style="color:#d9534f">*</span></label>
                                    <textarea name="message" class="form-control" rows="5" placeholder="Décrivez votre demande..." required>{{ old('message') }}</textarea>
                                    @error('message') <span class="contact-field-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <button type="submit" class="contact-btn mt-3"><i class="fi-rs-paper-plane mr-5"></i>Envoyer le message</button>
                        </form>
                    </div>
                </div>

            </div>

            {{-- Accès rapides (lot 109) --}}
            <div class="contact-acces">
                <a href="{{ route('client.index') }}"><i class="fi-rs-shopping-cart"></i><span>Commander des matériaux<small>Gravier, sable, ciment, fer, brique…</small></span></a>
                <a href="{{ route('client.demandeLivraison') }}"><i class="fi-rs-marker"></i><span>Demander une livraison<small>Transport de votre marchandise</small></span></a>
                <a href="{{ route('client.location') }}"><i class="fi-rs-time-fast"></i><span>Louer du matériel<small>Engins et équipements de chantier</small></span></a>
            </div>

            {{-- Carte --}}
            <div class="row mt-5">
                <div class="col-12">
                    <div id="contactMap"></div>
                </div>
            </div>
        </div>
    </section>

</main>

@endsection

@section('jspart')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof L === 'undefined' || !document.getElementById('contactMap')) return;
        // Yopougon, Abidjan (Côte d'Ivoire)
        var lat = 5.3450, lon = -4.0830;
        var map = L.map('contactMap').setView([lat, lon], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        L.marker([lat, lon]).addTo(map)
            .bindPopup('<strong>Mon Gravier</strong> – DALAKOUN SARLU<br>Yopougon, rue 12, avenue Jean Marshall')
            .openPopup();
    });
</script>
@endsection
