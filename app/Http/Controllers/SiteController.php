<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Brands\BrandsPlatformController;
use App\Models\Event;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(Request $request): View
    {
        if ($this->shouldShowBrandsPlatform($request)) {
            return app(BrandsPlatformController::class)->index($request);
        }

        $events = Event::where('status', 'published')
            ->whereDate('starts_at', '>=', now()->toDateString())
            ->orderBy('starts_at')
            ->take(3)
            ->get();

        $brands = \App\Models\Brand::all();

        return view('pages.home', compact('events', 'brands'));
    }

    private function shouldShowBrandsPlatform(Request $request): bool
    {
        if ((string) config('cmih.app_kind') === 'brands') {
            return true;
        }

        $brandsHost = parse_url((string) config('cmih.urls.brands'), PHP_URL_HOST);

        return $brandsHost && strtolower($request->getHost()) === strtolower($brandsHost);
    }

    public function news(): View
    {
        $events = Event::where('status', 'published')
            ->orderByDesc('starts_at')
            ->paginate(8);

        return view('pages.news', compact('events'));
    }

    public function showSurvey(Survey $survey): View
    {
        if ($survey->status === 'draft') {
            abort(404, 'This survey is not currently available.');
        }

        $survey->load(['questions', 'event']);

        return view('pages.surveys.show', compact('survey'));
    }

    public function submitSurvey(Request $request, Survey $survey)
    {
        if ($survey->status === 'closed') {
            return back()->withErrors(['survey' => 'This survey is now closed.']);
        }

        $survey->load('questions');

        $rules = [];
        $messages = [];

        if (!$survey->is_anonymous) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['email'] = ['required', 'email', 'max:255'];
            $rules['phone'] = ['required', 'string', 'max:50'];
            $rules['age'] = ['nullable', 'integer', 'min:1', 'max:120'];
            $rules['gender'] = ['nullable', 'string', 'max:50'];
        }

        foreach ($survey->questions as $q) {
            if ($q->is_required) {
                if ($q->question_type === 'checkbox') {
                    $rules['answers.' . $q->id] = ['required', 'array', 'min:1'];
                    $messages['answers.' . $q->id . '.required'] = 'Please check at least one option for: ' . $q->question_text;
                } else {
                    $rules['answers.' . $q->id] = ['required'];
                    $messages['answers.' . $q->id . '.required'] = 'This question is required: ' . $q->question_text;
                }
            }
        }

        $request->validate($rules, $messages);

        SurveyResponse::create([
            'survey_id' => $survey->id,
            'name' => !$survey->is_anonymous ? $request->input('name') : null,
            'email' => !$survey->is_anonymous ? $request->input('email') : null,
            'phone' => !$survey->is_anonymous ? $request->input('phone') : null,
            'age' => !$survey->is_anonymous ? $request->input('age') : null,
            'gender' => !$survey->is_anonymous ? $request->input('gender') : null,
            'answers' => $request->input('answers', []),
            'ip_address' => $request->ip(),
        ]);

        return view('pages.surveys.success', compact('survey'));
    }
}
