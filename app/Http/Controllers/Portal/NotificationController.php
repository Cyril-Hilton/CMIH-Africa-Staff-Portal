<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Task;
use App\Models\CreativeComment;
use App\Models\LeaveApplication;
use App\Models\PettyCashClaim;
use App\Models\ProjectBudget;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    private const PER_TYPE_LIMIT = 8;
    private const MAX_POLL_NOTIFICATIONS = 30;

    /**
     * Determine if user is CVO or Super Admin.
     */
    private function isCVO(User $user): bool
    {
        return $user->job_level === 'super_admin'
            || $user->access_role === 'super_admin'
            || in_array(strtolower(trim($user->name)), ['cyril hilton', 'cyril hilton wemegah'], true);
    }

    private function formatPersistentNotification(Notification $notification): array
    {
        return [
            'id' => 'notif_' . $notification->id,
            'type' => 'notification',
            'title' => $notification->title,
            'message' => $notification->message,
            'url' => $notification->url ? route('portal.notifications.read', $notification->id) : null,
        ];
    }

    private function recentUnreadPersistentNotifications(User $user): array
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subHours(16))
            ->latest()
            ->limit(8)
            ->get()
            ->reverse()
            ->map(fn (Notification $notification) => $this->formatPersistentNotification($notification))
            ->values()
            ->all();
    }

    /**
     * Poll for any new events/notifications since a given timestamp.
     */
    public function poll(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $sinceStr = $request->query('since');
        if (!$sinceStr) {
            return response()->json([
                'timestamp' => now()->toIso8601String(),
                'notifications' => $this->recentUnreadPersistentNotifications($user),
                'unread_count' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
                'unread_message_count' => Message::unreadFor($user)->count(),
            ]);
        }

        try {
            $since = Carbon::parse($sinceStr);
        } catch (\Exception $e) {
            $since = now()->subMinutes(5);
        }

        $notifications = [];

        // 1. Messages in user's conversations
        $newMessages = Message::where('created_at', '>', $since)
            ->where('user_id', '!=', $user->id)
            ->whereHas('conversation.users', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->with(['user', 'conversation'])
            ->latest()
            ->limit(self::PER_TYPE_LIMIT)
            ->get();

        foreach ($newMessages as $message) {
            $sender = $message->user ? $message->user->name : 'Someone';
            $convName = $message->conversation ? $message->conversation->name : 'Chat';
            
            if ($message->conversation && $message->conversation->is_direct) {
                $notifications[] = [
                    'id' => 'msg_' . $message->id,
                    'type' => 'message',
                    'title' => 'New Message',
                    'message' => "{$sender} sent you a message.",
                    'url' => route('portal.messages.show', $message->conversation_id),
                ];
            } else {
                $notifications[] = [
                    'id' => 'msg_' . $message->id,
                    'type' => 'message',
                    'title' => "New Message in {$convName}",
                    'message' => "{$sender}: " . Str::limit($message->displayBody(), 50),
                    'url' => route('portal.messages.show', $message->conversation_id),
                ];
            }
        }

        // 2. Announcements
        $newAnnouncements = Announcement::where('created_at', '>', $since)
            ->where('user_id', '!=', $user->id)
            ->visibleTo($user)
            ->with('user')
            ->latest()
            ->limit(self::PER_TYPE_LIMIT)
            ->get();

        foreach ($newAnnouncements as $announcement) {
            $sender = $announcement->user ? $announcement->user->name : 'Admin';
            $notifications[] = [
                'id' => 'ann_' . $announcement->id,
                'type' => 'announcement',
                'title' => 'New Announcement',
                'message' => "{$sender} posted: {$announcement->title}",
                'url' => route('portal.announcements'),
            ];
        }

        // 3. Tasks assigned to user or user's department
        $deptMapping = [
            'hr_admin'            => ['hr_admin', 'admin', 'transport'],
            'finance'             => ['finance'],
            'client_relations'    => ['client_relations', 'client_service'],
            'operations_projects' => ['operations_projects', 'operations'],
            'brands_marketing'    => ['brands_marketing', 'brands'],
            'creatives'           => ['creatives'],
        ];

        $userDepts = [];
        if ($user->department) {
            foreach ($deptMapping as $key => $aliases) {
                if (in_array($user->department, $aliases)) {
                    $userDepts = $aliases;
                    break;
                }
            }
            if (empty($userDepts)) {
                $userDepts = [$user->department];
            }
        }

        $newTasks = Task::where('created_at', '>', $since)
            ->where('assigned_by', '!=', $user->id)
            ->where(function ($query) use ($user, $userDepts) {
                $query->where('assigned_to', $user->id);
                if (!empty($userDepts)) {
                    $query->orWhereIn('department', $userDepts);
                }
            })
            ->with('assigner')
            ->latest()
            ->limit(self::PER_TYPE_LIMIT)
            ->get();

        foreach ($newTasks as $task) {
            $assigner = $task->assigner ? $task->assigner->name : 'Someone';
            $notifications[] = [
                'id' => 'task_' . $task->id,
                'type' => 'task',
                'title' => 'New Task Assigned',
                'message' => "{$assigner} created task: {$task->title}",
                'url' => route('portal.tasks'),
            ];
        }

        // 4. Creative comments on tasks where the user is involved
        $newComments = CreativeComment::where('created_at', '>', $since)
            ->where('user_id', '!=', $user->id)
            ->whereHas('task', function ($query) use ($user) {
                $query->where('assigned_to', $user->id)
                      ->orWhere('assigned_by', $user->id)
                      ->orWhereJsonContains('supporting_staff_ids', (string)$user->id)
                      ->orWhereJsonContains('supporting_staff_ids', $user->id);
            })
            ->with(['user', 'task'])
            ->latest()
            ->limit(self::PER_TYPE_LIMIT)
            ->get();

        foreach ($newComments as $comment) {
            $commenter = $comment->user ? $comment->user->name : 'Someone';
            $notifications[] = [
                'id' => 'comment_' . $comment->id,
                'type' => 'comment',
                'title' => 'New Task Comment',
                'message' => "{$commenter} commented on: {$comment->task->title}",
                'url' => route('portal.tasks'),
            ];
        }

        // 5. Financial items (CVO approvals)
        if ($this->isCVO($user)) {
            // New Petty Cash claims
            $newClaims = PettyCashClaim::where('created_at', '>', $since)
                ->where('user_id', '!=', $user->id)
                ->with('user')
                ->latest()
                ->limit(self::PER_TYPE_LIMIT)
                ->get();
            foreach ($newClaims as $claim) {
                $name = $claim->user ? $claim->user->name : 'Staff';
                $notifications[] = [
                    'id' => 'claim_' . $claim->id,
                    'type' => 'finance',
                    'title' => 'New Petty Cash Claim',
                    'message' => "{$name} claimed {$claim->currency} {$claim->amount}",
                    'url' => route('portal.cvo'),
                ];
            }

            // New Project Budgets
            $newBudgets = ProjectBudget::where('created_at', '>', $since)
                ->where('created_by', '!=', $user->id)
                ->with('creator')
                ->latest()
                ->limit(self::PER_TYPE_LIMIT)
                ->get();
            foreach ($newBudgets as $budget) {
                $name = $budget->creator ? $budget->creator->name : 'Staff';
                $notifications[] = [
                    'id' => 'budget_' . $budget->id,
                    'type' => 'finance',
                    'title' => 'New Project Budget',
                    'message' => "{$name} created budget: {$budget->title} ({$budget->currency} {$budget->total_amount})",
                    'url' => route('portal.cvo'),
                ];
            }

            // New Supplier Invoices
            $newInvoices = SupplierInvoice::where('created_at', '>', $since)
                ->where('submitted_by', '!=', $user->id)
                ->with('submitter')
                ->latest()
                ->limit(self::PER_TYPE_LIMIT)
                ->get();
            foreach ($newInvoices as $invoice) {
                $name = $invoice->submitter ? $invoice->submitter->name : 'Staff';
                $notifications[] = [
                    'id' => 'invoice_' . $invoice->id,
                    'type' => 'finance',
                    'title' => 'New Supplier Invoice',
                    'message' => "{$name} submitted invoice #{$invoice->invoice_number} from {$invoice->supplier_name}",
                    'url' => route('portal.cvo'),
                ];
            }
        }

        // 6. Leave Applications pending manager / CVO approval
        $isCVO = $this->isCVO($user);
        $hasSubordinates = $user->subordinates()->exists();

        if ($isCVO || $hasSubordinates) {
            $query = LeaveApplication::where('created_at', '>', $since)
                ->where('user_id', '!=', $user->id);

            if ($isCVO) {
                $query->where('status', 'pending_cvo');
            } else {
                $query->where('line_manager_id', $user->id)->where('status', 'pending_manager');
            }

            $newLeaves = $query->with('user')->latest()->limit(self::PER_TYPE_LIMIT)->get();
            foreach ($newLeaves as $leave) {
                $name = $leave->user ? $leave->user->name : 'Staff';
                $notifications[] = [
                    'id' => 'leave_' . $leave->id,
                    'type' => 'leave',
                    'title' => 'New Leave Application',
                    'message' => "{$name} requested leave: {$leave->leave_type}",
                    'url' => $isCVO ? route('portal.cvo') : route('portal.leaves'),
                ];
            }
        }

        // 7. Profile Level-up & Department Change Requests (Super Admin only)
        if ($user->access_role === 'super_admin') {
            $newRequests = User::where(function ($q) {
                $q->whereNotNull('requested_position_title')
                  ->orWhereNotNull('requested_department');
            })
            ->where('requested_change_at', '>', $since)
            ->where('id', '!=', $user->id)
            ->latest('requested_change_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get();

            foreach ($newRequests as $req) {
                $notifications[] = [
                    'id' => 'profile_req_' . $req->id . '_' . ($req->requested_change_at ? $req->requested_change_at->timestamp : time()),
                    'type' => 'profile_change',
                    'title' => 'Profile Change Request',
                    'message' => "{$req->name} requested a Level-up or Department change.",
                    'url' => route('admin.users'),
                ];
            }
        }

        // 8. Project Budget status updates (notify creator, collaborators, and finance)
        $isFinanceDept = strtolower(trim($user->department ?? '')) === 'finance'
            || $user->access_role === 'super_admin';
        
        $updatedBudgets = ProjectBudget::where('updated_at', '>', $since)
            ->where(function ($query) use ($user, $isFinanceDept) {
                $query->where('created_by', $user->id)
                      ->orWhereHas('collaborators', function ($q) use ($user) {
                          $q->where('users.id', $user->id);
                      });
                if ($isFinanceDept) {
                    $query->orWhereIn('status', ['Submitted to Finance', 'Finance Approved', 'CVO Approved', 'Rejected']);
                }
            })
            ->with(['creator'])
            ->latest('updated_at')
            ->limit(self::PER_TYPE_LIMIT)
            ->get();

        foreach ($updatedBudgets as $budget) {
            $notifications[] = [
                'id' => 'budget_update_' . $budget->id . '_' . $budget->updated_at->timestamp,
                'type' => 'finance',
                'title' => "Budget: {$budget->title}",
                'message' => "Budget status is now: {$budget->status}",
                'url' => route('portal.finance.budgets.show', $budget->id),
            ];
        }

        // 9. Persistent notifications from the new notifications table
        $newNotifs = Notification::where('user_id', $user->id)
            ->where('created_at', '>', $since)
            ->latest()
            ->limit(self::PER_TYPE_LIMIT)
            ->get();

        foreach ($newNotifs as $notif) {
            $notifications[] = $this->formatPersistentNotification($notif);
        }

        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'notifications' => array_slice($notifications, 0, self::MAX_POLL_NOTIFICATIONS),
            'unread_count' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
            'unread_message_count' => Message::unreadFor($user)->count(),
        ]);
    }
}
