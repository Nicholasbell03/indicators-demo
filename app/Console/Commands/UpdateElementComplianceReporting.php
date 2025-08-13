<?php

namespace App\Console\Commands;

use App\Models\LicenceGroup;
use App\Models\Organisation;
use App\Models\OrganisationProgrammeSeat;
use App\Models\User;
use App\Services\ProgrammeElementProgressCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateElementComplianceReporting extends Command
{
    protected $signature = 'update:element_compliance_reporting';

    protected $description = 'Update the Element Compliance Reporting table';

    public function handle(): int
    {
        Log::info('Updating Element Compliance Reporting table...');
        $this->info('Updating Element Compliance Reporting table...');

        try {
            DB::table('element_compliance_reporting')->truncate();

            User::whereHas('organisations.licenceGroups')
                ->with([
                    'organisations.licenceGroups',
                    'programmeSeats.programme',
                    'departments',
                ])
                ->orderBy('id')
                ->chunk(50, function ($users) {
                    foreach ($users as $user) {
                        $this->processUser($user);
                    }
                });

            $this->info('Element Compliance Reporting table updated successfully.');
            Log::info('Element Compliance Reporting table updated successfully.');

            return 0;
        } catch (\Exception $e) {
            $this->error('An error occurred while updating the Element Compliance Reporting table.');
            $this->error($e->getMessage());
            Log::error('An error occurred while updating the Element Compliance Reporting table.', ['error' => $e->getMessage()]);

            return 1;
        }
    }

    private function processUser(User $user): void
    {
        $this->info('Processing user: '.$user->id);
        $programmeNumbers = $this->getProgrammeNumbers($user);

        // Iterate through each organisation the user belongs to
        foreach ($user->organisations as $organisation) {
            // Iterate through each licence group in that organisation
            foreach ($organisation->licenceGroups as $licenceGroup) {

                // The context for calculation
                $programme = $licenceGroup->programme; // This can be null

                // Find a matching programme seat, if one exists. This can also be null.
                $programmeSeat = $programme ? $user->programmeSeats->where('programme_id', $programme->id)->first() : null;

                // Instantiate the newly flexible service
                $progressService = new ProgrammeElementProgressCalculationService($user, $organisation, $programme);

                // Get the progress data
                $detailedProgress = $progressService->getDetailedProgress();

                if ($detailedProgress->isEmpty()) {
                    continue;
                }

                $programmeNumber = $programme ? ($programmeNumbers[$programme->id] ?? null) : null;

                // Pass all context (including the nullable seat) to the insertion method
                $this->insertProgressData($user, $programmeSeat, $programmeNumber, $detailedProgress, $organisation, $licenceGroup);
            }
        }
    }

    private function getProgrammeNumbers(User $user): array
    {
        $userProgrammes = $user->programmeSeats()
            ->whereNotNull('ignition_date')
            ->where('ignition_date', '<=', now())
            ->orderByDesc('ignition_date')
            ->get()
            ->keyBy('programme_id');

        $programmeNumbers = [];
        $number = 1;
        foreach ($userProgrammes as $programmeId => $seat) {
            $programmeNumbers[$programmeId] = $number++;
        }

        return $programmeNumbers;
    }

    private function insertProgressData(User $user, ?OrganisationProgrammeSeat $programmeSeat, ?int $programmeNumber, Collection $detailedProgress, Organisation $organisation, LicenceGroup $licenceGroup): void
    {
        $records = $detailedProgress->map(function ($progress) use ($user, $programmeSeat, $programmeNumber, $organisation, $licenceGroup) {
            $totalFields = $progress['total_fields'];
            $completedFields = $progress['completed_fields'];

            // Now we use the context passed into this method
            return [
                'tenant_id' => $progress['tenant_id'],
                'user_id' => $user->id,
                'rems_id' => $user->foreign_uid,
                'entrepreneur' => $user->name,
                'company_name' => $organisation->name, // Use the org from context
                'email' => $user->email,
                'role' => 'user',
                'licence' => $licenceGroup->name, // Use the licence from context
                'programme' => $programmeSeat?->programme->title ?? null,
                'organisation_programme_seat_id' => $programmeSeat?->id ?? null,
                'contract_start_date' => $programmeSeat?->contract_start_date ?? null,
                'ignition_date' => $programmeSeat?->ignition_date ?? null,
                'programme_seat_active' => $programmeSeat?->is_active ?? null,
                'programme_number' => $programmeNumber,
                'catalogue_module' => $progress['catalogue_module_name'],
                'catalogue' => $progress['catalogue_name'],
                'progress_type' => $progress['catalogue_progress_type'],
                'element' => $progress['element_name'],
                'total_number_of_fields' => $totalFields,
                'number_of_fields_completed' => $completedFields,
                'number_of_fields_in_progress' => 0,
                'number_of_fields_not_started' => $totalFields - $completedFields,
                'last_updated' => $progress['progress_last_updated'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();

        if (! empty($records)) {
            DB::table('element_compliance_reporting')->insert($records);
        }
    }
}
