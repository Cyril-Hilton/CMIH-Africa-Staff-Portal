<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PerformanceAward;
use App\Mail\AwardCertificateMail;
use App\Services\PerformanceScoringService;
use App\Services\TaskStatsService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class PerformanceAwardController extends Controller
{
    private const DEPARTMENTS = [
        'hr_admin'            => 'HR & Admin',
        'finance'             => 'Finance',
        'client_relations'    => 'Client Relations',
        'operations_projects' => 'Operations / Projects',
        'brands_marketing'    => 'Brands & Marketing',
        'creatives'           => 'Creatives',
    ];

    private const DEPARTMENT_MAPPING = [
        'hr_admin'            => ['hr_admin', 'admin', 'transport'],
        'finance'             => ['finance'],
        'client_relations'    => ['client_relations', 'client_service'],
        'operations_projects' => ['operations_projects', 'operations'],
        'brands_marketing'    => ['brands_marketing', 'brands'],
        'creatives'           => ['creatives'],
    ];

    private function checkPermission(User $user): void
    {
        $isSuperOrAdmin = in_array($user->access_role, ['super_admin', 'admin']);
        $userDept       = strtolower(trim($user->department ?? ''));
        $isHR           = in_array($userDept, ['hr_admin', 'admin'], true);

        if (!$isSuperOrAdmin && !$isHR) {
            abort(403, '🔒 Access Denied. Only HR staff or administrators can lock awards.');
        }
    }

    /**
     * Compute and get standings (real-time).
     */
    public function getStandings(Request $request): JsonResponse
    {
        $period = $request->query('period', Carbon::now()->format('Y-m'));
        $standings = $this->calculateStandingsData($period);
        
        // Check if there is already a locked award for this period
        $lockedAwards = PerformanceAward::with(['winner', 'firstRunnerUp', 'secondRunnerUp'])
            ->where('period', $period)
            ->get()
            ->keyBy('award_type');

        return response()->json([
            'period' => $period,
            'calculated' => $standings,
            'locked' => $lockedAwards->map(function ($award) {
                return [
                    'winner' => $award->winner ? $award->winner->name : null,
                    'winner_avatar' => $award->winner ? $award->winner->profilePhotoUrl() : null,
                    'first_runner_up' => $award->firstRunnerUp ? $award->firstRunnerUp->name : null,
                    'second_runner_up' => $award->secondRunnerUp ? $award->secondRunnerUp->name : null,
                    'winner_val' => $award->winner_val,
                    'winner_val_label' => PerformanceAward::getDepartmentLabel($award->winner_val),
                    'first_runner_up_val' => $award->first_runner_up_val,
                    'first_runner_up_val_label' => PerformanceAward::getDepartmentLabel($award->first_runner_up_val),
                    'second_runner_up_val' => $award->second_runner_up_val,
                    'second_runner_up_val_label' => PerformanceAward::getDepartmentLabel($award->second_runner_up_val),
                    'winner_score' => $award->winner_score,
                    'first_runner_up_score' => $award->first_runner_up_score,
                    'second_runner_up_score' => $award->second_runner_up_score,
                ];
            })
        ]);
    }

    /**
     * Lock and Issue Awards.
     */
    public function lockAward(Request $request): RedirectResponse
    {
        $this->checkPermission($request->user());

        $request->validate([
            'award_type' => ['required', 'string', 'in:employee_of_the_month,department_of_the_month,employee_of_the_year,department_of_the_year'],
            'period' => ['required', 'string', 'max:16'],
            'winner_id' => ['nullable', 'exists:users,id'],
            'first_runner_up_id' => ['nullable', 'exists:users,id'],
            'second_runner_up_id' => ['nullable', 'exists:users,id'],
            'winner_val' => ['nullable', 'string', 'max:100'],
            'first_runner_up_val' => ['nullable', 'string', 'max:100'],
            'second_runner_up_val' => ['nullable', 'string', 'max:100'],
            'winner_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'first_runner_up_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'second_runner_up_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $award = PerformanceAward::updateOrCreate(
            [
                'award_type' => $request->input('award_type'),
                'period' => $request->input('period'),
            ],
            $request->only([
                'winner_id', 'first_runner_up_id', 'second_runner_up_id',
                'winner_val', 'first_runner_up_val', 'second_runner_up_val',
                'winner_score', 'first_runner_up_score', 'second_runner_up_score'
            ])
        );

        // Dispatch email certificates to winner
        $sentCount = $this->dispatchAwardCertificates($award);

        $awardTypeLabel = ucwords(str_replace('_', ' ', $request->input('award_type')));
        return back()->with('status', "🏆 {$awardTypeLabel} awards locked & certificates sent successfully to {$sentCount} recipient(s) for period: {$request->input('period')}!");
    }

    /**
     * Resend Certificates for a locked award period (HR, Admin & Super Admin).
     */
    public function resendCertificates(Request $request): RedirectResponse
    {
        $user = $request->user();
        $canResend = $user->access_role === 'super_admin'
            || $user->access_role === 'admin'
            || $user->hasFullHrAccess();

        abort_unless($canResend, 403, '🔒 Access Denied. Only HR staff, Admins, and Super Admins can resend award certificates.');

        $request->validate([
            'award_type' => ['required', 'string'],
            'period' => ['required', 'string'],
        ]);

        $award = PerformanceAward::where('award_type', $request->input('award_type'))
            ->where('period', $request->input('period'))
            ->first();

        if (! $award) {
            return back()->withErrors(['award' => "No locked award found for period {$request->input('period')}."]);
        }

        $sentCount = $this->dispatchAwardCertificates($award);

        $awardTypeLabel = ucwords(str_replace('_', ' ', $award->award_type));

        return back()->with('status', "🏆 {$awardTypeLabel} certificates successfully resent to {$sentCount} recipient(s) for period: {$award->period}!");
    }

    private function dispatchAwardCertificates(PerformanceAward $award): int
    {
        $count = 0;

        if (str_contains($award->award_type, 'employee')) {
            $winner = User::find($award->winner_id);
            if ($winner) {
                $emails = array_unique(array_filter([$winner->contact_email, $winner->email]));
                if (! empty($emails)) {
                    try {
                        Mail::to($emails)->send(new AwardCertificateMail(
                            $award->award_type,
                            $award->period,
                            $winner
                        ));
                        $count++;
                    } catch (\Exception $e) {
                        logger()->error("Award certificate email failed for {$winner->name}: " . $e->getMessage());
                    }
                }
            }
        } else {
            // Department award
            $winnerDept = $award->winner_val;
            if ($winnerDept) {
                $deptMembers = User::where('department', $winnerDept)
                    ->internalStaff()
                    ->where('status', 'active')
                    ->get();

                foreach ($deptMembers as $member) {
                    $emails = array_unique(array_filter([$member->contact_email, $member->email]));
                    if (! empty($emails)) {
                        try {
                            Mail::to($emails)->send(new AwardCertificateMail(
                                $award->award_type,
                                $award->period,
                                $member,
                                $winnerDept
                            ));
                            $count++;
                        } catch (\Exception $e) {
                            logger()->error("Award certificate email failed for department member {$member->name}: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Internal calculator engine for standings.
     */
    public function calculateStandingsData(string $period): array
    {
        if (strlen($period) === 7) {
            $startDate = Carbon::createFromFormat('!Y-m', $period)->startOfMonth();
            $endDate = Carbon::createFromFormat('!Y-m', $period)->endOfMonth();
        } else {
            $startDate = Carbon::createFromFormat('Y', $period)->startOfYear();
            $endDate = Carbon::createFromFormat('Y', $period)->endOfYear();

            $trackingStart = PerformanceScoringService::trackingStartedAt();
            if ($trackingStart && $trackingStart->gt($startDate)) {
                $startDate = $trackingStart;
            }
        }

        // 1. Employees calculations
        $users = User::internalStaff()->where('status', 'active')->get();
        $employeeStandings = [];

        foreach ($users as $user) {
            if ($this->shouldExcludeFromAwardStandings($user)) {
                continue;
            }

            $score = $this->scoreUserForPeriod($user, $startDate, $endDate);

            if (! $score['has_activity']) {
                continue; // Skip inactive user
            }

            $employeeStandings[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->profilePhotoUrl(),
                'department' => PerformanceAward::getDepartmentLabel($user->department),
                'score' => $score['score'],
                'task_rate' => $score['task_rate'],
                'punctuality' => $score['punctuality'],
                'attendance_rate' => $score['attendance_rate'],
                'task_weighted' => $score['task_weighted'],
                'punctuality_weighted' => $score['punctuality_weighted'],
                'attendance_weighted' => $score['attendance_weighted'],
                'expected_workdays' => $score['expected_workdays'],
                'attendance_days' => $score['attendance_days'],
            ];
        }

        // Sort descending
        usort($employeeStandings, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // 2. Department calculations
        $deptStandings = [];

        foreach (self::DEPARTMENTS as $key => $label) {
            $mappedDepts = self::DEPARTMENT_MAPPING[$key] ?? [$key];
            $deptUsers = User::internalStaff()
                ->whereIn('department', $mappedDepts)
                ->where('status', 'active')
                ->get()
                ->reject(fn (User $member) => $this->shouldExcludeFromAwardStandings($member))
                ->values();

            $memberScores = $deptUsers->map(function (User $member) use ($startDate, $endDate) {
                $score = $this->scoreUserForPeriod($member, $startDate, $endDate, includeInactiveAsZero: true);

                return array_merge($score, [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'avatar' => $member->profilePhotoUrl(),
                    'department' => PerformanceAward::getDepartmentLabel($member->department),
                ]);
            });

            $deptScore = $memberScores->isNotEmpty() ? $memberScores->avg('score') : 0;
            $deptTaskRate = $memberScores->isNotEmpty() ? $memberScores->avg('task_rate') : 0;
            $deptPunctuality = $memberScores->isNotEmpty() ? $memberScores->avg('punctuality') : 0;
            $deptAttendanceRate = $memberScores->isNotEmpty() ? $memberScores->avg('attendance_rate') : 0;
            $memberCount = $deptUsers->count();

            $deptStandings[] = [
                'key' => $key,
                'label' => $label,
                'score' => round($deptScore, 1),
                'task_rate' => round($deptTaskRate, 1),
                'punctuality' => round($deptPunctuality, 1),
                'attendance_rate' => round($deptAttendanceRate, 1),
                'member_count' => $memberCount,
                'active_member_count' => $memberScores->where('has_activity', true)->count(),
                'members' => $memberScores
                    ->sortByDesc('score')
                    ->values()
                    ->map(fn (array $memberScore) => array_merge($memberScore, [
                        'score_contribution' => $memberCount > 0 ? round($memberScore['score'] / $memberCount, 1) : 0.0,
                        'task_contribution' => $memberCount > 0 ? round($memberScore['task_rate'] / $memberCount, 1) : 0.0,
                        'punctuality_contribution' => $memberCount > 0 ? round($memberScore['punctuality'] / $memberCount, 1) : 0.0,
                        'attendance_contribution' => $memberCount > 0 ? round($memberScore['attendance_rate'] / $memberCount, 1) : 0.0,
                    ]))
                    ->all(),
            ];
        }

        // Sort descending
        usort($deptStandings, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return [
            'employees' => array_slice($employeeStandings, 0, 10), // Top 10
            'departments' => $deptStandings
        ];
    }

    private function shouldExcludeFromAwardStandings(User $user): bool
    {
        $isCyril = in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'], true);

        return $user->access_role === 'super_admin' && ! $isCyril;
    }

    private function scoreUserForPeriod(User $user, Carbon $startDate, Carbon $endDate, bool $includeInactiveAsZero = false): array
    {
        $taskStats = TaskStatsService::forUser($user, $startDate, $endDate);
        $totalTasks = $taskStats['accountable_total'];
        $attendanceSummary = PerformanceScoringService::attendanceSummary($user, $startDate, $endDate);
        $totalAttendances = $attendanceSummary['attendance_days'];

        $hasActivity = $totalTasks > 0 || $totalAttendances > 0;

        if (! $hasActivity && $includeInactiveAsZero) {
            return [
                'score' => 0.0,
                'task_rate' => 0.0,
                'punctuality' => 0.0,
                'attendance_rate' => 0.0,
                'task_weighted' => 0.0,
                'punctuality_weighted' => 0.0,
                'attendance_weighted' => 0.0,
                'expected_workdays' => 0,
                'attendance_days' => 0,
                'has_activity' => false,
            ];
        }

        $taskCompletionRate = $taskStats['completion_rate'];

        $punctualityScore = $attendanceSummary['punctuality_score'];
        $attendanceRate = $attendanceSummary['attendance_rate'];
        $overallScore = $hasActivity
            ? (($taskCompletionRate * 0.2) + ($punctualityScore * 0.5) + ($attendanceRate * 0.3))
            : 0;

        return [
            'score' => round($overallScore, 1),
            'task_rate' => round($taskCompletionRate, 1),
            'punctuality' => round($punctualityScore, 1),
            'attendance_rate' => round($attendanceRate, 1),
            'task_weighted' => round($taskCompletionRate * 0.2, 1),
            'punctuality_weighted' => round($punctualityScore * 0.5, 1),
            'attendance_weighted' => round($attendanceRate * 0.3, 1),
            'expected_workdays' => $attendanceSummary['expected_workdays'],
            'attendance_days' => $attendanceSummary['attendance_days'],
            'has_activity' => $hasActivity,
        ];
    }
}
