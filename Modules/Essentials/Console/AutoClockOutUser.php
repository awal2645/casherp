<?php

namespace Modules\Essentials\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Essentials\Entities\EssentialsAttendance;

class AutoClockOutUser extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pos:autoClockOutUser';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto clock out user for a given time';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $updated = 0;
        EssentialsAttendance::join('essentials_shifts as es', 'essentials_attendances.essentials_shift_id', 'es.id')
            ->join('business as b', 'b.id', '=', 'essentials_attendances.business_id')
            ->whereColumn('es.business_id', 'essentials_attendances.business_id')
            ->where('es.is_allowed_auto_clockout', 1)
            ->whereNotNull('es.auto_clockout_time')
            ->whereNull('essentials_attendances.clock_out_time')
            ->select([
                'essentials_attendances.id',
                'essentials_attendances.business_id',
                'essentials_attendances.clock_in_time',
                'es.start_time',
                'es.auto_clockout_time',
                'b.time_zone',
            ])
            ->orderBy('essentials_attendances.id')
            ->chunkById(250, function ($attendances) use (&$updated) {
                foreach ($attendances as $attendance) {
                    $timezone = in_array($attendance->time_zone, \DateTimeZone::listIdentifiers(), true)
                        ? $attendance->time_zone
                        : config('app.timezone');
                    $clock_in = Carbon::parse($attendance->clock_in_time, $timezone);
                    $auto_clock_out = Carbon::parse(
                        $clock_in->format('Y-m-d').' '.$attendance->auto_clockout_time,
                        $timezone
                    );

                    $start_time = ! empty($attendance->start_time)
                        ? Carbon::parse($clock_in->format('Y-m-d').' '.$attendance->start_time, $timezone)
                        : null;
                    if ((! empty($start_time) && $auto_clock_out->lessThanOrEqualTo($start_time))
                        || (empty($start_time) && $auto_clock_out->lessThanOrEqualTo($clock_in))) {
                        $auto_clock_out->addDay();
                    }

                    if (Carbon::now($timezone)->greaterThanOrEqualTo($auto_clock_out)) {
                        $updated += EssentialsAttendance::where('id', $attendance->id)
                            ->where('business_id', $attendance->business_id)
                            ->whereNull('clock_out_time')
                            ->update(['clock_out_time' => $auto_clock_out->format('Y-m-d H:i:s')]);
                    }
                }
            }, 'essentials_attendances.id', 'id');

        $this->info("Auto clocked out {$updated} attendance record(s).");

        return self::SUCCESS;
    }
}
