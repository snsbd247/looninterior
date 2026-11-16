<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\TaxRate;
use App\Models\Setting;
use App\Services\CompanyProfile;
use App\Services\DocumentNumber;
use App\Services\Pdf;
use App\Support\ActivityLogger;
use App\Support\BranchContext;
use App\Support\Uploader;
use Illuminate\Http\Request;

/** Company details, bank, terms, signature/seal and numbering used on business documents. */
class BusinessSettingController extends Controller
{
    /** Text keys (all stored with the biz_ prefix). */
    private const TEXT = [
        'company_name' => 'nullable|string|max:255',
        'company_name_bn' => 'nullable|string|max:255',
        'address' => 'nullable|string|max:1000',
        'address_bn' => 'nullable|string|max:1000',
        'phone' => 'nullable|string|max:100',
        'email' => 'nullable|email|max:255',
        'website' => 'nullable|string|max:255',
        'bin' => 'nullable|string|max:50',
        'tin' => 'nullable|string|max:50',
        'bank_details' => 'nullable|string|max:2000',
        'bank_details_bn' => 'nullable|string|max:2000',
        'terms' => 'nullable|string|max:5000',
        'terms_bn' => 'nullable|string|max:5000',
        'footer' => 'nullable|string|max:255',
        'footer_bn' => 'nullable|string|max:255',
        'signatory_name' => 'nullable|string|max:255',
        'signatory_name_bn' => 'nullable|string|max:255',
        'signatory_title' => 'nullable|string|max:255',
        'signatory_title_bn' => 'nullable|string|max:255',
        'number_format' => ['nullable', 'string', 'max:60', 'regex:/\{SEQ\}/'],
        'number_padding' => 'nullable|integer|min:1|max:10',
        'bn_digits' => 'nullable|in:0,1',
    ];

    private const IMAGES = ['logo', 'signature', 'seal'];

    public function index()
    {
        return response()->json([
            'data' => Setting::businessArray(),
            'number_preview' => $this->numberPreview(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (self::TEXT as $key => $rule) {
            $rules["biz_{$key}"] = $rule;
        }
        foreach (self::IMAGES as $key) {
            $rules["biz_{$key}"] = 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096';
        }
        $data = $request->validate($rules, [
            'biz_number_format.regex' => 'The number format must contain {SEQ}.',
        ]);

        $current = Setting::businessArray();
        $changed = [];

        foreach (array_keys(self::TEXT) as $key) {
            $k = "biz_{$key}";
            if (! $request->has($k)) {
                continue;
            }
            $value = $data[$k] ?? null;
            if ((string) ($current[$k] ?? '') !== (string) ($value ?? '')) {
                $changed[] = $k;
            }
            Setting::updateOrCreate(['key' => $k], ['value' => $value]);
        }

        foreach (self::IMAGES as $key) {
            $k = "biz_{$key}";
            if ($request->hasFile($k)) {
                Uploader::delete($current[$k] ?? null);
                Setting::updateOrCreate(['key' => $k], ['value' => Uploader::store($request->file($k), 'business')]);
                $changed[] = $k;
            } elseif ($request->boolean("remove_{$k}")) {
                Uploader::delete($current[$k] ?? null);
                Setting::updateOrCreate(['key' => $k], ['value' => null]);
                $changed[] = $k;
            }
        }

        if ($changed) {
            ActivityLogger::record('updated', null, 'Updated business settings: '.implode(', ', $changed));
        }

        return response()->json([
            'message' => 'Business settings saved',
            'data' => Setting::businessArray(),
            'number_preview' => $this->numberPreview(),
        ]);
    }

    /** A sample document to check company details, logo, signature, language and numbering. */
    public function samplePdf(Request $request)
    {
        $lang = $request->query('lang') === 'bn' ? 'bn' : 'en';
        $bn = $lang === 'bn';
        $company = CompanyProfile::get($lang);
        $tax = TaxRate::where('is_default', true)->where('is_active', true)->first();

        $sections = $bn ? [
            ['name' => 'লিভিং রুম', 'items' => [
                ['description' => 'জিপসাম বোর্ড ফলস সিলিং, লাইটিং সহ', 'unit' => 'বর্গফুট', 'qty' => 320, 'rate' => 180],
                ['description' => 'টিভি ওয়াল ক্যাবিনেট (ল্যামিনেটেড বোর্ড)', 'unit' => 'রানিং ফুট', 'qty' => 12, 'rate' => 4500],
            ]],
            ['name' => 'কিচেন', 'items' => [
                ['description' => 'কিচেন ক্যাবিনেট (উপরে ও নিচে), হার্ডওয়্যার সহ', 'unit' => 'রানিং ফুট', 'qty' => 18, 'rate' => 7500],
            ]],
        ] : [
            ['name' => 'Living Room', 'items' => [
                ['description' => 'Gypsum board false ceiling with lighting', 'unit' => 'sqft', 'qty' => 320, 'rate' => 180],
                ['description' => 'TV wall cabinet (laminated board)', 'unit' => 'rft', 'qty' => 12, 'rate' => 4500],
            ]],
            ['name' => 'Kitchen', 'items' => [
                ['description' => 'Kitchen cabinet (upper & lower) with hardware', 'unit' => 'rft', 'qty' => 18, 'rate' => 7500],
            ]],
        ];

        $subtotal = 0;
        foreach ($sections as &$section) {
            foreach ($section['items'] as &$item) {
                $item['amount'] = round($item['qty'] * $item['rate'], 2);
                $subtotal += $item['amount'];
            }
        }
        unset($section, $item);

        $discount = 5000;
        $taxAmount = $tax ? round(($subtotal - $discount) * (float) $tax->rate / 100, 2) : 0;
        $rateText = $tax ? rtrim(rtrim(number_format((float) $tax->rate, 3, '.', ''), '0'), '.') : '';

        $doc = [
            'title' => __('doc.quotation', [], $lang),
            'number' => $this->numberPreview('QT'),
            'date' => now()->format('d/m/Y'),
            'meta' => [
                __('doc.valid_until', [], $lang) => now()->addDays(15)->format('d/m/Y'),
            ],
            'party_label' => __('doc.bill_to', [], $lang),
            'party' => $bn
                ? ['জনাব রহিম উদ্দিন', 'বাড়ি ১২, রোড ৫, গুলশান-১, ঢাকা', 'ফোন: ০১৭১১-০০০০০০']
                : ['Mr. Rahim Uddin', 'House 12, Road 5, Gulshan-1, Dhaka', 'Phone: 01711-000000'],
            'sections' => $sections,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax_label' => $tax ? "{$tax->name} ({$rateText}%)" : '',
            'tax' => $taxAmount,
            'total' => $subtotal - $discount + $taxAmount,
            'notes' => __('doc.sample_note', [], $lang),
            'watermark' => __('doc.sample', [], $lang),
        ];

        $pdf = Pdf::render('pdf.document', ['company' => $company, 'doc' => $doc], $lang, $doc['title']);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="sample-quotation-'.$lang.'.pdf"',
        ]);
    }

    private function numberPreview(string $type = 'INV'): string
    {
        $branch = BranchContext::current() ?? (BranchContext::allowed()[0] ?? null);

        return $branch ? DocumentNumber::preview($type, $branch) : '';
    }
}
