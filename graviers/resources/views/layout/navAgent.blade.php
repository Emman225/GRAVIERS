{{-- Menu Agent SAV (type_user_id = 7) --}}
<li class="menu-item {{ request()->routeIs('show.mesTicketsSAV', 'show.traiterTicketSAVPage') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('show.mesTicketsSAV') }}">
        <i class="icon material-icons md-support_agent"></i>
        <span class="text">Mes tickets SAV</span>
    </a>
</li>
