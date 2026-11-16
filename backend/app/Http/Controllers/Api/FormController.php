<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\Inquiry;
use App\Models\JobApplication;
use App\Services\LeadIntake;
use App\Support\Notifier;
use App\Support\SpamGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormController extends Controller
{
    /** Quote request / contact form submissions. */
    public function inquiry(Request $request)
    {
        $thanks = response()->json(['message' => 'Thank you! We will contact you shortly.'], 201);

        if (SpamGuard::isBot($request)) {
            return $thanks;
        }

        $data = $request->validate([
            'type' => 'nullable|in:quote,contact',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'service' => 'nullable|string|max:255',
            'property_type' => 'nullable|string|max:255',
            'area_size' => 'nullable|string|max:255',
            'budget' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:5000',
        ]);

        $data['type'] = $data['type'] ?? 'quote';
        $data['status'] = 'new';
        $data['ip'] = $request->ip();

        $inquiry = Inquiry::create($data);
        LeadIntake::fromInquiry($inquiry);
        Notifier::inquiry($inquiry);

        return $thanks;
    }

    public function apply(Request $request, string $slug)
    {
        $career = Career::active()->where('slug', $slug)->firstOrFail();
        $thanks = response()->json(['message' => 'Application submitted successfully. Good luck!'], 201);

        if (SpamGuard::isBot($request)) {
            return $thanks;
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'cover_letter' => 'nullable|string|max:5000',
            'cv' => 'required|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $file = $request->file('cv');
        $name = date('Ymd').'-'.Str::random(20).'.'.strtolower($file->guessExtension() ?: 'pdf');
        $file->move(storage_path('app/cvs'), $name);

        $application = JobApplication::create([
            'career_id' => $career->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'cover_letter' => $data['cover_letter'] ?? null,
            'cv_path' => 'cvs/'.$name,
            'status' => 'new',
        ]);
        Notifier::application($application, $career->title);

        return $thanks;
    }
}
