@props([
    'tableId' => 'liste',
    'container' => null,
    'filename' => 'export',
    'title' => null,
    'pdfUrl' => null,
    'wordUrl' => null,
    'excelUrl' => null,
])
{{--
    Boutons d'export Excel, Word et PDF d'un tableau HTML.

    Usage courant — un seul tableau :
        <x-export-buttons table-id="maTable" filename="liste-clients" title="Liste des clients" />

    Usage document — plusieurs tableaux dans un même bloc :
        <x-export-buttons container="bonEnlevement" filename="bon-132727" title="Bon n° 132727" />

    - tableId  : id du <table> à exporter
    - container: id d'un conteneur ; TOUS ses tableaux sont exportés à la suite,
                 chacun précédé de son titre. Prioritaire sur tableId.
    - filename : nom de fichier (sans extension)
    - title    : titre porté en tête du document (défaut : filename)
    - pdfUrl   : adresse d'un PDF produit par le serveur. Fournie, elle remplace
                 la génération dans le navigateur — un document mis en page par
                 le serveur vaut toujours mieux qu'un tableau reconstitué.
    - wordUrl  : idem pour le Word.
    - excelUrl : idem pour le classeur. Utile quand l'export doit porter sur
                 TOUTE la donnée et non sur les seules lignes affichées, ou
                 quand il doit en écarter une partie (une corbeille, par
                 exemple) que l'écran, lui, montre.

    EXPORT PAR PÉRIODE (lot 81, 15/09/2026) : deux dates « du / au » précèdent les
    boutons. Renseignées, l'export ne retient que les lignes dont la colonne de
    date tombe dans la période (colonne repérée par son en-tête — date, jour,
    créé… — sinon la première colonne où la plupart des cellules portent une
    date) ; vides — bouton « Tout » —, l'export emporte toutes les lignes.
    Un tableau sans colonne de date est exporté entier, et le dit.

    Charge SheetJS (XLSX) + jsPDF (+ autotable) en CDN à la première utilisation.
    Le Word, lui, ne demande aucune bibliothèque : Word ouvre nativement un
    document HTML, et c'est ainsi que le format .doc se produit sans dépendance.
--}}
@once
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script>
        window.GravierExport = window.GravierExport || (function () {
            function texteDe(cellule) {
                // Une colonne qui ne porte que des boutons d'action n'a rien à
                // faire dans un export : on la vide plutôt que d'y recopier
                // « Voir Télécharger Supprimer ».
                // Un <a> SANS href n'est pas un bouton : le thème enveloppe le
                // nom du produit dans <a class="itemside"> — la colonne
                // « Produit » sortait vide de l'export (08/09/2026).
                var queDesBoutons = cellule.children.length > 0 &&
                    Array.prototype.every.call(cellule.children, function (c) {
                        return (c.tagName === 'A' && c.hasAttribute('href')) || c.tagName === 'BUTTON' || c.tagName === 'FORM';
                    }) && cellule.textContent.trim().replace(/\s+/g, ' ').length < 30;
                return queDesBoutons ? '' : cellule.textContent.trim().replace(/\s+/g, ' ');
            }

            /**
             * TOUTES LES LIGNES, PAS SEULEMENT LA PAGE AFFICHEE.
             *
             * DataTables RETIRE DU DOM les lignes des autres pages. Lire
             * « tbody tr » ne rendait donc que la page courante : sur une liste
             * de 31 commandes paginee par 10, l'export en emportait 10 et se
             * taisait sur les 21 autres. Un fichier tronque sans le dire est
             * pire que pas de fichier du tout.
             *
             * On demande donc ses lignes a DataTables quand il pilote le
             * tableau. « search: 'applied' » respecte la recherche en cours :
             * on exporte ce que l'utilisateur a sous les yeux, toutes pages
             * confondues — et non le tableau entier s'il a filtre.
             */
            function lignesDe($table) {
                if (window.jQuery && jQuery.fn.dataTable
                    && jQuery.fn.dataTable.isDataTable($table)) {
                    return jQuery($table).DataTable()
                        .rows({ search: 'applied' }).nodes().toArray();
                }
                return Array.prototype.slice.call($table.querySelectorAll('tbody tr'));
            }

            /** Une date lue dans une cellule : jj/mm/aaaa (avec ou sans heure), aaaa-mm-jj, jj-mm-aaaa. */
            function dateDe(texte) {
                var m = /(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/.exec(texte || '');
                if (m) return new Date(+m[3], +m[2] - 1, +m[1]);
                m = /(\d{4})-(\d{2})-(\d{2})/.exec(texte || '');
                if (m) return new Date(+m[1], +m[2] - 1, +m[3]);
                return null;
            }

            /**
             * La colonne qui date chaque ligne : d'abord un en-tête qui parle de
             * date, sinon la première colonne où la plupart des cellules
             * renseignées portent une date. -1 quand le tableau n'en a pas.
             */
            function colonneDate(headers, rows) {
                var candidats = [];
                headers.forEach(function (h, i) {
                    if (/date|jour|p[ée]riode|cr[ée]{1,2}|\ble\b|\bdu\b/i.test(h)) candidats.push(i);
                });
                headers.forEach(function (h, i) { if (candidats.indexOf(i) < 0) candidats.push(i); });
                for (var k = 0; k < candidats.length; k++) {
                    var i = candidats[k], avec = 0, total = 0;
                    rows.forEach(function (r) {
                        if (r[i] && r[i].trim()) { total++; if (dateDe(r[i])) avec++; }
                    });
                    if (total > 0 && avec >= Math.max(1, Math.ceil(total * 0.6))) return i;
                }
                return -1;
            }

            /** Ne garde que les lignes de la période ; marque le bloc sans colonne de date. */
            function filtrerParPeriode(bloc, periode) {
                if (!periode) return bloc;
                var i = colonneDate(bloc.headers, bloc.rows);
                if (i < 0) { bloc.sansDate = true; return bloc; }
                var du = periode.du ? dateDe(periode.du) : null;
                var au = periode.au ? dateDe(periode.au) : null;
                bloc.rows = bloc.rows.filter(function (r) {
                    var d = dateDe(r[i]);
                    if (!d) return false;
                    return (!du || d >= du) && (!au || d <= au);
                });
                return bloc;
            }

            /** La période saisie à côté du bouton cliqué ; null = tout exporter. */
            function periodeDe(bouton) {
                var zone = bouton && bouton.closest ? bouton.closest('.export-periode') : null;
                if (!zone) return null;
                var du = (zone.querySelector('.export-du') || {}).value || '';
                var au = (zone.querySelector('.export-au') || {}).value || '';
                if (!du && !au) return null;
                if (du && au && du > au) {
                    prevenir('Période incohérente', 'La date de début est postérieure à la date de fin.');
                    return { invalide: true };
                }
                return { du: du, au: au };
            }

            function jjmmaaaa(iso) {
                var m = /(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
                return m ? m[3] + '/' + m[2] + '/' + m[1] : iso;
            }

            function libellePeriode(periode) {
                if (!periode) return '';
                if (periode.du && periode.au) return 'du ' + jjmmaaaa(periode.du) + ' au ' + jjmmaaaa(periode.au);
                if (periode.du) return 'depuis le ' + jjmmaaaa(periode.du);
                return "jusqu'au " + jjmmaaaa(periode.au);
            }

            function suffixePeriode(periode) {
                return periode ? '-du-' + (periode.du || 'debut') + '-au-' + (periode.au || 'fin') : '';
            }

            /** SweetAlert2 quand il est là (règle du 10/09/2026 : jamais d'alerte native). */
            function prevenir(titre, texte) {
                if (window.Swal) { window.Swal.fire({ icon: 'info', title: titre, text: texte }); }
                else { console.warn('[export] ' + titre + ' : ' + texte); }
            }

            /**
             * Les blocs à exporter, filtrés sur la période du bouton cliqué, avec
             * le nom de fichier et le titre qui la disent. null = rien à exporter.
             */
            function preparer(cible, filename, title, bouton) {
                var periode = periodeDe(bouton);
                if (periode && periode.invalide) return null;
                var blocs = lireBlocs(cible);
                if (!blocs) return null;
                if (periode) {
                    var sansDate = false;
                    blocs = blocs.map(function (b) { b = filtrerParPeriode(b, periode); if (b.sansDate) sansDate = true; return b; });
                    if (sansDate) {
                        prevenir('Pas de colonne de date', 'Ce tableau ne porte pas de date : toutes ses lignes sont exportées.');
                        periode = null;
                    } else if (!blocs.some(function (b) { return b.rows.length; })) {
                        prevenir('Aucune ligne', 'Aucune ligne ' + libellePeriode(periode) + '.');
                        return null;
                    }
                }
                return {
                    blocs: blocs,
                    filename: (filename || 'export') + suffixePeriode(periode),
                    title: title ? (periode ? title + ' — ' + libellePeriode(periode) : title) : title
                };
            }

            function lireTableau($table) {
                var headers = [];
                var ligneEntete = $table.querySelector('thead tr');
                if (ligneEntete) {
                    ligneEntete.querySelectorAll('th').forEach(function (th) {
                        headers.push(th.textContent.trim().replace(/\s+/g, ' '));
                    });
                }
                var rows = [];
                lignesDe($table).forEach(function (tr) {
                    var row = [];
                    tr.querySelectorAll('td').forEach(function (td) {
                        row.push(texteDe(td));
                    });
                    if (row.length) rows.push(row);
                });
                return { headers: headers, rows: rows };
            }

            /** Titre le plus proche au-dessus d'un tableau, pour nommer son bloc. */
            function titreDe($table) {
                var noeud = $table;
                while (noeud && noeud !== document.body) {
                    var precedent = noeud.previousElementSibling;
                    while (precedent) {
                        if (/^H[1-6]$/.test(precedent.tagName)) {
                            return precedent.textContent.trim().replace(/\s+/g, ' ');
                        }
                        precedent = precedent.previousElementSibling;
                    }
                    noeud = noeud.parentElement;
                }
                return null;
            }

            /**
             * Renvoie une LISTE de blocs { titre, headers, rows } : un seul quand
             * l'export porte sur un tableau, autant que nécessaire quand il porte
             * sur un conteneur.
             */
            function lireBlocs(cible) {
                if (cible.container) {
                    var $conteneur = document.getElementById(cible.container);
                    if (!$conteneur) {
                        console.error('Export : conteneur introuvable id=' + cible.container);
                        return null;
                    }
                    var blocs = [];
                    $conteneur.querySelectorAll('table').forEach(function ($t) {
                        var bloc = lireTableau($t);
                        if (!bloc.rows.length) return; // tableau vide : rien à exporter
                        bloc.titre = titreDe($t);
                        blocs.push(bloc);
                    });
                    return blocs.length ? blocs : null;
                }

                var $table = document.getElementById(cible.tableId);
                if (!$table) {
                    console.error('Export : tableau introuvable id=' + cible.tableId);
                    return null;
                }
                var seul = lireTableau($table);
                seul.titre = null;
                return [seul];
            }

            function echapper(texte) {
                return String(texte == null ? '' : texte)
                    .replace(/&/g, '&amp;').replace(/[<]/g, '&lt;').replace(/[>]/g, '&gt;');
            }

            function telecharger(contenu, type, nom) {
                var lien = document.createElement('a');
                lien.href = URL.createObjectURL(new Blob([contenu], { type: type }));
                lien.download = nom;
                document.body.appendChild(lien);
                lien.click();
                document.body.removeChild(lien);
                URL.revokeObjectURL(lien.href);
            }

            return {
                /** Bouton « Tout » : efface la période, l'export suivant emporte toutes les lignes. */
                tout: function (bouton) {
                    var zone = bouton && bouton.closest ? bouton.closest('.export-periode') : null;
                    if (!zone) return;
                    zone.querySelectorAll('.export-du, .export-au').forEach(function (i) { i.value = ''; });
                },

                toExcel: function (cible, filename, title, bouton) {
                    var prep = preparer(cible, filename, title, bouton);
                    if (!prep) return;
                    var blocs = prep.blocs;
                    filename = prep.filename;

                    var aoa = [];
                    blocs.forEach(function (bloc, i) {
                        if (i > 0) aoa.push([]);            // ligne vide entre deux blocs
                        if (bloc.titre) aoa.push([bloc.titre]);
                        aoa.push(bloc.headers);
                        bloc.rows.forEach(function (r) { aoa.push(r); });
                    });

                    var ws = XLSX.utils.aoa_to_sheet(aoa);
                    var wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Export');
                    XLSX.writeFile(wb, (filename || 'export') + '.xlsx');
                },

                toWord: function (cible, filename, title, bouton) {
                    var prep = preparer(cible, filename, title, bouton);
                    if (!prep) return;
                    var blocs = prep.blocs;
                    filename = prep.filename;
                    title = prep.title;

                    // ATTENTION — AUCUNE BALISE N'EST ÉCRITE EN TOUTES LETTRES ICI.
                    //
                    // Ce script est servi À L'INTÉRIEUR d'une page HTML. Un « < »
                    // suivi d'une barre oblique, ou une ouverture de commentaire
                    // HTML, peut faire sortir l'analyseur du bloc de script :
                    // la suite du code s'affiche alors en clair dans la page et le
                    // navigateur signale « Invalid or unexpected token ». C'est
                    // arrivé en production, alors que la même page était saine en
                    // local — un intermédiaire côté hébergement suffit à provoquer
                    // l'écart.
                    //
                    // Les balises se composent donc caractère par caractère, et le
                    // document Word ne porte plus de commentaire conditionnel.
                    var CHEVRON = String.fromCharCode(60);   // <
                    var FERME   = String.fromCharCode(62);   // >
                    var BARRE   = String.fromCharCode(47);   // /

                    function ouvre(nom, attributs) {
                        return CHEVRON + nom + (attributs ? ' ' + attributs : '') + FERME;
                    }
                    function ferme(nom) {
                        return CHEVRON + BARRE + nom + FERME;
                    }
                    function balise(nom, contenu) {
                        return ouvre(nom) + echapper(contenu) + ferme(nom);
                    }

                    var styles = 'body { font-family: Calibri, Arial, sans-serif; font-size: 10pt; }'
                        + 'h1 { font-size: 15pt; color: #1c57a3; }'
                        + 'h2 { font-size: 12pt; color: #1c57a3; margin-top: 18pt; }'
                        + 'table { border-collapse: collapse; width: 100%; margin-bottom: 14pt; }'
                        + 'th { background: #1c57a3; color: #fff; border: 1px solid #7f9dc4;'
                        + '     padding: 4pt; text-align: left; }'
                        + 'td { border: 1px solid #bbb; padding: 4pt; }';

                    var html = ouvre('html')
                        + ouvre('head')
                        + ouvre('meta', 'charset="utf-8"')
                        + ouvre('style') + styles + ferme('style')
                        + ferme('head')
                        + ouvre('body');

                    if (title) html += balise('h1', title);

                    blocs.forEach(function (bloc) {
                        if (bloc.titre) html += balise('h2', bloc.titre);

                        html += ouvre('table') + ouvre('thead') + ouvre('tr');
                        bloc.headers.forEach(function (h) { html += balise('th', h); });
                        html += ferme('tr') + ferme('thead') + ouvre('tbody');

                        bloc.rows.forEach(function (r) {
                            html += ouvre('tr');
                            r.forEach(function (c) { html += balise('td', c); });
                            html += ferme('tr');
                        });

                        html += ferme('tbody') + ferme('table');
                    });

                    html += ferme('body') + ferme('html');

                    // Marque d'encodage en tête : sans elle, Word retombe sur son
                    // réglage régional et les accents deviennent illisibles. Écrite
                    // en séquence d'échappement, jamais en caractère littéral — un
                    // caractère invisible dans du code source ne survit pas toujours
                    // à un transfert de fichier.
                    telecharger('\uFEFF' + html, 'application/msword', (filename || 'export') + '.doc');
                },

                toPdf: function (cible, filename, title, bouton) {
                    var prep = preparer(cible, filename, title, bouton);
                    if (!prep) return;
                    var blocs = prep.blocs;
                    filename = prep.filename;
                    title = prep.title;

                    var colonnes = Math.max.apply(null, blocs.map(function (b) { return b.headers.length; }));
                    var jsPDF = window.jspdf.jsPDF;
                    // UN TABLEAU LARGE RESTE LISIBLE (15/09/2026) : au-delà de douze
                    // colonnes (planning du fournisseur, un produit par colonne), la
                    // page passe en A3 paysage et la police se réduit ; sur A4 les
                    // en-têtes se coupaient lettre par lettre.
                    var doc = new jsPDF({
                        orientation: colonnes > 6 ? 'landscape' : 'portrait',
                        format: colonnes > 12 ? 'a3' : 'a4'
                    });
                    var taille = colonnes > 16 ? 6.5 : (colonnes > 8 ? 7.5 : 8);
                    var y = 14;

                    if (title) {
                        doc.setFontSize(14);
                        doc.text(title, 14, 15);
                        y = 22;
                    }

                    blocs.forEach(function (bloc) {
                        if (bloc.titre) {
                            doc.setFontSize(11);
                            doc.text(bloc.titre, 14, y + 4);
                            y += 8;
                        }
                        doc.autoTable({
                            head: [bloc.headers],
                            body: bloc.rows,
                            startY: y,
                            styles: { fontSize: taille, cellPadding: 1.5, overflow: 'linebreak' },
                            headStyles: { fillColor: [28, 87, 163], valign: 'middle' },
                            // La première colonne (date, nom…) garde sa largeur : elle ne se coupe pas.
                            columnStyles: { 0: { cellWidth: 'wrap' } },
                        });
                        y = doc.lastAutoTable.finalY + 8;
                    });

                    doc.save((filename || 'export') + '.pdf');
                },
            };
        })();
    </script>
@endonce

@php
    // La cible est passée telle quelle au JavaScript : un identifiant de
    // conteneur l'emporte sur un identifiant de tableau.
    $cible = json_encode($container ? ['container' => $container] : ['tableId' => $tableId]);
    $intitule = $title ?? $filename;
@endphp

@php
    // Les dates « du / au » n'ont de sens que pour un export fait dans le
    // navigateur : un export produit par le serveur (URL) les ignore.
    $avecPeriode = !($excelUrl && $wordUrl && $pdfUrl);
@endphp
<div class="d-inline-flex align-items-center flex-wrap mb-2 export-periode" style="gap:4px;">
    @if ($avecPeriode)
        <span class="small text-muted">Période :</span>
        <input type="date" class="form-control form-control-sm export-du" style="width:auto; display:inline-block;" title="Date de début (vide : depuis le début)" aria-label="Date de début de la période à exporter">
        <span class="small text-muted">au</span>
        <input type="date" class="form-control form-control-sm export-au" style="width:auto; display:inline-block;" title="Date de fin (vide : jusqu'à la fin)" aria-label="Date de fin de la période à exporter">
    @endif
    @if ($excelUrl)
        <a href="{{ $excelUrl }}" class="btn btn-sm btn-success">
            <i class="material-icons md-cloud_download align-middle"></i> Excel
        </a>
    @else
        <button type="button" class="btn btn-sm btn-success"
            onclick="GravierExport.toExcel({{ $cible }}, @js($filename), @js($intitule), this)">
            <i class="material-icons md-cloud_download align-middle"></i> Excel
        </button>
    @endif

    @if ($wordUrl)
        <a href="{{ $wordUrl }}" class="btn btn-sm btn-primary">
            <i class="material-icons md-description align-middle"></i> Word
        </a>
    @else
        <button type="button" class="btn btn-sm btn-primary"
            onclick="GravierExport.toWord({{ $cible }}, @js($filename), @js($intitule), this)">
            <i class="material-icons md-description align-middle"></i> Word
        </button>
    @endif

    @if ($pdfUrl)
        <a href="{{ $pdfUrl }}" class="btn btn-sm btn-danger">
            <i class="material-icons md-picture_as_pdf align-middle"></i> PDF
        </a>
    @else
        <button type="button" class="btn btn-sm btn-danger"
            onclick="GravierExport.toPdf({{ $cible }}, @js($filename), @js($intitule), this)">
            <i class="material-icons md-picture_as_pdf align-middle"></i> PDF
        </button>
    @endif
    @if ($avecPeriode)
        <button type="button" class="btn btn-sm btn-outline-secondary" title="Effacer la période : les boutons exportent alors toutes les lignes"
            onclick="GravierExport.tout(this)">Tout</button>
    @endif
</div>
