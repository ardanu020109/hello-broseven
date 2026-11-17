<aside class="hb-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="hbSidebar" aria-label="Menu utama">
    <div class="hb-sidebar-inner">
        <div class="hb-sidebar-brand">
            <a href="{{ route('dashboard') }}" class="hb-brand">
                <img src="{{ asset('assets/images/logo.svg') }}" alt="" width="36" height="36">
                <span class="hb-brand-text">Hello BroSeven!</span>
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none"
                    data-bs-dismiss="offcanvas" data-bs-target="#hbSidebar" aria-label="Tutup menu"></button>
        </div>

        <nav class="hb-nav" aria-label="Navigasi">
            <ul class="hb-nav-list">
                @foreach ($menu as $i => $item)
                    <li class="hb-nav-item">
                        @if (!empty($item['children']))
                            <button type="button"
                                    class="hb-nav-link hb-nav-toggle {{ $item['open'] ? '' : 'collapsed' }} {{ $item['open'] ? 'has-active' : '' }}"
                                    data-bs-toggle="collapse" data-bs-target="#hbSub{{ $i }}"
                                    aria-expanded="{{ $item['open'] ? 'true' : 'false' }}" aria-controls="hbSub{{ $i }}"
                                    title="{{ $item['label'] }}">
                                <i class="bi {{ $item['icon'] }} hb-nav-icon"></i>
                                <span class="hb-nav-label">{{ $item['label'] }}</span>
                                <i class="bi bi-chevron-down hb-nav-chevron"></i>
                            </button>
                            <div class="collapse hb-subnav-wrap {{ $item['open'] ? 'show' : '' }}" id="hbSub{{ $i }}">
                                <ul class="hb-subnav">
                                    @foreach ($item['children'] as $child)
                                        <li>
                                            <a href="{{ $child['url'] }}"
                                               class="hb-nav-link {{ $child['active'] ? 'is-active' : '' }} {{ $child['soon'] ? 'is-soon' : '' }}"
                                               @if ($child['soon']) data-soon data-label="{{ $child['label'] }}" @endif
                                               @if ($child['active']) aria-current="page" @endif>
                                                <i class="bi {{ $child['icon'] }} hb-nav-icon"></i>
                                                <span class="hb-nav-label">{{ $child['label'] }}</span>
                                                @if ($child['soon']) <span class="hb-soon-tag">Segera</span> @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <a href="{{ $item['url'] }}"
                               class="hb-nav-link {{ $item['active'] ? 'is-active' : '' }} {{ $item['soon'] ? 'is-soon' : '' }}"
                               title="{{ $item['label'] }}"
                               @if ($item['soon']) data-soon data-label="{{ $item['label'] }}" @endif
                               @if ($item['active']) aria-current="page" @endif>
                                <i class="bi {{ $item['icon'] }} hb-nav-icon"></i>
                                <span class="hb-nav-label">{{ $item['label'] }}</span>
                                @if ($item['soon']) <span class="hb-soon-tag">Segera</span> @endif
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</aside>
