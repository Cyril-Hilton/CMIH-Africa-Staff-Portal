<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HrStaffRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class HrStaffController extends Controller
{
    private function authorizeHr(Request $request): void
    {
        abort_unless($request->user()?->hasFullHrAccess(), 403);
    }

    private function staffQuery(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        return User::internalStaff()->with('lineManager')->orderBy('name')->orderBy('id')
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('staff_id_number', 'like', '%'.$search.'%')))
            ->when($filters['department'] ?? null, fn ($q, $value) => $q->where('department', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value));
    }

    public function index(Request $request, HrStaffRecord $record)
    {
        $this->authorizeHr($request);

        return response()->view('portal.hr.staff-index', [
            'staff' => $this->staffQuery($request)->paginate(25)->withQueryString(),
            'departments' => User::internalStaff()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'statuses' => User::internalStaff()->distinct()->orderBy('status')->pluck('status'),
            'record' => $record,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, User $user, HrStaffRecord $record)
    {
        $this->authorizeHr($request);
        abort_unless(User::internalStaff()->whereKey($user->id)->exists(), 404);
        $data = $request->validate(['section' => ['nullable', Rule::in(array_keys(HrStaffRecord::HISTORIES))]]);
        $section = $data['section'] ?? 'leave';

        return response()->view('portal.hr.staff-show', [
            'staffMember' => $user->load('lineManager'), 'record' => $record, 'section' => $section,
            'history' => $record->history($user, $section)->paginate(15)->withQueryString(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function document(Request $request, User $user, string $field, HrStaffRecord $record)
    {
        $this->authorizeHr($request);
        abort_unless(User::internalStaff()->whereKey($user->id)->exists(), 404);
        $path = $record->document($user, $field);
        abort_unless($path, 404, 'This document is not available.');

        return response()->download($path, basename($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function export(Request $request, HrStaffRecord $record)
    {
        $this->authorizeHr($request);
        $data = $request->validate([
            'scope' => ['required', Rule::in(['selected', 'filtered', 'all'])],
            'ids' => ['required_if:scope,selected', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'format' => ['required', Rule::in(['csv', 'zip'])],
            'fields' => ['required_if:format,csv', 'array', 'min:1'],
            'fields.*' => ['required', 'string', 'distinct', Rule::in($record->fields())],
        ]);
        $query = $data['scope'] === 'filtered' ? $this->staffQuery($request) : User::internalStaff()->with('lineManager')->orderBy('name')->orderBy('id');
        if ($data['scope'] === 'selected') {
            $query->whereKey($data['ids']);
            if ((clone $query)->count() !== count($data['ids'])) {
                throw ValidationException::withMessages(['ids' => 'One or more selected staff records are unavailable. Refresh the register and select again.']);
            }
        }
        if (! (clone $query)->exists()) {
            throw ValidationException::withMessages(['scope' => 'No staff match this export.']);
        }
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($data['format'] === 'csv') {
            $fields = array_values(array_unique(['staff_id_number', 'name', ...$data['fields']]));

            return response()->streamDownload(function () use ($query, $fields, $record) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                $record->csvRow($handle, array_map($record->label(...), $fields));
                foreach ($query->lazy(100) as $user) {
                    $record->csvRow($handle, array_map(fn ($field) => $record->value($user, $field), $fields));
                }
                fclose($handle);
            }, 'staff-information-'.now()->format('Y-m-d').'.csv', $headers + ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        abort_unless(class_exists(ZipArchive::class), 503, 'Full record downloads require the PHP ZIP extension. CSV exports remain available.');
        $path = tempnam(sys_get_temp_dir(), 'cmih-hr-');
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create staff archive.');
            }
            $zip->addFromString('READ-ME.txt', 'CMIH staff records exported '.now()->toIso8601String()."\nEach staff folder contains a profile CSV, recorded HR and employment history CSVs, and available profile documents.\nEmpty history CSVs mean no records exist. Missing documents are listed in documents.csv.\nPasswords, authentication tokens and private messages are excluded.\nCSV values beginning with formula characters are prefixed with an apostrophe for spreadsheet safety.\n");
            foreach ($query->lazy(100) as $user) {
                $folder = 'staff-'.$user->id.'/';
                $this->addCsv($zip, $folder.'profile.csv', ['Field', 'Value'], (function () use ($record, $user) {
                    foreach ($record->fields() as $field) {
                        yield [$record->label($field), $record->value($user, $field)];
                    }
                })(), $record);
                foreach (HrStaffRecord::HISTORIES as $key => [$label]) {
                    $historyQuery = $record->history($user, $key);
                    $first = (clone $historyQuery)->first();
                    $this->addCsv($zip, $folder.$key.'.csv', $first ? array_map($record->label(...), array_keys((array) $first)) : ['No records'],
                        $historyQuery->cursor()->map(fn ($row) => array_values((array) $row)), $record);
                }
                $documents = [];
                foreach (HrStaffRecord::GROUPS['Documents'] as $field) {
                    $file = $record->document($user, $field);
                    $filename = $file ? 'documents/'.$field.'.'.(pathinfo($file, PATHINFO_EXTENSION) ?: 'bin') : '';
                    if ($file && ! $zip->addFile($file, $folder.$filename)) {
                        throw new \RuntimeException('Unable to include staff document.');
                    }
                    $documents[] = [$record->label($field), $file ? 'Included' : ($user->{$field} ? 'File missing' : 'Not supplied'), $filename];
                }
                $this->addCsv($zip, $folder.'documents.csv', ['Document', 'Status', 'File'], $documents, $record);
            }
            if (! $zip->close()) {
                throw new \RuntimeException('Unable to finalize staff archive.');
            }
        } catch (\Throwable $e) {
            if (is_file($path)) {
                unlink($path);
            }
            throw $e;
        }

        return response()->download($path, 'staff-records-'.now()->format('Y-m-d').'.zip', $headers)->deleteFileAfterSend(true);
    }

    private function addCsv(ZipArchive $zip, string $filename, array $headers, iterable $rows, HrStaffRecord $record): void
    {
        $handle = fopen('php://temp/maxmemory:2097152', 'w+');
        try {
            fwrite($handle, "\xEF\xBB\xBF");
            $record->csvRow($handle, $headers);
            foreach ($rows as $row) {
                $record->csvRow($handle, $row);
            }
            rewind($handle);
            if (! $zip->addFromString($filename, stream_get_contents($handle))) {
                throw new \RuntimeException('Unable to include staff record.');
            }
        } finally {
            fclose($handle);
        }
    }
}
