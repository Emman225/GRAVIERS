@extends('client.main')
@section('title','Gestion des véhicules')
@section('content')
    @if (session('ok'))
        <div class="alert alert-info text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('ok') }}</div>
    @endif
    @if(session('errorQte'))
        <div class="alert alert-danger text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('errorQte') }}</div>
    @endif
    @if(session('livree'))
        <div class="alert alert-success text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('livree') }}</div>
    @endif

    <main class="main gestion-vehicule-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="gestion-vehicule-hero">
            <div class="gestion-vehicule-hero__inner">
                <span class="gestion-vehicule-hero__chip"><i class="fi-rs-truck-side"></i> Mes véhicules</span>
                <h1 class="gestion-vehicule-hero__title">Gestion de mes véhicules</h1>
                <p class="gestion-vehicule-hero__subtitle">
                    Enregistrez et gérez les véhicules que vous utilisez pour vos commandes.
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-30">
            <div class="row g-4">

                {{-- Formulaire ajout/modification --}}
                <div class="col-lg-5">
                    <div class="gestion-vehicule-card">
                        <div class="gestion-vehicule-card__header">
                            <h5 class="gestion-vehicule-card__title">
                                <i class="fi-rs-edit"></i> Détails du véhicule
                            </h5>
                        </div>
                        <div class="gestion-vehicule-card__body">
                            <div class="alert alert-success text-center" style="display: none" id="success"></div>
                            <div class="alert alert-danger text-center" style="display: none" id="result"></div>
                            <form method="post" id="formVehicule">
                                @csrf
                                <div class="row g-3">
                                    <div class="form-group col-md-12">
                                        <label class="gestion-vehicule-field-label"><i class="fi-rs-truck-side"></i> Type</label>
                                        <select class="form-control gestion-vehicule-input" name="type">
                                            <option value="">Sélectionnez un type...</option>
                                            @foreach ($types as $type)
                                                <option @selected($type->id == $vehicule->type_vehicule_id) value="{{ $type->id }}">{{ $type->libelle }}</option>
                                            @endforeach
                                        </select>
                                        @error('type')
                                            <span class="text-danger" id="type">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group col-md-12">
                                        <label class="gestion-vehicule-field-label"><i class="fi-rs-tag"></i> Marque</label>
                                        <input class="form-control gestion-vehicule-input" value="{{ $vehicule->marque }}" name="marque" />
                                        @error('marque')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group col-md-12">
                                        <label class="gestion-vehicule-field-label"><i class="fi-rs-id-card-clip"></i> Immatriculation <span class="text-danger">*</span></label>
                                        <input class="form-control gestion-vehicule-input" value="{{ $vehicule->immatriculation }}" name="matricule" type="text" />
                                        @error('matricule')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label class="gestion-vehicule-field-label"><i class="fi-rs-truck-couch"></i> Modèle <span class="text-danger">*</span></label>
                                        <input class="form-control gestion-vehicule-input" value="{{ $vehicule->modele }}" name="modele" type="text" />
                                        @error('modele')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label class="gestion-vehicule-field-label"><i class="fi-rs-box-alt"></i> Capacité <span class="text-danger">*</span></label>
                                        <input class="form-control gestion-vehicule-input" value="{{ $vehicule->capacite }}" name="capacite" type="number" />
                                        @error('capacite')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <button type="submit" class="gestion-vehicule-submit mt-3">
                                    <i class="fi-rs-check"></i> Enregistrer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Liste des véhicules --}}
                <div class="col-lg-7">
                    <div class="gestion-vehicule-card">
                        <div class="gestion-vehicule-card__header">
                            <h5 class="gestion-vehicule-card__title">
                                <i class="fi-rs-list"></i> Mes véhicules enregistrés
                            </h5>
                        </div>
                        <div class="gestion-vehicule-card__body">
                            <div class="table-responsive shopping-summery">
                                <table class="table table-wishlist gestion-vehicule-table">
                                    <thead>
                                        <tr class="main-heading">
                                            <th>Immatriculation</th>
                                            <th>Marque</th>
                                            <th>Modèle</th>
                                            <th>Type</th>
                                            <th class="text-end">Capacité</th>
                                            <th>Ajouté le</th>
                                            <th class="text-center" colspan="2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($vehicules as $vehicule)
                                            <tr>
                                                <td><span class="gestion-vehicule-matricule">{{ $vehicule->immatriculation }}</span></td>
                                                <td><strong>{{ $vehicule->marque }}</strong></td>
                                                <td>{{ $vehicule->modele }}</td>
                                                <td><span class="gestion-vehicule-badge">{{ $vehicule->type->libelle }}</span></td>
                                                <td class="text-end fw-bold">{{ $vehicule->capacite }}</td>
                                                <td><small>{{ $vehicule->created_at->format('d/m/Y H:i') }}</small></td>
                                                <td class="text-center">
                                                    <a class="gestion-vehicule-action gestion-vehicule-action--edit" href="{{ route('client.modifierVehicule', $vehicule) }}" title="Modifier">
                                                        <i class="fi-rs-edit"></i>
                                                    </a>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('client.supprimerVehicule', $vehicule) }}" class="gestion-vehicule-action gestion-vehicule-action--delete" title="Supprimer"
                                                       onclick="return confirm('Voulez-vous vraiment supprimer ce véhicule ?')">
                                                        <i class="fi-rs-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="gestion-vehicule-actions">
                            <a class="gestion-vehicule-back" href="{{ route('client.monCompte') }}">
                                <i class="fi-rs-arrow-left"></i> Retour à mon compte
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .gestion-vehicule-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 44px;
            overflow: hidden;
            isolation: isolate;
        }
        .gestion-vehicule-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .gestion-vehicule-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .gestion-vehicule-hero__chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .gestion-vehicule-hero__chip i { color: #fbbf24; font-size: 14px; }
        .gestion-vehicule-hero__title,
        h1.gestion-vehicule-hero__title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .gestion-vehicule-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }

        .gestion-vehicule-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .gestion-vehicule-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .gestion-vehicule-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .gestion-vehicule-card__title i { color: #1c57a3; font-size: 18px; }
        .gestion-vehicule-card__body { padding: 22px; }

        .gestion-vehicule-field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: #374151;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        .gestion-vehicule-field-label i { color: #1c57a3; font-size: 14px; }
        .gestion-vehicule-input {
            padding: 11px 14px !important;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 10px !important;
            background: #ffffff !important;
            font-size: 0.92rem !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            height: auto !important;
        }
        .gestion-vehicule-input:focus {
            border-color: #ea580c !important;
            box-shadow: 0 0 0 3px rgba(234,88,12,0.12) !important;
            outline: none !important;
        }

        .gestion-vehicule-submit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            background: linear-gradient(135deg, #fb923c, #ea580c);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.92rem;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(234,88,12,0.30);
            transition: all 0.18s ease;
        }
        .gestion-vehicule-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(234,88,12,0.42);
        }
        .gestion-vehicule-submit i { font-size: 14px; }

        .gestion-vehicule-table thead th {
            background: #f9fafb !important;
            color: #374151 !important;
            font-weight: 700 !important;
            font-size: 0.74rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 14px 10px !important;
            border-bottom: 1px solid #e5e7eb !important;
            border-top: 0 !important;
        }
        .gestion-vehicule-table tbody td {
            padding: 14px 10px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle !important;
            font-size: 0.88rem;
        }
        .gestion-vehicule-matricule {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #ffffff;
            background: linear-gradient(135deg, #1c57a3, #134380);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.82rem;
            letter-spacing: 0.04em;
        }
        .gestion-vehicule-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            background: #eff6ff;
            color: #1c57a3;
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.78rem;
        }

        .gestion-vehicule-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px; height: 34px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .gestion-vehicule-action--edit { background: #eff6ff; color: #1c57a3 !important; }
        .gestion-vehicule-action--edit:hover { background: #1c57a3; color: #ffffff !important; }
        .gestion-vehicule-action--delete { background: #fef2f2; color: #b91c1c !important; }
        .gestion-vehicule-action--delete:hover { background: #ef4444; color: #ffffff !important; transform: scale(1.05); }
        .gestion-vehicule-action i { font-size: 13px; }

        .gestion-vehicule-actions {
            padding: 16px 22px 20px;
            border-top: 1px solid #f1f5f9;
        }
        .gestion-vehicule-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: #ffffff;
            border: 1.5px solid #e5e7eb;
            color: #374151 !important;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.15s ease;
        }
        .gestion-vehicule-back:hover {
            border-color: #1c57a3;
            color: #1c57a3 !important;
            background: #eff6ff;
        }

        @media (max-width: 575px) {
            .gestion-vehicule-hero { padding: 30px 16px 36px; }
            .gestion-vehicule-hero__title { font-size: 1.5rem; }
        }
    </style>
@endsection

@section('jspart')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('.table').DataTable({
                columnDefs: [{ targets: '_all', defaultContent: '-' }],
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>
@endsection
