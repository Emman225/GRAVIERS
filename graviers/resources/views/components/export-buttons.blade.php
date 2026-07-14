@props(['tableId' => 'liste', 'filename' => 'export', 'title' => null])
{{--
    Boutons d'export Excel et PDF d'un tableau HTML.
    Usage :
        <x-export-buttons table-id="maTable" filename="liste-clients" title="Liste des clients" />

    - tableId : id du <table> à exporter
    - filename : nom de fichier (sans extension)
    - title : titre affiché en tête du PDF (défaut : filename)

    Charge SheetJS (XLSX) + jsPDF (+ autotable) en CDN à la première utilisation.
--}}
@once
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script>
        window.GravierExport = window.GravierExport || (function () {
            function getTableData(tableId) {
                var $table = document.getElementById(tableId);
                if (!$table) {
                    console.error('Export: tableau introuvable id=' + tableId);
                    return null;
                }
                var headers = [];
                var headerRow = $table.querySelector('thead tr');
                if (headerRow) {
                    headerRow.querySelectorAll('th').forEach(function (th) {
                        headers.push(th.textContent.trim());
                    });
                }
                var rows = [];
                $table.querySelectorAll('tbody tr').forEach(function (tr) {
                    var row = [];
                    tr.querySelectorAll('td').forEach(function (td) {
                        // On ignore les colonnes qui contiennent uniquement des boutons d'action
                        var hasOnlyButtons = td.children.length > 0 &&
                            Array.prototype.every.call(td.children, function (c) {
                                return c.tagName === 'A' || c.tagName === 'BUTTON' || c.tagName === 'FORM';
                            }) && td.textContent.trim().replace(/\s+/g, ' ').length < 30;
                        row.push(hasOnlyButtons ? '' : td.textContent.trim().replace(/\s+/g, ' '));
                    });
                    if (row.length) rows.push(row);
                });
                return { headers: headers, rows: rows };
            }

            return {
                toExcel: function (tableId, filename) {
                    var data = getTableData(tableId);
                    if (!data) return;
                    var aoa = [data.headers].concat(data.rows);
                    var ws = XLSX.utils.aoa_to_sheet(aoa);
                    var wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Export');
                    XLSX.writeFile(wb, (filename || 'export') + '.xlsx');
                },
                toPdf: function (tableId, filename, title) {
                    var data = getTableData(tableId);
                    if (!data) return;
                    var jsPDF = window.jspdf.jsPDF;
                    var doc = new jsPDF({ orientation: data.headers.length > 6 ? 'landscape' : 'portrait' });
                    if (title) {
                        doc.setFontSize(14);
                        doc.text(title, 14, 15);
                    }
                    doc.autoTable({
                        head: [data.headers],
                        body: data.rows,
                        startY: title ? 22 : 14,
                        styles: { fontSize: 8, cellPadding: 2 },
                        headStyles: { fillColor: [28, 87, 163] },
                    });
                    doc.save((filename || 'export') + '.pdf');
                },
            };
        })();
    </script>
@endonce

<div class="d-inline-block mb-2">
    <button type="button" class="btn btn-sm btn-success"
        onclick="GravierExport.toExcel('{{ $tableId }}', '{{ $filename }}')">
        <i class="material-icons md-cloud_download align-middle"></i> Excel
    </button>
    <button type="button" class="btn btn-sm btn-danger"
        onclick="GravierExport.toPdf('{{ $tableId }}', '{{ $filename }}', '{{ $title ?? $filename }}')">
        <i class="material-icons md-picture_as_pdf align-middle"></i> PDF
    </button>
</div>
