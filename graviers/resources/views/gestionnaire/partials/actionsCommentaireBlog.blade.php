{{--
    Colonne « Action » d'un commentaire de blog.

    Partagée par l'écran d'un article (commentaireBlog) et par la modération
    générale (moderationCommentaireBlog) : les deux doivent proposer exactement
    les mêmes commandes, sans les dupliquer.

    Toutes les actions renvoient sur back() côté contrôleur : on revient donc
    sur l'écran d'où l'on a cliqué.
--}}
<td class="text-center text-nowrap">
    @if (!$commentaire->trashed())
        @if ($commentaire->statut != \App\Models\blog_commentaire::PUBLIE)
            <a href="{{ route('show.publierCommentaireBlog', $commentaire->id) }}"
               class="btn btn-sm btn-success" title="Publier sur le site">
                <i class="material-icons md-public"></i>
            </a>
        @endif

        @if ($commentaire->statut != \App\Models\blog_commentaire::REFUSE)
            <a href="{{ route('show.annulerCommentaireBlog', $commentaire->id) }}"
               class="btn btn-sm btn-warning" title="Refuser (retirer du site)">
                <i class="material-icons md-visibility_off"></i>
            </a>
        @endif

        <a href="{{ route('show.supprimerCommentaireBlog', $commentaire->id) }}"
           class="btn btn-sm btn-danger" title="Mettre à la corbeille">
            <i class="material-icons md-delete"></i>
        </a>
    @else
        <a href="{{ route('show.supprimerCommentaireBlog', $commentaire->id) }}"
           class="btn btn-sm btn-success" title="Restaurer">
            <i class="material-icons md-restore_from_trash"></i>
        </a>

        {{-- js-delete-form : la confirmation passe par SweetAlert2
             (public/backend/assets/js/delete-confirm.js, motif D), comme sur le
             reste de l'administration. L'ancien onsubmit="return confirm(...)"
             ouvrait la fenêtre grise du navigateur, que ce script n'intercepte
             pas : il ne reprend que les onclick.
             Ce fragment étant partagé par l'écran d'un article et par la
             modération générale, les deux en bénéficient. --}}
        <form action="{{ route('show.suppressionDefinitiveCommentaireBlog', $commentaire->id) }}" method="POST" class="d-inline js-delete-form"
              data-item-name="le commentaire de {{ $commentaire->client->display_name ?: 'ce client' }}"
              data-confirm-text="Le commentaire sera supprimé définitivement. Cette action est irréversible.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-dark" title="Supprimer définitivement">
                <i class="material-icons md-delete_forever"></i>
            </button>
        </form>
    @endif
</td>
