<?php

namespace App\Services;

use App\Models\Business\Quotation;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Builds the $doc arrays for resources/views/pdf/document.blade.php and sends
 * documents by e-mail. One place per document type, so PDF, e-mail and the
 * public share link always show the same thing.
 */
class Documents
{
    public static function d(?\DateTimeInterface $date): string
    {
        return $date ? $date->format('d/m/Y') : '';
    }

    private static function party($customer, string $lang): array
    {
        if (! $customer) {
            return [];
        }

        return array_values(array_filter([
            $customer->name,
            $customer->company,
            $customer->address,
            $customer->phone ? __('doc.phone', [], $lang).': '.$customer->phone : null,
            $customer->email,
        ]));
    }

    public static function quotation(Quotation $q, string $lang): array
    {
        $q->loadMissing(['items', 'customer']);
        $meta = [];
        if ($q->valid_until) {
            $meta[__('doc.valid_until', [], $lang)] = self::d($q->valid_until);
        }
        if ($q->title) {
            $meta[__('doc.project', [], $lang)] = $q->title;
        }

        return [
            'title' => __('doc.quotation', [], $lang),
            'number' => $q->number,
            'date' => self::d($q->date),
            'meta' => $meta,
            'party_label' => __('doc.bill_to', [], $lang),
            'party' => self::party($q->customer, $lang),
            'sections' => DocumentTotals::sections($q->items),
            'subtotal' => $q->subtotal,
            'discount' => $q->discount,
            'tax_label' => DocumentTotals::taxLabel($q->tax_name, $q->tax_rate),
            'tax' => $q->tax,
            'total' => $q->total,
            'notes' => $q->notes,
            'terms' => $q->terms,
            'watermark' => in_array($q->status, ['cancelled', 'revised'], true) ? strtoupper($q->status) : null,
        ];
    }

    public static function invoice(\App\Models\Business\Invoice $inv, string $lang): array
    {
        $inv->loadMissing(['items', 'customer', 'project:id,code,title']);
        $meta = [];
        if ($inv->due_date) {
            $meta[__('doc.due_date', [], $lang)] = self::d($inv->due_date);
        }
        if ($inv->project) {
            $meta[__('doc.project', [], $lang)] = $inv->project->code;
        }
        $doc = [
            'title' => __('doc.invoice', [], $lang),
            'number' => $inv->number,
            'date' => self::d($inv->date),
            'meta' => $meta,
            'party_label' => __('doc.bill_to', [], $lang),
            'party' => self::party($inv->customer, $lang),
            'sections' => DocumentTotals::sections($inv->items),
            'subtotal' => $inv->subtotal,
            'discount' => $inv->discount,
            'tax_label' => DocumentTotals::taxLabel($inv->tax_name, $inv->tax_rate),
            'tax' => $inv->tax,
            'total' => $inv->total,
            'notes' => $inv->notes,
            'terms' => $inv->terms,
            'watermark' => match ($inv->status) { 'cancelled' => 'CANCELLED', 'draft' => 'DRAFT', 'paid' => 'PAID', default => null },
        ];
        if ($inv->status !== 'draft') {
            $doc['paid'] = (float) $inv->paid + (float) $inv->credited;
            $doc['due'] = $inv->due;
        }

        return $doc;
    }

    public static function receipt(\App\Models\Business\Payment $p, string $lang): array
    {
        $p->loadMissing(['invoice', 'customer', 'account:id,name']);
        $method = strtoupper($p->method).($p->reference ? ' · '.$p->reference : '');
        $line = $lang === 'bn'
            ? "ইনভয়েস {$p->invoice->number} এর বিপরীতে পরিশোধ ({$method})"
            : "Payment received against invoice {$p->invoice->number} ({$method})";

        return [
            'title' => __('doc.receipt', [], $lang),
            'number' => $p->number,
            'date' => self::d($p->date),
            'meta' => [__('doc.invoice', [], $lang) => $p->invoice->number],
            'party_label' => $lang === 'bn' ? 'গ্রহণ করা হয়েছে' : 'Received from',
            'party' => self::party($p->customer, $lang),
            'sections' => [['name' => null, 'items' => [['description' => $line, 'unit' => '', 'qty' => 1, 'rate' => $p->amount, 'amount' => $p->amount]]]],
            'subtotal' => $p->amount,
            'discount' => 0,
            'tax_label' => '',
            'tax' => 0,
            'total' => $p->amount,
            'notes' => trim(($lang === 'bn' ? 'ইনভয়েসের বকেয়া: ' : 'Invoice balance due: ').Money::format($p->invoice->due, $lang).($p->notes ? "\n".$p->notes : '')),
            'watermark' => $p->status === 'cancelled' ? 'CANCELLED' : null,
            'hide_terms' => true,
        ];
    }

    /** Render a document PDF. $doc['terms'] (document-specific) overrides company terms. */
    public static function pdf(array $doc, string $lang): string
    {
        $company = CompanyProfile::get($lang);
        if (! empty($doc['terms'])) {
            $company['terms'] = $doc['terms'];
        }
        if (! empty($doc['hide_terms'])) {
            $company['terms'] = null;
            $company['bank'] = null;
        }

        return Pdf::render('pdf.document', ['company' => $company, 'doc' => $doc], $lang, $doc['title'].' '.$doc['number']);
    }

    /** E-mail a document PDF to a customer/supplier. Throws on mail failure (caller shows the error). */
    public static function email(Model $subject, string $to, array $doc, string $lang, string $message = ''): void
    {
        $pdf = self::pdf($doc, $lang);
        $company = CompanyProfile::get($lang);
        $file = preg_replace('/[^A-Za-z0-9\-]+/', '-', $doc['number']).'.pdf';

        Mail::send('emails.document', [
            'site' => $company['name'],
            'title' => $doc['title'],
            'number' => $doc['number'],
            'total' => Money::format($doc['total'], 'en', true),
            'body' => $message,
        ], function (Message $m) use ($to, $doc, $company, $pdf, $file) {
            $m->to($to)->subject("{$doc['title']} {$doc['number']} | {$company['name']}")
                ->attachData($pdf, $file, ['mime' => 'application/pdf']);
            if ($company['email']) {
                $m->replyTo($company['email'], $company['name']);
            }
        });

        ActivityLogger::record('emailed', $subject, "E-mailed {$doc['title']} {$doc['number']} to {$to}");
    }
}
