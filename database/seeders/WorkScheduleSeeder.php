<?php

namespace Database\Seeders;

use App\Models\ScheduleDay;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class WorkScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $schedule = WorkSchedule::updateOrCreate(
            ['name' => 'Jadwal Reguler Senin-Jumat'],
            [
                'check_in_time' => '08:00:00',
                'check_out_time' => '17:00:00',
                'late_tolerance_minutes' => 15,
                'break_start' => '12:00:00',
                'break_end' => '13:00:00',
                'is_shift' => false,
                'timezone' => 'Asia/Makassar',
                'is_active' => true,
            ]
        );

        foreach (range(1, 7) as $isoDay) { // 1 = Senin ... 7 = Minggu
            ScheduleDay::updateOrCreate(
                ['work_schedule_id' => $schedule->id, 'day_of_week' => $isoDay],
                ['is_working_day' => $isoDay <= 5, 'work_mode' => 'WFO']
            );
        }
    }
}
