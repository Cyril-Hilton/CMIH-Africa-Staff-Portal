<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\RevenueReview;
use Illuminate\Http\Request;

class RevenueReviewController extends Controller
{
    public function store(Request $r, string $type, int $id, RevenueReview $service)
    {
        abort_unless(in_array($type, ['project', 'target']), 404);
        $v = $r->validate(['revision' => 'required|integer|min:1', 'action' => 'required|in:submit,confirm,correct,accept,reject', 'amount' => 'nullable|numeric|min:0|max:9999999999.99', 'reason' => 'required|string|min:5|max:2000']);
        $service->act($type, $id, $r->user(), $v);

        return back()->with('status', 'Revenue review saved. Only jointly confirmed revenue contributes to confirmed totals.');
    }
}
