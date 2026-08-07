<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index()
    {
        $theme = SiteContent::getValue('site_theme', 'BOLDER and BETTER');
        $radius = SiteContent::getValue('merchandiser_radius', '30');
        return view('admin.settings', compact('theme', 'radius'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_theme' => ['required', 'string', 'max:255'],
            'merchandiser_radius' => ['required', 'numeric', 'min:1', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            SiteContent::updateOrCreate(
                ['key' => 'site_theme'],
                [
                    'value' => $validated['site_theme'],
                    'type' => 'text',
                    'updated_by' => $request->user()->id,
                ]
            );

            SiteContent::updateOrCreate(
                ['key' => 'merchandiser_radius'],
                [
                    'value' => $validated['merchandiser_radius'],
                    'type' => 'text',
                    'updated_by' => $request->user()->id,
                ]
            );
        });

        return back()->with('success', 'System settings updated successfully.');
    }
}
