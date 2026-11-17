@php($user = auth()->user())

<header class="hb-navbar">
    <button type="button" id="hbSidebarToggle" class="hb-icon-btn"
            aria-label="Tampilkan atau sembunyikan menu" aria-controls="hbSidebar" aria-expanded="true">
        <i class="bi bi-list fs-4"></i>
    </button>

    {{-- Logo di navbar (sidebar tersembunyi pada layar kecil) --}}
    <a href="{{ route('dashboard') }}" class="hb-brand hb-brand-dark d-lg-none">
        <img src="{{ asset('assets/images/logo.svg') }}" alt="" width="32" height="32">
        <span class="d-none d-sm-inline">Hello BroSeven!</span>
    </a>

    <nav aria-label="breadcrumb" class="d-none d-lg-block">
        <ol class="breadcrumb hb-breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('dashboard') }}" aria-label="Beranda dashboard"><i class="bi bi-house-door"></i></a>
            </li>
            @hasSection('breadcrumb')
                @yield('breadcrumb')
            @else
                <li class="breadcrumb-item active" aria-current="page">@yield('page_title', 'Dashboard')</li>
            @endif
        </ol>
    </nav>

    <div class="ms-auto d-flex align-items-center gap-2">
        {{-- Notifikasi --}}
        <div class="dropdown">
            <button type="button" class="hb-icon-btn position-relative" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifikasi">
                <i class="bi bi-bell"></i>
                @if ($unreadCount > 0)
                    <span class="hb-notif-dot">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end hb-dropdown hb-notif-menu">
                <div class="hb-dropdown-head d-flex justify-content-between align-items-center gap-2">
                    <strong>Notifikasi</strong>
                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="hb-link-btn">Tandai semua dibaca</button>
                        </form>
                    @else
                        <span class="small text-muted">Semua sudah dibaca</span>
                    @endif
                </div>
                <div class="hb-notif-list">
                    @forelse ($recentNotifications as $n)
                        <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                            @csrf
                            <button type="submit" class="hb-notif-item {{ $n->read_at ? '' : 'is-unread' }}">
                                <span class="d-block fw-semibold">{{ $n->title }}</span>
                                @if ($n->message)
                                    <span class="d-block small text-muted">{{ \Illuminate\Support\Str::limit($n->message, 90) }}</span>
                                @endif
                                <span class="d-block small text-muted">{{ $n->created_at->diffForHumans() }}</span>
                            </button>
                        </form>
                    @empty
                        <div class="hb-empty">
                            <i class="bi bi-bell-slash"></i>
                            <span>Belum ada notifikasi</span>
                        </div>
                    @endforelse
                </div>
                <a href="{{ route('notifications.index') }}" class="hb-notif-foot">Lihat semua notifikasi</a>
            </div>
        </div>

        {{-- Profil --}}
        <div class="dropdown">
            <button type="button" class="hb-profile-btn" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="hb-avatar">{{ $user->initial() }}</span>
                <span class="hb-profile-meta d-none d-md-flex">
                    <strong>{{ $user->name }}</strong>
                    <small>{{ $user->roleLabel() }}</small>
                </span>
                <i class="bi bi-chevron-down d-none d-md-inline small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end hb-dropdown">
                <li class="hb-dropdown-head">
                    <div class="fw-semibold">{{ $user->name }}</div>
                    <div class="small text-muted">{{ $user->email }}</div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('profile.show') }}"><i class="bi bi-gear me-2"></i>Pengaturan Akun</a></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
