<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(): View
    {
        $content = SiteContent::all()->keyBy('key');

        $fields = [
            'home.hero_headline' => 'Home: Hero Headline',
            'home.hero_subheadline' => 'Home: Hero Sub-headline',
            'home.advantage_title' => 'Home: Advantage Title',
            'home.advantage' => 'Home: Advantage Copy',
            'home.advantage_cta_label' => 'Home: Advantage Button Label',
            'home.brands_list' => 'Home: Brands List (one per line)',
            'home.ratings_headline' => 'Home: Ratings Headline',
            'home.ratings_subheadline' => 'Home: Ratings Subheadline',
            'home.ratings_score' => 'Home: Ratings Score',
            'home.ratings_count' => 'Home: Ratings Count',
            'about.story' => 'About: Story',
            'about.mission' => 'About: Mission',
            'about.vision' => 'About: Vision',
            'about.cvo_name' => 'About: CVO Name',
            'about.cvo_title' => 'About: CVO Title',
            'about.cvo_bio' => 'About: CVO Bio',
            'about.cvo_quote' => 'About: CVO Quote',
            'contact.address' => 'Contact: Address',
            'contact.phone' => 'Contact: Phone',
            'contact.email' => 'Contact: Email',
            'contact.nigeria_name' => 'Contact: Nigeria Office Name',
            'contact.nigeria_address' => 'Contact: Nigeria Address',
            'contact.nigeria_phone' => 'Contact: Nigeria Phone',
            
            // New Editable Sections
            'services.list' => 'Services Page: List (Format: Title | Detail)',
            'home.reviews' => 'Home: Reviews (Format: Quote | Author | Company)',
            'home.metrics' => 'Home: Metrics (Format: Label | Value | Suffix | Detail)',
            'home.advantage_1_title' => 'Home: Advantage 1 Title',
            'home.advantage_1_text'  => 'Home: Advantage 1 Text',
            'home.advantage_2_title' => 'Home: Advantage 2 Title',
            'home.advantage_2_text'  => 'Home: Advantage 2 Text',
            'home.advantage_3_title' => 'Home: Advantage 3 Title',
            'home.advantage_3_text'  => 'Home: Advantage 3 Text',
            'home.advantage_4_title' => 'Home: Advantage 4 Title',
            'home.advantage_4_text'  => 'Home: Advantage 4 Text',
        ];

        $imageFields = [
            'home.hero_image' => 'Home: Hero Image',
            'home.hero_image_dark' => 'Home: Hero Image (Dark Mode)',
            'home.advantage_image' => 'Home: Advantage Image',
            'home.advantage_image_dark' => 'Home: Advantage Image (Dark Mode)',
            'about.cover_image' => 'About: Cover Image',
            'about.cover_image_dark' => 'About: Cover Image (Dark Mode)',
            'about.cvo_image' => 'About: CVO Image',
            'about.cvo_image_dark' => 'About: CVO Image (Dark Mode)',
            'portfolio.cover_image' => 'Portfolio: Cover Image',
            'portfolio.cover_image_dark' => 'Portfolio: Cover Image (Dark Mode)',
            'logo_light' => 'Global: Logo (Light Mode - displayed on light backgrounds)',
            'logo_dark'  => 'Global: Logo (Dark Mode - displayed on dark backgrounds)',
        ];

        $defaults = [
            'home.hero_headline' => 'Unlocking the Power of the African Market.',
            'home.hero_subheadline' => 'Integrated marketing solutions that bridge the gap between global strategy and local impact. CMIH Africa: We Make It Happen.',
            'home.advantage_title' => 'The CMIH Advantage',
            'home.advantage' => 'A results-driven partner connecting strategy, execution, and measurable impact.',
            'home.advantage_cta_label' => 'About CMIH',
            'home.brands_list' => implode("\n", [
                'Global Beverage Co.',
                'Tech Nova',
                'Nile Finance',
                'Atlas Energy',
                'Urban Pulse',
                'Apex FMCG',
            ]),
            'home.ratings_headline' => 'Rated 4.9 by Enterprise Teams.',
            'home.ratings_subheadline' => 'Client reviews highlight precision execution, local intelligence, and measurable performance.',
            'home.ratings_score' => '4.9',
            'home.ratings_count' => '150+ reviews',
            'about.story' => 'CMIH Africa provides deep cultural intelligence, ensuring messages are never "lost in translation."',
            'about.mission' => 'Empower brands for sustainable growth in Africa.',
            'about.vision' => 'To be the most influential marketing catalyst for the African economic Renaissance.',
            'about.cvo_name' => 'Solomon Nanfa',
            'about.cvo_title' => 'Chief Visionary Officer (CVO)',
            'about.cvo_bio' => 'A pan-African marketing leader focused on building locally fluent strategies that deliver measurable impact across diverse markets.',
            'about.cvo_quote' => '“We build momentum where global ambition meets local insight.”',
            'contact.address' => 'No. 7 Afum Street, North Legon. Accra - Ghana',
            'contact.phone' => '+233 542204282',
            'contact.email' => 'info@cmihgh.com',
            'contact.nigeria_name' => 'CONCEPTS MAKE IT HAPPEN LTD, NIGERIA',
            'contact.nigeria_address' => '25, Ajanaku Street, Awuse Estates, Opebi Ikeja, Lagos, Nigeria.',
            'contact.nigeria_phone' => '+234 8065776473',
            'services.list' => implode("\n", [
                'Event Management|Turning vision into reality through flawless end-to-end execution.',
                'Online & Social Media Marketing|Driving engagement, conversation, and growth in the digital space.',
                'Management of Sponsored Events|Maximizing brand visibility through strategic event partnerships.',
                'POP Deployment & Activations|Creating high-impact Point of Purchase moments that drive action.',
                'Brand Management Channel|Orchestrating brand consistency across distribution channels.',
                'Instore & Shopper Marketing|Influencing the moment of truth at the point of purchase.',
                'Commercial Supply Chains Solutions|Seamless logistics and optimization for marketing collateral.',
                'Campus Activations|Connecting with the youth demographic through authentic experiences.',
                'Road Shows|Bringing the brand directly to regions with mobile experiences.',
                'Town Storming|Hyper-local, high-intensity community engagement to dominate markets.',
                'Street Level Promotion|Guerrilla-style marketing that captures attention in the urban landscape.',
            ]),
            'home.reviews' => implode("\n", [
                'CMIH delivered our pan-African launch with flawless execution and local relevance.|Marketing Director|Global Beverage Co.',
                'Their field teams moved with speed, and the reporting kept every stakeholder aligned.|Head of Brand|Urban Pulse',
                'Strategic, responsive, and results driven. We saw measurable uplift across markets.|CMO|Tech Nova',
            ]),
            'home.metrics' => implode("\n", [
                'Satisfaction|96|%|Client satisfaction score',
                'On-Time Delivery|94|%|Campaigns delivered on schedule',
                'Engagement Lift|31|%|Average activation uplift',
            ]),
        ];

        return view('admin.content', compact('content', 'fields', 'imageFields', 'defaults'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:5000'],
            'type' => ['nullable', 'string', 'max:32'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,avif,bmp,gif,tif,tiff', 'max:4096'],
        ]);

        $type = $validated['type'] ?? 'text';
        $value = $validated['value'] ?? '';

        if ($type === 'image' && $request->hasFile('image')) {
            $path = $request->file('image')->store('content', 'public');

            $existing = SiteContent::where('key', $validated['key'])->first();
            if ($existing && $existing->value) {
                Storage::disk('public')->delete($existing->value);
            }

            $value = $path;
        } elseif ($type === 'image' && $value === '') {
            $existing = SiteContent::where('key', $validated['key'])->first();
            if ($existing && $existing->value) {
                Storage::disk('public')->delete($existing->value);
            }
        }

        SiteContent::updateOrCreate(
            ['key' => $validated['key']],
            [
                'value' => $value,
                'type' => $type,
                'updated_by' => $request->user()->id,
            ]
        );

        Cache::forget('site_content_all');

        return back()->with('status', 'Content updated.');
    }
}
