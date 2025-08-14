<?php

declare(strict_types=1);

use App\Enums\SessionCategoryType;
use App\Models\EngaugeSession;
use App\Models\EngaugeSessionProgress;
use App\Models\EngaugeUser;
use App\Models\OrganisationProgrammeSeat;
use App\Models\Pivots\EngaugeSessionUser;
use App\Models\SessionCategory;
use App\Models\User;
use App\Services\ProgrammeSessionAttendanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Create EngaugeUser first
    $this->engaugeUser = EngaugeUser::factory()->create();

    // Set up common test data
    $this->user = User::factory()->create([
        'engauge_user_id' => $this->engaugeUser->id,
    ]);

    $this->seat = OrganisationProgrammeSeat::factory()
        ->user($this->user)
        ->ignitionDate(Carbon::now()->subWeeks(4))
        ->contractStartDate(Carbon::now()->subWeeks(4))
        ->create();

    $this->service = new ProgrammeSessionAttendanceService($this->seat);

    // Create session categories
    $this->learningCategory = SessionCategory::factory()->create([
        'type' => SessionCategoryType::LEARNING,
    ]);

    $this->mentoringCategory = SessionCategory::factory()->create([
        'type' => SessionCategoryType::MENTORING,
    ]);
});

describe('getProgrammeSeatSessions', function () {
    it('returns only published sessions that have ended and are after the seat start date', function () {
        // Create a valid session
        $validSession = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => Carbon::now()->subWeeks(2),
            'end_datetime' => Carbon::now()->subWeek(),
            'category_id' => $this->learningCategory->id,
        ]);

        // Create a draft session
        $draftSession = EngaugeSession::factory()->create([
            'status' => 'draft',
            'start_datetime' => Carbon::now()->subWeeks(2),
            'end_datetime' => Carbon::now()->subWeek(),
            'category_id' => $this->learningCategory->id,
        ]);

        // Create a session that hasn't ended yet
        $futureSession = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => Carbon::now()->addDays(2),
            'end_datetime' => Carbon::now()->addWeek(),
            'category_id' => $this->learningCategory->id,
        ]);

        // Create a session that started before the seat's start date
        $oldSession = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => Carbon::now()->subWeeks(6),
            'end_datetime' => Carbon::now()->subWeeks(6)->addHours(2),
            'category_id' => $this->learningCategory->id,
        ]);

        // Create EngaugeSessionUser records (observer will automatically create progress records)
        EngaugeSessionUser::create([
            'engauge_session_id' => $validSession->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        EngaugeSessionUser::create([
            'engauge_session_id' => $draftSession->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        EngaugeSessionUser::create([
            'engauge_session_id' => $futureSession->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        EngaugeSessionUser::create([
            'engauge_session_id' => $oldSession->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        // Update progress records (observer creates them automatically with null values)
        EngaugeSessionProgress::where([
            'engauge_session_id' => $validSession->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => true]);

        EngaugeSessionProgress::where([
            'engauge_session_id' => $draftSession->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => true]);

        EngaugeSessionProgress::where([
            'engauge_session_id' => $futureSession->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => null]);

        EngaugeSessionProgress::where([
            'engauge_session_id' => $oldSession->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => true]);

        $result = $this->service->getProgrammeSeatSessions()->get();

        expect($result)->toHaveCount(1);
        expect($result->first()->engauge_session_id)->toBe($validSession->id);
    });

    it('uses ignition date as the start date when contract start date is null', function () {
        // Update seat to have null contract_start_date but set ignition_date
        $ignitionDate = Carbon::now()->subWeeks(3);
        $this->seat->update([
            'contract_start_date' => null,
            'ignition_date' => $ignitionDate,
        ]);

        // Create two sessions: one after ignition date and one before
        $sessionAfterIgnition = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => $ignitionDate->copy()->addWeek(),
            'end_datetime' => $ignitionDate->copy()->addWeek()->addHours(2),
            'category_id' => $this->learningCategory->id,
        ]);

        $sessionBeforeIgnition = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => $ignitionDate->copy()->subWeek(),
            'end_datetime' => $ignitionDate->copy()->subWeek()->addHours(2),
            'category_id' => $this->learningCategory->id,
        ]);

        // Create EngaugeSessionUser records
        EngaugeSessionUser::create([
            'engauge_session_id' => $sessionAfterIgnition->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        EngaugeSessionUser::create([
            'engauge_session_id' => $sessionBeforeIgnition->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        // Update progress records
        EngaugeSessionProgress::where([
            'engauge_session_id' => $sessionAfterIgnition->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => true]);

        EngaugeSessionProgress::where([
            'engauge_session_id' => $sessionBeforeIgnition->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => true]);

        $result = $this->service->getProgrammeSeatSessions()->get();

        expect($result)->toHaveCount(1);
        expect($result->first()->engauge_session_id)->toBe($sessionAfterIgnition->id);
    });

    it('eager loads the required relationships', function () {
        // Create a valid session
        $session = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => Carbon::now()->subWeeks(2),
            'end_datetime' => Carbon::now()->subWeek(),
            'category_id' => $this->learningCategory->id,
        ]);

        // Create EngaugeSessionUser record
        EngaugeSessionUser::create([
            'engauge_session_id' => $session->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        // Update progress record
        EngaugeSessionProgress::where([
            'engauge_session_id' => $session->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => true]);

        $result = $this->service->getProgrammeSeatSessions()->get();

        expect($result)->toHaveCount(1);

        $sessionUser = $result->first();
        expect($sessionUser->relationLoaded('progress'))->toBeTrue();
        expect($sessionUser->relationLoaded('session'))->toBeTrue();
        expect($sessionUser->session->relationLoaded('category'))->toBeTrue();
    });

    it('returns an empty query and logs a warning if user has no engauge user id', function () {
        Log::spy();

        // Create a user without engauge_user_id
        $userWithoutEngaugeId = User::factory()->make(['engauge_user_id' => null]);
        // To avoid the observer setting the engauge_user_id
        $userWithoutEngaugeId->saveQuietly();
        $seatWithoutEngaugeUser = OrganisationProgrammeSeat::factory()
            ->user($userWithoutEngaugeId)
            ->create();

        $service = new ProgrammeSessionAttendanceService($seatWithoutEngaugeUser);

        $result = $service->getProgrammeSeatSessions()->get();

        expect($result)->toHaveCount(0);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'No EngaugeUser found for user');
            });
    });
});

describe('getProgrammeSeatSessionsByType', function () {
    beforeEach(function () {
        // Create sessions with different types
        $this->learningSession = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => Carbon::now()->subWeeks(2),
            'end_datetime' => Carbon::now()->subWeek(),
            'category_id' => $this->learningCategory->id,
        ]);

        $this->mentoringSession = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => Carbon::now()->subWeeks(2),
            'end_datetime' => Carbon::now()->subWeek(),
            'category_id' => $this->mentoringCategory->id,
        ]);

        // Create EngaugeSessionUser records
        EngaugeSessionUser::create([
            'engauge_session_id' => $this->learningSession->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        EngaugeSessionUser::create([
            'engauge_session_id' => $this->mentoringSession->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        // Update progress records
        EngaugeSessionProgress::where([
            'engauge_session_id' => $this->learningSession->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => true]);

        EngaugeSessionProgress::where([
            'engauge_session_id' => $this->mentoringSession->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => true]);
    });

    it('filters sessions by learning type when specified', function () {
        $result = $this->service->getProgrammeSeatSessionsByType(SessionCategoryType::LEARNING);

        expect($result)->toHaveCount(1);
        expect($result->first()->engauge_session_id)->toBe($this->learningSession->id);
    });

    it('filters sessions by mentoring type when specified', function () {
        $result = $this->service->getProgrammeSeatSessionsByType(SessionCategoryType::MENTORING);

        expect($result)->toHaveCount(1);
        expect($result->first()->engauge_session_id)->toBe($this->mentoringSession->id);
    });

    it('returns all session types when type is null', function () {
        $result = $this->service->getProgrammeSeatSessionsByType(null);

        expect($result)->toHaveCount(2);
        $sessionIds = $result->pluck('engauge_session_id')->toArray();
        expect($sessionIds)->toContain($this->learningSession->id);
        expect($sessionIds)->toContain($this->mentoringSession->id);
    });
});

describe('getAttendanceStats', function () {
    it('correctly calculates all stats when all attendance statuses are present', function () {
        // Create sessions with mixed attendance statuses
        $sessions = collect();

        // 5 attended sessions
        for ($i = 0; $i < 5; $i++) {
            $session = EngaugeSession::factory()->create([
                'status' => 'published',
                'start_datetime' => Carbon::now()->subWeeks(4),
                'end_datetime' => Carbon::now()->subWeeks(2)->addHours(2),
                'category_id' => $this->learningCategory->id,
            ]);

            EngaugeSessionUser::create([
                'engauge_session_id' => $session->id,
                'user_id' => $this->engaugeUser->id,
            ]);

            EngaugeSessionProgress::where([
                'engauge_session_id' => $session->id,
                'user_id' => $this->engaugeUser->id,
            ])->update(['attended' => true]);

            $sessions->push($session);
        }

        // 3 missed sessions
        for ($i = 0; $i < 3; $i++) {
            $session = EngaugeSession::factory()->create([
                'status' => 'published',
                'start_datetime' => Carbon::now()->subWeeks(2),
                'end_datetime' => Carbon::now()->subWeeks(2)->addHours(2),
                'category_id' => $this->learningCategory->id,
            ]);

            EngaugeSessionUser::create([
                'engauge_session_id' => $session->id,
                'user_id' => $this->engaugeUser->id,
            ]);

            EngaugeSessionProgress::where([
                'engauge_session_id' => $session->id,
                'user_id' => $this->engaugeUser->id,
            ])->update(['attended' => false]);

            $sessions->push($session);
        }

        // 2 not marked sessions
        for ($i = 0; $i < 2; $i++) {
            $session = EngaugeSession::factory()->create([
                'status' => 'published',
                'start_datetime' => Carbon::now()->subWeeks(2),
                'end_datetime' => Carbon::now()->subWeeks(2)->addHours(2),
                'category_id' => $this->learningCategory->id,
            ]);

            EngaugeSessionUser::create([
                'engauge_session_id' => $session->id,
                'user_id' => $this->engaugeUser->id,
            ]);

            EngaugeSessionProgress::where([
                'engauge_session_id' => $session->id,
                'user_id' => $this->engaugeUser->id,
            ])->update(['attended' => null]);

            $sessions->push($session);
        }

        $result = $this->service->getAttendanceStats();

        expect($result['attended'])->toBe(5);
        expect($result['missed'])->toBe(3);
        expect($result['notMarked'])->toBe(2);
        expect($result['total'])->toBe(10);
        expect($result['percentage'])->toBe(62.5); // (5 / (10 - 2)) * 100 = 62.5
    });

    it('calculates percentage as zero when no sessions are marked', function () {
        // Create sessions where all have attended = null
        for ($i = 0; $i < 3; $i++) {
            $session = EngaugeSession::factory()->create([
                'status' => 'published',
                'start_datetime' => Carbon::now()->subWeeks(2),
                'end_datetime' => Carbon::now()->subWeeks(2)->addHours(2),
                'category_id' => $this->learningCategory->id,
            ]);

            EngaugeSessionUser::create([
                'engauge_session_id' => $session->id,
                'user_id' => $this->engaugeUser->id,
            ]);

            EngaugeSessionProgress::where([
                'engauge_session_id' => $session->id,
                'user_id' => $this->engaugeUser->id,
            ])->update(['attended' => null]);
        }

        $result = $this->service->getAttendanceStats();

        expect($result['attended'])->toBe(0);
        expect($result['missed'])->toBe(0);
        expect($result['notMarked'])->toBe(3);
        expect($result['total'])->toBe(3);
        expect($result['percentage'])->toBe(0);
    });

    it('calculates percentage correctly when there are no missed sessions', function () {
        // Create 4 attended sessions
        for ($i = 0; $i < 4; $i++) {
            $session = EngaugeSession::factory()->create([
                'status' => 'published',
                'start_datetime' => Carbon::now()->subWeeks(2),
                'end_datetime' => Carbon::now()->subWeeks(2)->addHours(2),
                'category_id' => $this->learningCategory->id,
            ]);

            EngaugeSessionUser::create([
                'engauge_session_id' => $session->id,
                'user_id' => $this->engaugeUser->id,
            ]);

            EngaugeSessionProgress::where([
                'engauge_session_id' => $session->id,
                'user_id' => $this->engaugeUser->id,
            ])->update(['attended' => true]);
        }

        // Create 1 not marked session
        $session = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => Carbon::now()->subWeeks(2),
            'end_datetime' => Carbon::now()->subWeeks(2)->addHours(2),
            'category_id' => $this->learningCategory->id,
        ]);

        EngaugeSessionUser::create([
            'engauge_session_id' => $session->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        EngaugeSessionProgress::where([
            'engauge_session_id' => $session->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => null]);

        $result = $this->service->getAttendanceStats();

        expect($result['attended'])->toBe(4);
        expect($result['missed'])->toBe(0);
        expect($result['notMarked'])->toBe(1);
        expect($result['total'])->toBe(5);
        expect($result['percentage'])->toBe(100); // (4 / (5 - 1)) * 100 = 100
    });

    it('returns all zeroes for an empty session collection', function () {
        // Don't create any sessions
        $result = $this->service->getAttendanceStats();

        expect($result['attended'])->toBe(0);
        expect($result['missed'])->toBe(0);
        expect($result['notMarked'])->toBe(0);
        expect($result['total'])->toBe(0);
        expect($result['percentage'])->toBe(0);
    });

    it('includes detailed meta data when includeMeta is true', function () {
        // Create 2 sessions with different attendance
        $session1 = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => Carbon::now()->subWeek(),
            'end_datetime' => Carbon::now()->subWeek()->addHours(2),
            'category_id' => $this->learningCategory->id,
        ]);

        $session2 = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => Carbon::now()->subWeeks(2),
            'end_datetime' => Carbon::now()->subWeeks(2)->addHours(2),
            'category_id' => $this->learningCategory->id,
        ]);

        EngaugeSessionUser::create([
            'engauge_session_id' => $session1->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        EngaugeSessionUser::create([
            'engauge_session_id' => $session2->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        EngaugeSessionProgress::where([
            'engauge_session_id' => $session1->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => true]);

        EngaugeSessionProgress::where([
            'engauge_session_id' => $session2->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => false]);

        $result = $this->service->getAttendanceStats(null, true);

        expect($result['meta'])->not()->toBeEmpty();
        expect($result['meta'])->toHaveCount(2);

        // Check structure of meta items
        expect($result['meta'][0])->toHaveKeys(['session_id', 'start_datetime', 'attended']);
        expect($result['meta'][1])->toHaveKeys(['session_id', 'start_datetime', 'attended']);

        // Verify the data
        $metaSessionIds = collect($result['meta'])->pluck('session_id')->toArray();
        expect($metaSessionIds)->toContain($session1->id);
        expect($metaSessionIds)->toContain($session2->id);
    });

    it('returns an empty meta array when includeMeta is false', function () {
        // Create a session
        $session = EngaugeSession::factory()->create([
            'status' => 'published',
            'start_datetime' => Carbon::now()->subWeek(),
            'end_datetime' => Carbon::now()->subWeek()->addHours(2),
            'category_id' => $this->learningCategory->id,
        ]);

        EngaugeSessionUser::create([
            'engauge_session_id' => $session->id,
            'user_id' => $this->engaugeUser->id,
        ]);

        EngaugeSessionProgress::where([
            'engauge_session_id' => $session->id,
            'user_id' => $this->engaugeUser->id,
        ])->update(['attended' => true]);

        $result = $this->service->getAttendanceStats(null, false);

        expect($result['meta'])->toBe([]);
    });
});
