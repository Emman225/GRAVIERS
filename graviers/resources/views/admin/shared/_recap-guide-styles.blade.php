<style>
/* ============================================================
   Bouton « Guide d'utilisation » dans le header du dashboard
   ============================================================ */
.dash-guide-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.92);
    color: #1c57a3;
    font-weight: 600;
    font-size: 0.85rem;
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 4px 12px rgba(10, 37, 64, 0.15);
    transition: all 0.2s ease;
    cursor: pointer;
}
.dash-guide-btn:hover {
    background: #ffffff;
    color: #0a2540;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(10, 37, 64, 0.22);
}
.dash-guide-btn .material-icons { font-size: 18px; }

/* ============================================================
   Contenu de la modale guide
   ============================================================ */
.recap-guide { color: #1f2937; }
.recap-guide-section {
    padding: 14px 16px;
    margin-bottom: 12px;
    background: #f9fafb;
    border-left: 4px solid #1c57a3;
    border-radius: 0 10px 10px 0;
}
.recap-guide-section.recap-guide-contact {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-left-color: #3b82f6;
}
.recap-guide-title {
    font-weight: 700;
    color: #0a2540;
    font-size: 0.98rem;
    margin: 0 0 8px;
    letter-spacing: -0.01em;
}
.recap-guide-list,
.recap-guide-ol {
    margin: 0;
    padding-left: 22px;
}
.recap-guide-list li,
.recap-guide-ol li {
    margin-bottom: 6px;
    line-height: 1.5;
}
.recap-guide-link {
    color: #1c57a3;
    font-weight: 600;
    text-decoration: none;
    margin-left: 4px;
    font-size: 0.9rem;
}
.recap-guide-link:hover { color: #0a2540; text-decoration: underline; }

/* Code couleur */
.recap-guide-colors {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.recap-guide-color-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.92rem;
}
.recap-guide-color-item .dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    flex-shrink: 0;
    border: 1px solid rgba(0, 0, 0, 0.08);
}
.dot-blue   { background: #3b82f6; }
.dot-green  { background: #10b981; }
.dot-amber  { background: #f59e0b; }
.dot-red    { background: #ef4444; }
.dot-yellow { background: #fde047; }

/* Actions recommandées */
.recap-guide-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.recap-guide-action {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 14px;
    align-items: start;
    padding: 10px 12px;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}
.recap-guide-action p { margin: 0; font-size: 0.92rem; line-height: 1.5; }
.recap-guide-action-tag {
    text-align: center;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 6px 10px;
    border-radius: 999px;
    align-self: center;
}
.recap-guide-action-daily  .recap-guide-action-tag { background: #fee2e2; color: #991b1b; }
.recap-guide-action-weekly .recap-guide-action-tag { background: #fef3c7; color: #92400e; }
.recap-guide-action-monthly .recap-guide-action-tag { background: #d1fae5; color: #065f46; }

@media (max-width: 575px) {
    .recap-guide-action { grid-template-columns: 1fr; }
}
</style>
