@extends('client.main')
@section('title','Contact')
@section('content')

<style>
    .contact-hero{
        background: linear-gradient(135deg, #0b3d2e 0%, #14855a 100%);
        color:#fff; padding:55px 0; text-align:center;
    }
    .contact-hero h1{ color:#fff; font-weight:800; margin-bottom:8px; }
    .contact-hero p{ color:#e8f5ee; margin:0; }
    .contact-section{ padding:55px 0; }
    .contact-info-card{
        background:#fff; border:1px solid #eee; border-radius:14px; padding:24px;
        display:flex; align-items:flex-start; gap:16px; margin-bottom:18px;
    }
    .contact-info-card .ico{
        width:48px; height:48px; min-width:48px; border-radius:10px; display:flex;
        align-items:center; justify-content:center; background:#e7f6ee; color:#14855a; font-size:22px;
    }
    .contact-info-card h6{ font-weight:700; margin:0 0 4px; color:#0b3d2e; }
    .contact-info-card p{ margin:0; color:#666; }
    .contact-form-wrap{
        background:#fff; border:1px solid #eee; border-radius:16px; padding:32px;
        box-shadow:0 8px 24px rgba(0,0,0,.04);
    }
    .contact-form-wrap h3{ font-weight:800; color:#0b3d2e; margin-bottom:6px; }
    .contact-form-wrap .form-control{
        border:1px solid #dce3df; border-radius:10px; padding:12px 14px; margin-bottom:4px;
    }
    .contact-form-wrap .form-control:focus{ border-color:#14855a; box-shadow:0 0 0 .15rem rgba(20,133,90,.15); }
    .contact-form-wrap label{ font-weight:600; color:#33433c; margin-bottom:6px; }
    .contact-field-error{ color:#d9534f; font-size:.85rem; display:block; margin-bottom:8px; }
    .contact-btn{
        background:#14855a; color:#fff; border:none; padding:13px 34px; border-radius:30px;
        font-weight:700; cursor:pointer;
    }
    .contact-btn:hover{ background:#0f6e49; }
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
                    <h3 style="font-weight:800;color:#0b3d2e;margin-bottom:20px;">Nos coordonnées</h3>

                    <div class="contact-info-card">
                        <div class="ico"><i class="fi-rs-marker"></i></div>
                        <div><h6>Adresse</h6><p>Abidjan – Yopougon, Rue 12 Avenue Jean Marshall, Côte d'Ivoire</p></div>
                    </div>
                    <div class="contact-info-card">
                        <div class="ico"><i class="fi-rs-phone-call"></i></div>
                        <div><h6>Téléphone</h6><p><a href="tel:+22507273333" style="color:#14855a;">(+225) 07 27 3333 - 3333</a></p></div>
                    </div>
                    <div class="contact-info-card">
                        <div class="ico"><i class="fi-rs-envelope"></i></div>
                        <div><h6>Email</h6><p><a href="mailto:support@gravier.com" style="color:#14855a;">support@gravier.com</a></p></div>
                    </div>
                    <div class="contact-info-card">
                        <div class="ico"><i class="fi-rs-clock"></i></div>
                        <div><h6>Horaires</h6><p>Tous les jours de 8h00 à 22h00</p></div>
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
                                    <input type="text" name="sujet" class="form-control" value="{{ old('sujet') }}" placeholder="Objet de votre message" maxlength="50" required>
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
            .bindPopup('<strong>DALAKOUN SARL</strong><br>Yopougon, Rue 12 Avenue Jean Marshall')
            .openPopup();
    });
</script>
@endsection
