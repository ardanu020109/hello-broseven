@php
    $tabs = [
        ['Jadwal Kerja', 'bi-calendar-week', 'schedules.index', ['schedules.index', 'schedules.create', 'schedules.edit']],
        ['Penugasan', 'bi-person-lines-fill', 'schedules.assignments.index', ['schedules.assignments.*']],
        ['Kalender', 'bi-calendar3', 'schedules.calendar', ['schedules.calendar']],
        ['Hari Libur', 'bi-calendar-heart', 'schedules.holidays.index', ['schedules.holidays.*']],
    ];
@endphp
<ul class="hb-tabs" role="navigation" aria-label="Menu jadwal">
    @foreach ($tabs as [$label, $icon, $route, $active])
        <li>
            <a href="{{ route($route) }}" class="hb-tab {{ request()->routeIs(...$active) ? 'is-active' : '' }}"
               @if (request()->routeIs(...$active)) aria-current="page" @endif>
                <i class="bi {{ $icon }}"></i> {{ $label }}
            </a>
        </li>
    @endforeach
</ul>
