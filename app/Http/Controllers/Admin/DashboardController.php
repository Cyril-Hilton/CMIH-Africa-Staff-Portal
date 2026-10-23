<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /** Administrative tools remain on their existing authorised routes. */
    public function index(): RedirectResponse
    {
        return redirect()->route('dashboard', ['weekly_department' => 'all']);
    }
}
