@props([
    'tableId' => 'liste',
    'container' => null,
    'filename' => 'export',
    'title' => null,
    'pdfUrl' => null,
    'wordUrl' => null,
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
                var queDesBoutons = cellule.children.length > 0 &&
                    Array.prototype.every.call(cellule.children, function (c) {
                        return c.tagName === 'A' || c.tagName === 'BUTTON' || c.tagName === 'FORM';
                    }) && cellule.textContent.trim().replace(/\s+/g, ' ').length < 30;
                return queDesBoutons ? '' : cellule.textContent.trim().replace(/\s+/g, ' ');
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
                $table.querySelectorAll('tbody tr').forEach(function (tr) {
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
                toExcel: function (cible, filename) {
                    var blocs = lireBlocs(cible);
                    if (!blocs) return;

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

                toWord: function (cible, filename, title) {
                    var blocs = lireBlocs(cible);
                    if (!blocs) return;

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

                toPdf: function (cible, filename, title) {
                    var blocs = lireBlocs(cible);
                    if (!blocs) return;

                    var colonnes = Math.max.apply(null, blocs.map(function (b) { return b.headers.length; }));
                    var jsPDF = window.jspdf.jsPDF;
                    var doc = new jsPDF({ orientation: colonnes > 6 ? 'landscape' : 'portrait' });
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
                            styles: { fontSize: 8, cellPadding: 2 },
                            headStyles: { fillColor: [28, 87, 163] },
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

<div class="d-inline-block mb-2">
    <button type="button" class="btn btn-sm btn-success"
        onclick="GravierExport.toExcel({{ $cible }}, @js($filename))">
        <i class="material-icons md-cloud_download align-middle"></i> Excel
    </button>

    @if ($wordUrl)
        <a href="{{ $wordUrl }}" class="btn btn-sm btn-primary">
            <i class="material-icons md-description align-middle"></i> Word
        </a>
    @else
        <button type="button" class="btn btn-sm btn-primary"
            onclick="GravierExport.toWord({{ $cible }}, @js($filename), @js($intitule))">
            <i class="material-icons md-description align-middle"></i> Word
        </button>
    @endif

    @if ($pdfUrl)
        <a href="{{ $pdfUrl }}" class="btn btn-sm btn-danger">
            <i class="material-icons md-picture_as_pdf align-middle"></i> PDF
        </a>
    @else
        <button type="button" class="btn btn-sm btn-danger"
            onclick="GravierExport.toPdf({{ $cible }}, @js($filename), @js($intitule))">
            <i class="material-icons md-picture_as_pdf align-middle"></i> PDF
        </button>
    @endif
</div>
