@php
    $dow = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    $todayKey = today()->toDateString();
    $linkDays = isset($dayQuery) && is_array($dayQuery);
@endphp

<div class="hb-cal-wrap">
    <div class="hb-cal" role="grid" aria-label="Kalender jadwal">
        @foreach ($dow as $label)
            <div class="hb-cal-dow" role="columnheader">{{ $label }}</div>
        @endforeach

        @foreach ($cal['days'] as $key => $day)
            @php
                $inMonth = $day['date']->format('Y-m') === $date->format('Y-m');
                $entry = $single ? ($cal['matrix'][$singleId][$key] ?? null) : null;
                $tag = $linkDays ? 'a' : 'div';
                $classes = 'hb-cal-cell'.($inMonth ? '' : ' is-out').($key === $todayKey ? ' is-today' : '').($day['holiday'] ? ' is-holiday' : '');
            @endphp

            <{{ $tag }} class="{{ $classes }}" role="gridcell"
                @if ($linkDays) href="{{ route('schedules.calendar', array_merge($dayQuery, ['view' => 'day', 'date' => $key])) }}" @endif>
                <span class="hb-cal-num">{{ $day['date']->day }}</span>

                @if ($day['holiday'])
                    <span class="hb-cal-holiday" title="{{ $day['holiday'] }}">{{ $day['holiday'] }}</span>
                @endif

                @if ($single)
                    @if ($entry && $entry['state'] === 'working')
                        <span class="hb-cal-time">{{ $entry['time'] }}</span>
                        <span class="hb-cal-sub">{{ $entry['schedule'] }}</span>
                        <span class="hb-mode hb-mode-{{ strtolower($entry['mode']) }}">{{ $entry['mode'] }}</span>
                    @elseif ($entry && $entry['state'] === 'off')
                        <span class="hb-cal-muted">Libur</span>
                    @elseif ($entry && $entry['state'] === 'none')
                        <span class="hb-cal-muted">Belum ada jadwal</span>
                    @endif
                @else
                    @if ($day['working_count'] > 0)
                        <span class="hb-cal-count">{{ $day['working_count'] }} bekerja</span>
                        @foreach (array_slice($day['groups'], 0, 2, true) as $g)
                            <span class="hb-cal-sub" title="{{ $g['name'] }} ({{ $g['time'] }})">{{ $g['name'] }}: {{ $g['count'] }}</span>
                        @endforeach
                        @if (count($day['groups']) > 2)
                            <span class="hb-cal-sub">+{{ count($day['groups']) - 2 }} jadwal lain</span>
                        @endif
                    @elseif (!$day['holiday'] && $day['off_count'] > 0)
                        <span class="hb-cal-muted">Libur</span>
                    @endif
                    @if ($day['unscheduled'] > 0 && !$day['holiday'])
                        <span class="hb-cal-muted">{{ $day['unscheduled'] }} tanpa jadwal</span>
                    @endif
                @endif
            </{{ $tag }}>
        @endforeach
    </div>
</div>
