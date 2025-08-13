<?php

namespace App\Console\Commands;

use App\Enums\SessionCategoryType;
use App\Models\OrganisationProgrammeSeat;
use App\Models\SessionAttendanceSnapshot;
use App\Models\User;
use App\Services\ProgrammeSessionAttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateSessionAttendanceSnapshots extends Command
{
    protected $signature = 'update:session_attendance_snapshots';

    protected $description = 'Update the Session Attendance Snapshots table';

    public function handle(): int
    {
        Log::info('Updating Session Attendance Snapshots table...');
        $this->info('Updating Session Attendance Snapshots table...');

        try {
            DB::table('session_attendance_snapshots')->truncate();

            User::whereHas('programmeSeats', function ($query) {
                $query->where('contract_start_date', '<=', now());
            })
                ->with('programmeSeats')
                ->orderBy('id')
                ->chunk(100, function ($users) {
                    foreach ($users as $user) {
                        $this->processUser($user);
                    }
                });

            $this->info('Session Attendance Snapshots table updated successfully.');
            Log::info('Session Attendance Snapshots table updated successfully.');

            return 0;
        } catch (\Exception $e) {
            $this->error('An error occurred while updating the Session Attendance Snapshots table.');
            $this->error($e->getMessage());
            report($e);
            Log::critical('An error occurred while updating the Session Attendance Snapshots table.', ['error' => $e->getMessage()]);

            return 1;
        }
    }

    /**
     * Process a user and their recent programme seats
     */
    private function processUser(User $user)
    {
        $this->info('Processing user: '.$user->id);

        $seats = $user->programmeSeats()
            ->where('contract_start_date', '<=', now())
            ->orderByDesc('contract_start_date')
            ->take(2)
            ->get();

        foreach ($seats as $seat) {
            $this->processSeat($seat);
        }
    }

    /**
     * Process a single programme seat and calculate attendance snapshot
     */
    private function processSeat(OrganisationProgrammeSeat $seat)
    {
        $types = [SessionCategoryType::LEARNING, SessionCategoryType::MENTORING];
        foreach ($types as $type) {
            $service = new ProgrammeSessionAttendanceService($seat);
            $stats = $service->getAttendanceStats($type, true);

            SessionAttendanceSnapshot::create([
                'type' => $type->value,
                'user_id' => $seat->user_id,
                'organisation_id' => $seat->organisation_id,
                'programme_id' => $seat->programme_id,
                'organisation_programme_seat_id' => $seat->id,
                'contract_start_date' => $seat->contract_start_date,
                'ignition_date' => $seat->ignition_date,
                'attendance_percentage' => $stats['percentage'],
                'sessions_attended' => $stats['attended'],
                'sessions_missed' => $stats['missed'],
                'sessions_not_marked' => $stats['notMarked'],
                'sessions_total' => $stats['total'],
                'meta' => json_encode($stats['meta'] ?? []),
            ]);
        }
    }
}
