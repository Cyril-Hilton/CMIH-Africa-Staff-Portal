<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CVOController extends Controller
{
    public function dashboard(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isCvoOrSuperAdmin(), 403);

        return redirect()->route('dashboard', ['weekly_department' => 'all']);
    }
}
