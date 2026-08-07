<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortfolioPayment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioPaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request): View
    {
        if ($request->user()->access_role !== 'super_admin') {
            abort(403, 'Unauthorized.');
        }

        $payments = PortfolioPayment::latest()->paginate(15);

        return view('admin.portfolio-payments', compact('payments'));
    }
}
