@props(['status'])

@php
    $map = [
        'present' => ['Hadir', 'present'],
        'late' => ['Terlambat', 'late'],
        'absent' => ['Tidak Hadir', 'absent'],
        'leave' => ['Izin', 'leave'],
        'sick' => ['Sakit', 'leave'],
        'pending' => ['Menunggu', 'pending'],
        'approved' => ['Disetujui', 'present'],
        'rejected' => ['Ditolak', 'absent'],
        'draft' => ['Draft', 'pending'],
        'finalized' => ['Final', 'leave'],
        'paid' => ['Dibayar', 'present'],
    ];
    [$label, $class] = $map[$status] ?? [ucfirst((string) $status), 'leave'];
@endphp

<span class="hb-st hb-st-{{ $class }}">{{ $label }}</span>
