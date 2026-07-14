@php
    $i = 0;
@endphp

@extends('client.main')
@section('title', 'Mode de paiement')
@section('content')
    <div class="alert alert-info text-center modifie coller-en-haut mt-5" style="display: none;" id="notify">
        <span>Mis à jour effectuée</span>
    </div>

    <main class="main paiement-main">
        {{-- ===== HERO ===== --}}
        <section class="paiement-hero">
            <div class="paiement-hero__inner">
                <span class="paiement-hero__chip"><i class="fi-rs-credit-card"></i> Étape 3 / 3</span>
                <h1 class="paiement-hero__title">Mode de paiement</h1>
                <p class="paiement-hero__subtitle">Finalisez votre commande en choisissant comment payer.</p>

                <ol class="paiement-steps">
                    <li class="paiement-steps__item is-done"><span>1</span> Panier</li>
                    <li class="paiement-steps__item is-done"><span>2</span> Livraison</li>
                    <li class="paiement-steps__item is-active"><span>3</span> Paiement</li>
                </ol>
            </div>
        </section>

        <div class="container mb-80 mt-40">
            <div class="row g-4">

                {{-- ===== COLONNE 1 : Bon de commande + Mode de paiement ===== --}}
                <div class="col-lg-4">
                    <div class="paiement-card">
                        <div class="paiement-card__header">
                            <h5 class="paiement-card__title"><i class="fi-rs-document"></i> Informations bon de commande</h5>
                        </div>
                        <div class="paiement-card__body">
                            <form method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="row shipping_calculator">
                                    <div class="form-group col-lg-12">

                                        @if ($client->type_client == 'ENTREPRISE')
                                            <div class="custom_select mb-3">
                                                <label class="paiement-field-label"><i class="fi-rs-file"></i> Numéro de bon de commande</label>
                                                <input type="text" required placeholder="Entrez un numéro de bon de commande"
                                                       class="form-control paiement-input" name="numero_bon">
                                            </div>
                                            <div class="custom_select mb-3">
                                                <label class="paiement-field-label"><i class="fi-rs-upload"></i> Joindre le bon de commande</label>
                                                <input type="file" required class="form-control paiement-input" name="fichier">
                                            </div>
                                        @endif

                                        <div class="custom_select mb-3">
                                            <label class="paiement-field-label"><i class="fi-rs-shipping-fast"></i> Type de livraison</label>
                                            <select required class="form-control paiement-select" name="type_livraison">
                                                <option value="">Type de livraison...</option>
                                                @foreach ($typeLivraison as $type)
                                                    <option @selected($type->libelle == 'En vrac') value="{{ $type->id }}">{{ $type->libelle }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="custom_select mb-3">
                                            <label class="paiement-field-label" for="date_livraison"><i class="fi-rs-calendar"></i> Date de livraison souhaitée</label>
                                            <input type="date" name="date_livraison" min="{{now()->format('Y-m-d')}}" required class="form-control paiement-input" id="date_livraison">
                                        </div>

                                        <div class="custom_select mb-3">
                                            <label class="paiement-field-label"><i class="fi-rs-credit-card"></i> Mode de paiement</label>
                                            <select required class="form-control paiement-select" name="mode">
                                                <option value="">Choisir le mode...</option>
                                                @foreach ($modes as $mode)
                                                    <option value="{{ $mode->id }}">{{ $mode->libelle }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                    </div>
                                </div>

                                {{-- Boutons cachés (préservés pour formaction) --}}
                                @if (session('type') == 'commande')
                                    <button type="submit" style="display: none" formaction="{{ route('client.recapCommande') }}"
                                            class="btn btn-fill-out btn-block mt-30" id="recap">c'est une commande direct <i class="fi-rs-check ml-15"></i></button>
                                    <button type="submit" id="enregistrerEnDevis" style="display: none" formaction="{{ route('devis.recapDevis') }}">c'est une commande directe</button>
                                @endif

                                @if (session('type') == 'devis')
                                    <button type="submit" style="display: none" formaction="{{ route('client.recapCommandeVenantDunDevis', $devis) }}"
                                            class="btn btn-fill-out btn-block mt-30" id="recapDevis">c'est un devis qui passe en commande<i class="fi-rs-check ml-15"></i></button>
                                @endif

                                @if (session('type') == 'location')
                                    <button type="submit" style="display: none" formaction="{{ route('client.recapLocation') }}" class="btn btn-fill-out btn-block mt-30" id="recapLocation">c'est une location<i class="fi-rs-check ml-15"></i></button>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ===== COLONNE 2 : Code promo + Points ===== --}}
                <div class="col-lg-3">
                    <div class="paiement-card mb-4">
                        <div class="paiement-card__header">
                            <h5 class="paiement-card__title"><i class="fi-rs-label"></i> Code promo</h5>
                        </div>
                        <div class="paiement-card__body">
                            <p class="paiement-card__desc">Vous avez un code promo ? Saisissez-le pour réduire votre facture.</p>
                            <form id="formPromo" method="post" action="{{ route('client.AppliquerCodePromo') }}">
                                @csrf
                                <input type="text" name="code" class="form-control paiement-input mb-3" placeholder="Entrez votre code">
                                @if (isset($devis))
                                    <input type="hidden" name="devis_id" value="{{ $devis->id }}">
                                @endif
                                <button type="submit" class="paiement-secondary-btn w-100">
                                    <i class="fi-rs-check"></i> Appliquer
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="paiement-card">
                        <div class="paiement-card__header">
                            <h5 class="paiement-card__title"><i class="fi-rs-star"></i> Points fidélité</h5>
                        </div>
                        <div class="paiement-card__body">
                            <p class="paiement-card__desc">
                                <strong>1 pt = {{ number_format($conf->montant_point, '0', '', ' ') }} fcfa</strong>
                            </p>
                            <form method="post" id="formPoint" action="{{ route('client.AppliquerPointDeReduction') }}">
                                @csrf
                                @if (isset($devis))
                                    <input type="hidden" name="devis_id" value="{{ $devis->id }}">
                                @endif
                                <input type="text" name="point" placeholder="Nombre de points..." class="form-control paiement-input mb-3">
                                <button type="submit" class="paiement-secondary-btn w-100">
                                    <i class="fi-rs-check"></i> Appliquer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ===== COLONNE 3 : Récap ===== --}}
                <div class="col-lg-5">
                    <div class="paiement-card paiement-summary">
                        <div class="paiement-card__header">
                            <h5 class="paiement-card__title"><i class="fi-rs-shopping-bag"></i> Votre commande</h5>
                        </div>
                        <div class="paiement-card__body">
                            @if (session('type') !== 'location')
                                <div class="mb-3"></div>
                            @else
                                <div class="d-flex align-items-end justify-content-between mb-3">
                                    @if (session('point_reduc') || session('reduction_id'))
                                        <h1 class="fw-bold barre">
                                            {{ number_format(session('totalLocation') + session('0')['cout_livraison'], 0, '', ' ') }}fcfa
                                        </h1>
                                    @endif
                                </div>
                            @endif

                            {{-- ====== Recap DEVIS ====== --}}
                            @if (session('type') == 'devis')
                                <div class="table-responsive order_table checkout">
                                    <table class="table no-border paiement-products">
                                        <tbody>
                                            @foreach ($devis->detailDevis as $detail)
                                                <tr>
                                                    <td class="image product-thumbnail">
                                                        <img src="{{ asset("storage/".$detail->produit->image->first()->image) }}" alt="{{ $detail->produit->nom }}">
                                                    </td>
                                                    <td>
                                                        <h6 class="w-160 mb-5 paiement-product-name">{{ $detail->produit->nom }}</h6>
                                                        @if(($detail->produit->meilleur_note ?? 0) > 0)
                                                            <div class="product-rate-cover">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width :{{ $detail->produit->meilleur_note }}%"></div>
                                                                </div>
                                                                <span class="font-small ml-5 text-muted">({{ round(($detail->produit->meilleur_note * 5) / 100, 1) }})</span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td><h6 class="text-muted pl-20 pr-20">x {{ $detail->qte }}</h6></td>
                                                    <td>
                                                        <h4 class="text-brand">
                                                            @if(isset($prixPerso[$detail->produit->id]))
                                                                {{ Help::formatNombre($prixPerso[$detail->produit->id],true) }}
                                                            @else
                                                                {{ Help::formatNombre($detail->produit->prix_moyen,true) }}
                                                            @endif
                                                        </h4>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    <table class="table no-border col-12 paiement-totals">
                                        <tbody>
                                            <tr>
                                                <td><h6 class="text-muted">Montant HT</h6></td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                    <h6 class="text-brand text-end">{{ Help::formatNombre($devis->montant, true) }}</h6>
                                                </td>
                                            </tr>
                                            <tr id="mpRemise">
                                                <th class="cart_total_label"><h6 class="laRemise">Montant remise</h6></th>
                                                <th></th>
                                                <th class="cart_total_amount">
                                                    <h6 class="text-brand text-end"> <span id="laRemise" class="js-remise">0</span> fcfa</h6>
                                                </th>
                                            </tr>
                                            <tr>
                                                <td class="cart_total_label"><h6 class="text-muted">TVA</h6></td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                    <h6 class="text-brand text-end"> <span id="montant_total" class="js-tva">{{ Help::formatNombre($devis->tva,true) }}</span></h6>
                                                </td>
                                            </tr>
                                            @if ($devis->cout_livraison)
                                                <tr>
                                                    <td class="cart_total_label"><h6 class="text-muted">Coût livraison</h6></td>
                                                    <td></td>
                                                    <td class="cart_total_amount">
                                                        <h6 class="text-brand text-end"> <span id="montant_total">{{ Help::formatNombre($devis->cout_livraison, true) }}</span></h6>
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th class="cart_total_label"><h6 class="text-muted text-start">Montant TTC</h6></th>
                                                <th></th>
                                                <th class="cart_total_amount">
                                                    <h6 class="text-brand text-end paiement-total-final"> <span id="montant_total">{{ Help::formatNombre($devis->montant + $devis->tva + $devis->cout_livraison, true) }}</span></h6>
                                                </th>
                                            </tr>
                                            <tr id="mpMontantTotal">
                                                <th class="cart_total_label"><h6 class="">Montant Total</h6></th>
                                                <th></th>
                                                <th class="cart_total_amount">
                                                    <h6 class="text-brand text-end"><span id="leMontantTotal" class="js-montant-net">{{ Help::formatNombre($devis->montant + $devis->tva + $devis->cout_livraison, true) }}</span> fcfa</h6>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th colspan="3">
                                                    <span class="text-danger" id="messageAlert">
                                                        @if($devis->montant + $devis->tva + $devis->cout_livraison > 2000000)
                                                            Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.
                                                        @endif
                                                    </span>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <label for="recapDevis" class="paiement-cta">
                                    <i class="fi-rs-check"></i> Récapitulatif de la commande
                                </label>
                            @endif

                            {{-- ====== Recap COMMANDE ====== --}}
                            @if (session('type') == 'commande')
                                <div class="table-responsive order_table checkout">
                                    <table class="table no-border paiement-products">
                                        <tbody>
                                            @foreach (Cart::content() as $produit)
                                                <tr>
                                                    <td class="image product-thumbnail"><img src="storage/{{ $produit->options->image }}" alt="{{ $produit->name }}"></td>
                                                    <td>
                                                        <h6 class="w-160 mb-5 paiement-product-name">{{ $produit->name }}</h6>
                                                        @if(($produit->options->note ?? 0) > 0)
                                                            <div class="product-rate-cover">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width :{{ $produit->options->note }}%"></div>
                                                                </div>
                                                                <span class="font-small ml-5 text-muted">({{ round(($produit->options->note * 5) / 100, 1) }})</span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td><h6 class="text-muted pl-20 pr-20">x {{ $produit->qty }}</h6></td>
                                                    <td><h4 class="text-brand">{{ number_format($produit->price, 0, '', ' ') }} <small>fcfa</small></h4></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    <table class="table no-border col-12 paiement-totals">
                                        <tbody>
                                            <tr>
                                                <td><h6 class="text-muted">Montant HT</h6></td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                    <h6 class="text-brand text-end">{{ number_format($total, 0, '', ' ') }} fcfa</h6>
                                                </td>
                                            </tr>
                                            <tr id="mpRemise">
                                                <th class="cart_total_label"><h6 class="laRemise">Montant remise</h6></th>
                                                <th></th>
                                                <th class="cart_total_amount">
                                                    <h6 class="text-brand text-end">- <span id="laRemise" class="js-remise">0</span> fcfa</h6>
                                                </th>
                                            </tr>
                                            <tr>
                                                <td class="cart_total_label"><h6 class="text-muted">TVA</h6></td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                    <h6 class="text-brand text-end"> <span id="mpTVA" class="js-tva">{{ number_format($total * $tva, 0, '', ' ') }}</span> fcfa</h6>
                                                </td>
                                            </tr>
                                            @if (session('0')['ville'] != null)
                                                <tr>
                                                    <td class="cart_total_label"><h6 class="text-muted">Coût livraison</h6></td>
                                                    <td></td>
                                                    <td class="cart_total_amount">
                                                        <h6 class="text-brand text-end"> <span id="montant_total">({{ session('0')['km'] }} km) {{ number_format(session('0')['cout_livraison'], 0, '', ' ') }}</span> fcfa</h6>
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                        <tfoot>
                                            <tr id="mpMontantTTC">
                                                <th class="cart_total_label"><h6 class="text-muted text-start">Montant TTC</h6></th>
                                                <th></th>
                                                <th class="cart_total_amount">
                                                    <h6 class="text-brand text-end paiement-total-final"> <span id="montant_total">{{ number_format($total + $total * $tva + session('0')['cout_livraison'], 0, '', ' ') }}</span> fcfa</h6>
                                                </th>
                                            </tr>
                                            <tr id="mpMontantTotal">
                                                <th class="cart_total_label"><h6 class="">Montant TTC</h6></th>
                                                <th></th>
                                                <th class="cart_total_amount">
                                                    <h6 class="text-brand text-end"> <span id="leMontantTotal" class="js-montant-net">{{ number_format($total + $total * $tva + session('0')['cout_livraison'], 0, '', ' ') }}</span> fcfa</h6>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th colspan="3">
                                                    <span class="text-danger" id="messageAlert">
                                                        @if($total + $total * $tva + session('0')['cout_livraison'] > 2000000)
                                                            Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.
                                                        @endif
                                                    </span>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <label for="recap" class="paiement-cta">
                                        <i class="fi-rs-check"></i> Récapitulatif de la commande
                                    </label>
                                </div>
                            @endif

                            {{-- ====== Recap LOCATION ====== --}}
                            @if (session('type') == 'location')
                                <div class="table-responsive order_table checkout">
                                    <table class="table no-border paiement-products">
                                        <tbody>
                                            @foreach (Cart::content() as $produit)
                                                <tr>
                                                    <td class="image product-thumbnail"><img src="storage/{{ $produit->options->image }}" alt="{{ $produit->name }}"></td>
                                                    <td>
                                                        <h6 class="w-160 mb-5 paiement-product-name">{{ $produit->name }}</h6>
                                                        @if(($produit->options->note ?? 0) > 0)
                                                            <div class="product-rate-cover">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width :{{ $produit->options->note }}%"></div>
                                                                </div>
                                                                <span class="font-small ml-5 text-muted">({{ round(($produit->options->note * 5) / 100, 1) }})</span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td><h6 class="text-muted pl-20 pr-20">x {{ $produit->qty }}</h6></td>
                                                    <td><h6 class="text-muted pl-20 pr-20">{{ session('nbre_jour')[$i] }}j</h6></td>
                                                    <td><h4 class="text-brand">{{ number_format($produit->price, 0, '', ' ') }} <small>fcfa</small></h4></td>
                                                </tr>
                                                @php $i++; @endphp
                                            @endforeach
                                        </tbody>
                                    </table>
                                    <table class="table no-border col-12 paiement-totals">
                                        <tbody>
                                            <tr>
                                                <td><h6 class="text-muted">Montant HT</h6></td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                    <h6 class="text-brand text-end">{{ number_format($total, 0, '', ' ') }} fcfa</h6>
                                                </td>
                                            </tr>
                                            {{-- Remise (code promo / points) : masquée tant qu'aucune remise appliquée,
                                                 affichée par le JS (#formPromo / #formPoint dans ajoutProduit.js). --}}
                                            <tr id="mpRemise" style="display:none">
                                                <th class="cart_total_label"><h6 class="laRemise">Montant remise</h6></th>
                                                <th></th>
                                                <th class="cart_total_amount">
                                                    <h6 class="text-brand text-end">- <span id="laRemise" class="js-remise">0</span> fcfa</h6>
                                                </th>
                                            </tr>
                                            <tr>
                                                <td class="cart_total_label"><h6 class="text-muted">TVA</h6></td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                    <h6 class="text-brand text-end"> <span id="mpTVA" class="js-tva">{{ number_format($total * $tva, 0, '', ' ') }}</span> fcfa</h6>
                                                </td>
                                            </tr>
                                            @if (session('0')['ville'] != null)
                                                <tr>
                                                    <td class="cart_total_label"><h6 class="text-muted">Coût livraison</h6></td>
                                                    <td></td>
                                                    <td class="cart_total_amount">
                                                        <h6 class="text-brand text-end"> <span>({{ session('0')['km'] }} km) {{ number_format(session('0')['cout_livraison'], 0, '', ' ') }}</span> fcfa</h6>
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                        <tfoot>
                                            {{-- Montant TTC avant remise : masqué par le JS dès qu'une remise est appliquée. --}}
                                            <tr id="mpMontantTTC">
                                                <th class="cart_total_label"><h6 class="text-muted text-start">Montant TTC</h6></th>
                                                <th></th>
                                                <th class="cart_total_amount">
                                                    <h6 class="text-brand text-end paiement-total-final"> <span>{{ number_format($total + $total * $tva + (session('0')['cout_livraison'] ?? 0), 0, '', ' ') }}</span> fcfa</h6>
                                                </th>
                                            </tr>
                                            {{-- Montant TTC net après remise : masqué au départ, affiché par le JS. --}}
                                            <tr id="mpMontantTotal" style="display:none">
                                                <th class="cart_total_label"><h6 class="">Montant TTC</h6></th>
                                                <th></th>
                                                <th class="cart_total_amount">
                                                    <h6 class="text-brand text-end"> <span id="leMontantTotal" class="js-montant-net">{{ number_format($total + $total * $tva + (session('0')['cout_livraison'] ?? 0), 0, '', ' ') }}</span> fcfa</h6>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th colspan="3">
                                                    <span class="text-danger" id="messageAlert">
                                                        @if($total + $total * $tva + (session('0')['cout_livraison'] ?? 0) > 2000000)
                                                            Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.
                                                        @endif
                                                    </span>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <label for="recapLocation" class="paiement-cta">
                                        <i class="fi-rs-check"></i> Récapitulatif de la location
                                    </label>
                                </div>
                            @endif

                            {{-- Trust badges --}}
                            <ul class="paiement-summary__trust">
                                <li><i class="fi-rs-shield-check"></i> Paiement sécurisé</li>
                                <li><i class="fi-rs-truck-side"></i> Livraison suivie</li>
                                <li><i class="fi-rs-headset"></i> Support 7j/7</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <style>
        /* ===== HERO ===== */
        .paiement-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 44px 20px 32px;
            overflow: hidden;
            isolation: isolate;
        }
        .paiement-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .paiement-hero__inner { max-width: 1180px; margin: 0 auto; text-align: center; }
        .paiement-hero__chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            margin-bottom: 10px;
        }
        .paiement-hero__chip i { color: #fbbf24; font-size: 14px; }
        .paiement-hero__title,
        h1.paiement-hero__title {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .paiement-hero__subtitle {
            margin: 0 0 22px;
            color: rgba(255,255,255,0.92);
            font-size: 0.92rem;
            text-shadow: 0 1px 6px rgba(0,0,0,0.25);
        }

        /* Stepper */
        .paiement-steps {
            display: inline-flex;
            list-style: none;
            padding: 0; margin: 0;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 999px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            overflow: hidden;
        }
        .paiement-steps__item {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 16px;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.65);
            font-weight: 600;
            position: relative;
        }
        .paiement-steps__item + .paiement-steps__item::before {
            content: "›";
            position: absolute;
            left: -3px;
            color: rgba(255,255,255,0.4);
            font-size: 1rem;
        }
        .paiement-steps__item span {
            width: 22px; height: 22px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
            color: rgba(255,255,255,0.8);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .paiement-steps__item.is-done { color: #ffffff; }
        .paiement-steps__item.is-done span { background: rgba(16, 185, 129, 0.85); color: #fff; }
        .paiement-steps__item.is-active { color: #ffffff; }
        .paiement-steps__item.is-active span {
            background: #fbbf24;
            color: #0a2540;
            box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.30);
        }

        /* Conteneur principal au-dessus des bannières de service du footer */
        .paiement-main {
            position: relative;
            z-index: 20;
            padding-bottom: 80px; /* espace pour ne pas chevaucher le footer */
        }
        .paiement-main .container { position: relative; z-index: 20; }

        /* ===== CARDS ===== */
        .paiement-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
            position: relative;
            z-index: 21;
        }
        .paiement-card__header {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .paiement-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .paiement-card__title i { color: #1c57a3; font-size: 16px; }
        .paiement-card__body { padding: 18px 20px; }
        .paiement-card__desc {
            color: #6b7280;
            font-size: 0.86rem;
            margin: 0 0 12px;
        }
        .paiement-card__desc strong { color: #0a2540; font-weight: 700; }

        /* ===== FORM ===== */
        .paiement-field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: #374151;
            font-size: 0.82rem;
            margin-bottom: 6px;
        }
        .paiement-field-label i { color: #1c57a3; font-size: 14px; }
        .paiement-input,
        .paiement-select {
            display: block;
            width: 100%;
            padding: 11px 14px !important;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 10px !important;
            background: #ffffff !important;
            color: #111827 !important;
            font-size: 0.9rem !important;
            line-height: 1.4 !important;
            height: auto !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .paiement-input:focus,
        .paiement-select:focus {
            border-color: #ea580c !important;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12) !important;
            outline: none !important;
        }
        .paiement-input[type="file"] { padding: 8px 12px !important; }

        /* ===== BOUTONS ===== */
        .paiement-secondary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 16px;
            background: #ffffff;
            border: 1.5px solid #10b981;
            color: #10b981 !important;
            font-weight: 700;
            font-size: 0.88rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.18s ease;
        }
        .paiement-secondary-btn:hover {
            background: #10b981;
            color: #ffffff !important;
            box-shadow: 0 8px 18px rgba(16, 185, 129, 0.30);
        }
        .paiement-secondary-btn i { font-size: 14px; }

        .paiement-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 18px;
            margin-top: 18px;
            background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 12px;
            text-decoration: none;
            box-shadow: 0 10px 22px rgba(234, 88, 12, 0.32);
            transition: all 0.18s ease;
            letter-spacing: 0.01em;
            cursor: pointer;
        }
        .paiement-cta:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            box-shadow: 0 14px 28px rgba(234, 88, 12, 0.42);
            color: #ffffff !important;
        }
        .paiement-cta i { font-size: 16px; }

        /* ===== SUMMARY ===== */
        .paiement-summary { position: sticky; top: 20px; }
        .paiement-products img {
            width: 56px; height: 56px;
            object-fit: cover;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .paiement-product-name {
            color: #0a2540 !important;
            font-weight: 700;
            font-size: 0.92rem;
            margin: 0 0 4px;
            line-height: 1.4;
        }
        .paiement-totals { margin: 0; }
        .paiement-totals td, .paiement-totals th { padding: 6px 0 !important; border: 0 !important; }
        .paiement-totals .text-brand { font-weight: 700; color: #0a2540 !important; }
        .paiement-total-final {
            font-size: 1.3rem !important;
            color: #ea580c !important;
            font-weight: 800 !important;
        }
        #messageAlert {
            display: block;
            font-size: 0.82rem;
            line-height: 1.5;
            margin-top: 6px;
        }
        #messageAlert:not(:empty) {
            padding: 10px 12px;
            background: #fef2f2;
            border-radius: 8px;
            border-left: 3px solid #ef4444;
            color: #b91c1c;
        }
        .paiement-summary__trust {
            list-style: none;
            padding: 16px 0 0;
            margin: 16px 0 0;
            border-top: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .paiement-summary__trust li {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4b5563;
            font-size: 0.84rem;
            font-weight: 500;
        }
        .paiement-summary__trust i {
            color: #10b981;
            font-size: 14px;
            width: 26px; height: 26px;
            background: #ecfdf5;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991px) {
            .paiement-summary { position: static; }
        }
        @media (max-width: 575px) {
            .paiement-hero { padding: 34px 16px 28px; }
            .paiement-hero__title { font-size: 1.5rem; }
            .paiement-steps__item { padding: 6px 12px; font-size: 0.72rem; }
        }
    </style>
@endsection
@section('jspart')

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const dateInput = document.getElementById("date_livraison");

            // Définir la date minimale (aujourd'hui)
            const today = new Date().toISOString().split("T")[0];
            dateInput.setAttribute("min", today);

            // Validation à la SORTIE du champ (blur), pas sur "change" :
            // un champ <input type="date"> émet "change" à chaque segment saisi,
            // donc pendant la frappe de l'année (ex. 0002-06-22) la date paraît
            // antérieure à aujourd'hui et l'alerte se déclenchait prématurément.
            // On ne valide qu'une fois la saisie terminée (année complète).
            dateInput.addEventListener("blur", function () {
                if (dateInput.value && dateInput.value < today) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Date de livraison',
                            text: "La date de livraison ne peut pas être antérieure à aujourd'hui.",
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#ea580c',
                        });
                    } else {
                        alert("La date de livraison ne peut pas être antérieure à aujourd'hui.");
                    }
                    dateInput.value = ""; // Réinitialise le champ
                }
            });
        });
    </script>

    <script type="text/javascript">
        $(function() {
            $('#produits').select();
        });
    </script>

    {{-- L'application AJAX du code promo et des points de fidélité (avec SweetAlert2
         et mise à jour des totaux) est gérée de façon centralisée dans
         public/frontend/assets/js/ajoutProduit.js (handlers #formPromo / #formPoint).
         L'ancien handler inline (alert() basique) a été retiré : il faisait double
         emploi et provoquait l'affichage d'une alerte basique AVANT le SweetAlert2. --}}
@endsection
