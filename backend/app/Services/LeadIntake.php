<?php

namespace App\Services;

use App\Models\Business\Branch;
use App\Models\Business\Lead;
use App\Models\Business\LeadActivity;
use App\Models\Inquiry;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Turns a website quote/contact request into a CRM lead.
 * If an open lead with the same phone already exists, the request is added to
 * its timeline instead of creating a duplicate. Never breaks the website form.
 */
class LeadIntake
{
    public static function fromInquiry(Inquiry $inquiry): ?Lead
    {
        try {
            $branchId = self::defaultBranchId();
            if (! $branchId) {
                return null;
            }

            $summary = collect([
                'Service' => $inquiry->service, 'Property' => $inquiry->property_type, 'Area' => $inquiry->area_size,
                'Budget' => $inquiry->budget, 'Location' => $inquiry->location,
            ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ');
            $note = trim(($summary ? $summary."\n" : '').(string) $inquiry->message);

            $existing = $inquiry->phone
                ? Lead::withoutGlobalScopes()->where('phone', $inquiry->phone)->whereNotIn('stage', ['won', 'lost'])->latest('id')->first()
                : null;

            if ($existing) {
                LeadActivity::create([
                    'lead_id' => $existing->id, 'type' => 'note',
                    'note' => "New website {$inquiry->type} request (#{$inquiry->id}).".($note ? "\n".$note : ''),
                ]);

                return $existing;
            }

            $lead = Lead::create([
                'branch_id' => $branchId,
                'name' => $inquiry->name,
                'phone' => $inquiry->phone,
                'email' => $inquiry->email,
                'address' => $inquiry->location,
                'source' => 'website',
                'stage' => 'new',
                'service' => $inquiry->service,
                'property_type' => $inquiry->property_type,
                'area_size' => $inquiry->area_size,
                'budget' => $inquiry->budget,
                'requirements' => $inquiry->message,
                'inquiry_id' => $inquiry->id,
            ]);
            LeadActivity::create(['lead_id' => $lead->id, 'type' => 'note', 'note' => 'Lead created from website '.$inquiry->type.' form.'.($note ? "\n".$note : '')]);

            return $lead;
        } catch (\Throwable $e) {
            Log::error('Lead intake failed: '.$e->getMessage());

            return null;
        }
    }

    /** Branch that receives website leads: Business Settings, else the first active branch. */
    public static function defaultBranchId(): ?int
    {
        $id = (int) (Setting::where('key', 'biz_lead_branch_id')->value('value') ?: 0);
        if ($id && Branch::whereKey($id)->where('is_active', true)->exists()) {
            return $id;
        }

        return Branch::where('is_active', true)->orderBy('id')->value('id');
    }
}
