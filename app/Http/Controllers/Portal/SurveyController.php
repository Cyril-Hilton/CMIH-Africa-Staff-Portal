<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\SurveyBroadcastMail;
use App\Models\Survey;
use App\Models\Event;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SurveyController extends Controller
{
    public function index(): View
    {
        $surveys = Survey::with('event')
            ->withCount('responses')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('portal.surveys.index', compact('surveys'));
    }

    public function create(): View
    {
        $events = Event::where('status', 'published')->orderByDesc('starts_at')->get();
        return view('portal.surveys.create', compact('events'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'success_message'    => ['nullable', 'string', 'max:5000'],
            'event_id'           => ['nullable', 'exists:events,id'],
            'is_anonymous'       => ['nullable', 'boolean'],
            'status'             => ['required', 'string', Rule::in(['draft', 'published', 'closed'])],
            'client_brand_name'  => ['nullable', 'string', 'max:150'],
            'client_brand_name_2'=> ['nullable', 'string', 'max:150'],
            'cmih_logo'          => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'client_logo'        => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'client_logo_2'      => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'location_enabled'   => ['nullable', 'boolean'],
            'location_url'       => ['nullable', 'string', 'max:1000'],
            'location_label'     => ['nullable', 'string', 'max:255'],
            'questions'          => ['nullable', 'array'],
            'questions.*.question_text' => ['required', 'string', 'max:255'],
            'questions.*.question_type' => ['required', 'string', Rule::in(['short_text', 'paragraph', 'radio', 'checkbox', 'dropdown'])],
            'questions.*.options'       => ['nullable', 'array'],
            'questions.*.is_required'   => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($validated['title']) . '-' . Str::lower(Str::random(5));

        // Ensure unique slug
        while (Survey::where('slug', $slug)->exists()) {
            $slug = Str::slug($validated['title']) . '-' . Str::lower(Str::random(5));
        }

        // Handle logo uploads
        $cmihLogoPath    = null;
        $clientLogoPath  = null;
        $clientLogoPath2 = null;
        if ($request->hasFile('cmih_logo')) {
            $cmihLogoPath = $request->file('cmih_logo')->store('surveys/logos', 'public');
        }
        if ($request->hasFile('client_logo')) {
            $clientLogoPath = $request->file('client_logo')->store('surveys/logos', 'public');
        }
        if ($request->hasFile('client_logo_2')) {
            $clientLogoPath2 = $request->file('client_logo_2')->store('surveys/logos', 'public');
        }

        $survey = Survey::create([
            'created_by'          => auth()->id(),
            'event_id'            => $validated['event_id'] ?? null,
            'title'               => $validated['title'],
            'slug'                => $slug,
            'description'         => $validated['description'] ?? null,
            'success_message'     => $validated['success_message'] ?? null,
            'is_anonymous'        => $request->boolean('is_anonymous'),
            'status'              => $validated['status'],
            'client_brand_name'   => $validated['client_brand_name'] ?? null,
            'client_brand_name_2' => $validated['client_brand_name_2'] ?? null,
            'cmih_logo_path'      => $cmihLogoPath,
            'client_logo_path'    => $clientLogoPath,
            'client_logo_path_2'  => $clientLogoPath2,
            'location_enabled'    => $request->boolean('location_enabled'),
            'location_url'        => $validated['location_url'] ?? null,
            'location_label'      => $validated['location_label'] ?? null,
        ]);

        if (!empty($validated['questions'])) {
            foreach ($validated['questions'] as $index => $q) {
                $options = null;
                if (in_array($q['question_type'], ['radio', 'checkbox', 'dropdown']) && !empty($q['options'])) {
                    // Filter out empty options
                    $options = array_values(array_filter(array_map('trim', $q['options'])));
                }

                $survey->questions()->create([
                    'question_text' => $q['question_text'],
                    'question_type' => $q['question_type'],
                    'options' => $options,
                    'is_required' => !empty($q['is_required']),
                    'order' => $index,
                ]);
            }
        }

        // If survey is linked to an event, we can update event's registration_url automatically
        if ($survey->event_id && $survey->status === 'published') {
            $survey->event->update([
                'registration_url' => route('surveys.show', $survey->slug)
            ]);
        }

        return redirect()->route('portal.surveys.index')->with('status', 'Survey created successfully!');
    }

    public function show(Survey $survey): View
    {
        $survey->load(['questions', 'responses', 'event']);

        // Calculate Stats
        $stats = [];
        $totalResponses = $survey->responses->count();

        // 1. Demographics (only if not anonymous)
        if (!$survey->is_anonymous) {
            $genders = ['Male' => 0, 'Female' => 0, 'Other' => 0, 'Prefer not to say' => 0];
            $ages = ['Under 18' => 0, '18-24' => 0, '25-34' => 0, '35-44' => 0, '45+' => 0];

            foreach ($survey->responses as $r) {
                if ($r->gender) {
                    $normGender = ucwords(strtolower(trim($r->gender)));
                    if (array_key_exists($normGender, $genders)) {
                        $genders[$normGender]++;
                    } else {
                        $genders['Other']++;
                    }
                }
                if ($r->age) {
                    $age = intval($r->age);
                    if ($age < 18) $ages['Under 18']++;
                    elseif ($age <= 24) $ages['18-24']++;
                    elseif ($age <= 34) $ages['25-34']++;
                    elseif ($age <= 44) $ages['35-44']++;
                    else $ages['45+']++;
                }
            }
            $stats['genders'] = $genders;
            $stats['ages'] = $ages;
        }

        // 2. Custom choice question stats
        $questionStats = [];
        foreach ($survey->questions as $q) {
            if (in_array($q->question_type, ['radio', 'checkbox', 'dropdown'])) {
                $optionsMap = [];
                if (!empty($q->options)) {
                    foreach ($q->options as $opt) {
                        $optionsMap[$opt] = 0;
                    }
                }

                foreach ($survey->responses as $r) {
                    $ans = $r->answers[$q->id] ?? null;
                    if ($ans) {
                        if (is_array($ans)) {
                            // Checkbox outputs array of strings
                            foreach ($ans as $subAns) {
                                if (isset($optionsMap[$subAns])) {
                                    $optionsMap[$subAns]++;
                                } else {
                                    $optionsMap[$subAns] = 1;
                                }
                            }
                        } else {
                            if (isset($optionsMap[$ans])) {
                                $optionsMap[$ans]++;
                            } else {
                                $optionsMap[$ans] = 1;
                            }
                        }
                    }
                }
                $questionStats[$q->id] = $optionsMap;
            }
        }
        $stats['questions'] = $questionStats;

        return view('portal.surveys.show', compact('survey', 'stats', 'totalResponses'));
    }

    public function edit(Survey $survey): View
    {
        $survey->load('questions');
        $events = Event::where('status', 'published')->orderByDesc('starts_at')->get();
        return view('portal.surveys.edit', compact('survey', 'events'));
    }

    public function update(Request $request, Survey $survey): RedirectResponse
    {
        $validated = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'success_message'     => ['nullable', 'string', 'max:5000'],
            'event_id'            => ['nullable', 'exists:events,id'],
            'is_anonymous'        => ['nullable', 'boolean'],
            'status'              => ['required', 'string', Rule::in(['draft', 'published', 'closed'])],
            'client_brand_name'   => ['nullable', 'string', 'max:150'],
            'client_brand_name_2' => ['nullable', 'string', 'max:150'],
            'cmih_logo'           => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'client_logo'         => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'client_logo_2'       => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_cmih_logo'    => ['nullable', 'boolean'],
            'remove_client_logo'  => ['nullable', 'boolean'],
            'remove_client_logo_2'=> ['nullable', 'boolean'],
            'location_enabled'    => ['nullable', 'boolean'],
            'location_url'        => ['nullable', 'string', 'max:1000'],
            'location_label'      => ['nullable', 'string', 'max:255'],
            'questions'           => ['nullable', 'array'],
            'questions.*.id'      => ['nullable', 'integer'],
            'questions.*.question_text' => ['required', 'string', 'max:255'],
            'questions.*.question_type' => ['required', 'string', Rule::in(['short_text', 'paragraph', 'radio', 'checkbox', 'dropdown'])],
            'questions.*.options'       => ['nullable', 'array'],
            'questions.*.is_required'   => ['nullable', 'boolean'],
        ]);

        // Handle logo uploads / removals
        $cmihLogoPath    = $survey->cmih_logo_path;
        $clientLogoPath  = $survey->client_logo_path;
        $clientLogoPath2 = $survey->client_logo_path_2;

        if ($request->boolean('remove_cmih_logo')) {
            if ($cmihLogoPath) \Storage::disk('public')->delete($cmihLogoPath);
            $cmihLogoPath = null;
        } elseif ($request->hasFile('cmih_logo')) {
            if ($cmihLogoPath) \Storage::disk('public')->delete($cmihLogoPath);
            $cmihLogoPath = $request->file('cmih_logo')->store('surveys/logos', 'public');
        }

        if ($request->boolean('remove_client_logo')) {
            if ($clientLogoPath) \Storage::disk('public')->delete($clientLogoPath);
            $clientLogoPath = null;
        } elseif ($request->hasFile('client_logo')) {
            if ($clientLogoPath) \Storage::disk('public')->delete($clientLogoPath);
            $clientLogoPath = $request->file('client_logo')->store('surveys/logos', 'public');
        }

        if ($request->boolean('remove_client_logo_2')) {
            if ($clientLogoPath2) \Storage::disk('public')->delete($clientLogoPath2);
            $clientLogoPath2 = null;
        } elseif ($request->hasFile('client_logo_2')) {
            if ($clientLogoPath2) \Storage::disk('public')->delete($clientLogoPath2);
            $clientLogoPath2 = $request->file('client_logo_2')->store('surveys/logos', 'public');
        }

        $survey->update([
            'event_id'            => $validated['event_id'] ?? null,
            'title'               => $validated['title'],
            'description'         => $validated['description'] ?? null,
            'success_message'     => $validated['success_message'] ?? null,
            'is_anonymous'        => $request->boolean('is_anonymous'),
            'status'              => $validated['status'],
            'client_brand_name'   => $validated['client_brand_name'] ?? null,
            'client_brand_name_2' => $validated['client_brand_name_2'] ?? null,
            'cmih_logo_path'      => $cmihLogoPath,
            'client_logo_path'    => $clientLogoPath,
            'client_logo_path_2'  => $clientLogoPath2,
            'location_enabled'    => $request->boolean('location_enabled'),
            'location_url'        => $validated['location_url'] ?? null,
            'location_label'      => $validated['location_label'] ?? null,
        ]);

        $incomingIds = [];

        if (!empty($validated['questions'])) {
            foreach ($validated['questions'] as $index => $q) {
                $options = null;
                if (in_array($q['question_type'], ['radio', 'checkbox', 'dropdown']) && !empty($q['options'])) {
                    $options = array_values(array_filter(array_map('trim', $q['options'])));
                }

                $qData = [
                    'question_text' => $q['question_text'],
                    'question_type' => $q['question_type'],
                    'options' => $options,
                    'is_required' => !empty($q['is_required']),
                    'order' => $index,
                ];

                if (!empty($q['id'])) {
                    $question = $survey->questions()->where('id', $q['id'])->first();
                    if ($question) {
                        $question->update($qData);
                        $incomingIds[] = $question->id;
                    }
                } else {
                    $newQ = $survey->questions()->create($qData);
                    $incomingIds[] = $newQ->id;
                }
            }
        }

        // Delete questions that were removed in the builder
        $survey->questions()->whereNotIn('id', $incomingIds)->delete();

        // Update event registration URL if linked
        if ($survey->event_id && $survey->status === 'published') {
            $survey->event->update([
                'registration_url' => route('surveys.show', $survey->slug)
            ]);
        }

        return redirect()->route('portal.surveys.index')->with('status', 'Survey updated successfully!');
    }

    public function destroy(Survey $survey): RedirectResponse
    {
        $survey->delete();
        return redirect()->route('portal.surveys.index')->with('status', 'Survey deleted successfully.');
    }

    public function export(Survey $survey): StreamedResponse
    {
        $survey->load(['questions', 'responses']);

        $headers = [];
        if (!$survey->is_anonymous) {
            $headers = ['Name', 'Email', 'Phone', 'Age', 'Gender'];
        }
        
        $questions = $survey->questions;
        foreach ($questions as $q) {
            $headers[] = $q->question_text;
        }
        $headers[] = 'IP Address';
        $headers[] = 'Submitted At';

        $fileName = 'survey_responses_' . Str::slug($survey->title) . '_' . date('Y-m-d_H-i-s') . '.csv';

        $response = new StreamedResponse(function () use ($survey, $questions, $headers) {
            $handle = fopen('php://output', 'w');

            // Write BOM for Excel UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write CSV headers
            fputcsv($handle, $headers);

            // Write CSV rows
            foreach ($survey->responses as $record) {
                $row = [];
                if (!$survey->is_anonymous) {
                    $row[] = $record->name;
                    $row[] = $record->email;
                    $row[] = $record->phone;
                    $row[] = $record->age;
                    $row[] = $record->gender;
                }

                foreach ($questions as $q) {
                    $ans = $record->answers[$q->id] ?? '';
                    if (is_array($ans)) {
                        $row[] = implode(', ', $ans);
                    } else {
                        $row[] = $ans;
                    }
                }
                $row[] = $record->ip_address;
                $row[] = $record->created_at->format('Y-m-d H:i:s');
                fputcsv($handle, $row);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    // ── BROADCAST ──────────────────────────────────────────────────────────────

    public function broadcastCompose(Survey $survey): View
    {
        $survey->load('responses');

        // Only non-anonymous surveys have emails to send to
        $recipientCount = $survey->is_anonymous
            ? 0
            : $survey->responses->whereNotNull('email')->count();

        return view('portal.surveys.broadcast', compact('survey', 'recipientCount'));
    }

    public function broadcastSend(Request $request, Survey $survey): RedirectResponse
    {
        if ($survey->is_anonymous) {
            return back()->with('error', 'Cannot send emails to an anonymous survey — no email addresses were collected.');
        }

        $validated = $request->validate([
            'subject'        => ['required', 'string', 'max:255'],
            'body'           => ['required', 'string', 'max:10000'],
            'event_date'     => ['nullable', 'string', 'max:100'],
            'event_time'     => ['nullable', 'string', 'max:100'],
            'event_location' => ['nullable', 'string', 'max:255'],
            'event_map_url'  => ['nullable', 'string', 'max:1000'],
            'recipient_ids'  => ['nullable', 'array'],
            'recipient_ids.*'=> ['integer', 'exists:survey_responses,id'],
        ]);

        $survey->load('responses');

        // Use selected recipients, or ALL if none specifically chosen
        $responses = !empty($validated['recipient_ids'])
            ? $survey->responses->whereIn('id', $validated['recipient_ids'])
            : $survey->responses->whereNotNull('email')->where('email', '!=', '');

        $sent    = 0;
        $skipped = 0;
        $senderName = auth()->user()->name;

        foreach ($responses as $response) {
            if (!$response->email) { $skipped++; continue; }

            // Personalise body — replace {name} placeholder
            $personalBody = str_replace(
                ['{name}', '{Name}', '{NAME}'],
                $response->name ?? 'there',
                $validated['body']
            );

            try {
                Mail::to($response->email)->send(new SurveyBroadcastMail(
                    recipientName:  $response->name ?? 'Valued Respondent',
                    subject:        $validated['subject'],
                    body:           $personalBody,
                    eventDate:      $validated['event_date'] ?? null,
                    eventTime:      $validated['event_time'] ?? null,
                    eventLocation:  $validated['event_location'] ?? null,
                    eventMapUrl:    $validated['event_map_url'] ?? null,
                    surveyTitle:    $survey->title,
                    senderName:     $senderName,
                ));
                $sent++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        $msg = "✅ Broadcast sent! {$sent} email(s) delivered successfully.";
        if ($skipped > 0) $msg .= " {$skipped} skipped (no email address or delivery error).";

        return redirect()->route('portal.surveys.show', $survey)->with('status', $msg);
    }
}
