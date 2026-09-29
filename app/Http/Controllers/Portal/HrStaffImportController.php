<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HrStaffImport;
use App\Services\HrStaffRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HrStaffImportController extends Controller
{
    public function index(Request $request, HrStaffImport $importer, HrStaffRecord $record)
    {
        abort_unless($request->user()?->hasFullHrAccess(), 403);

        return response()->view('portal.hr.staff-import', ['preview' => $request->session()->get('hr_staff_import'), 'importer' => $importer, 'record' => $record])->header('Cache-Control', 'private, no-store');
    }

    public function template(Request $request, HrStaffImport $importer, HrStaffRecord $record)
    {
        abort_unless($request->user()?->hasFullHrAccess(), 403);

        return response()->streamDownload(function () use ($importer, $record) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            $record->csvRow($handle, array_map($record->label(...), ['id', 'staff_id_number', 'email', ...array_keys($importer->rules())]));
            fclose($handle);
        }, 'staff-update-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function preview(Request $request, HrStaffImport $importer, HrStaffRecord $record)
    {
        abort_unless($request->user()?->hasFullHrAccess(), 403);
        $request->session()->forget('hr_staff_import');
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:csv,txt', 'extensions:csv']]);
        $preview = $importer->preview($request->file('file')->getRealPath(), $request->user(), $record);
        $preview['token'] = Str::random(40);
        $preview['expires'] = now()->addMinutes(30)->timestamp;
        $preview['viewer'] = $request->user()->id;
        $request->session()->put('hr_staff_import', $preview);

        return redirect()->route('portal.hr.staff.import');
    }

    public function confirm(Request $request, HrStaffImport $importer)
    {
        abort_unless($request->user()?->hasFullHrAccess(), 403);
        $request->validate(['token' => ['required', 'string']]);
        $preview = $request->session()->get('hr_staff_import');
        if (! $preview || $preview['viewer'] !== $request->user()->id || ! hash_equals($preview['token'], $request->string('token')->toString()) || $preview['expires'] < now()->timestamp || $preview['errors']) {
            throw ValidationException::withMessages(['file' => 'Upload a valid CSV and review a fresh preview before confirming.']);
        }
        $count = DB::transaction(function () use ($preview, $importer) {
            $count = 0;
            foreach ($preview['rows'] as $row) {
                $user = User::internalStaff()->lockForUpdate()->find($row['id']);
                if (! $user || ! hash_equals($row['fingerprint'], $importer->fingerprint($user))) {
                    throw ValidationException::withMessages(['file' => 'A staff record changed after the preview. Nothing was imported. Upload again to review the current values.']);
                }
                if ($row['updates']) {
                    $user->fill($row['updates'])->save();
                    $count++;
                }
            }

            return $count;
        });
        // Log record IDs and changed field names, never personal values.
        logger()->info('HR staff import completed', ['actor_id' => $request->user()->id, 'changes' => collect($preview['rows'])->filter(fn ($r) => $r['updates'])->map(fn ($r) => ['staff_id' => $r['id'], 'fields' => array_keys($r['updates'])])->all()]);
        $request->session()->forget('hr_staff_import');

        return redirect()->route('portal.hr.staff.index')->with('status', "Updated {$count} staff record(s). No new accounts were created.");
    }
}
