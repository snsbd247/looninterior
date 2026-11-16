{{--
  Generic business document (quotation / invoice / receipt / PO) for mPDF.
  $lang     'en' | 'bn'
  $company  App\Services\CompanyProfile::get($lang)
  $doc      [
    'title', 'number', 'date', 'meta' => [label => value],          header info on the right
    'party_label', 'party' => [lines…],                             customer / supplier block
    'sections' => [['name' => ?, 'items' => [[description, unit, qty, rate, amount]]]],
    'subtotal', 'discount', 'tax_label', 'tax', 'total', 'paid'?, 'due'?,
    'notes'?, 'watermark'?
  ]
--}}
@php
    $t = fn ($key) => __('doc.'.$key, [], $lang);
    $num = fn ($v) => ($lang === 'bn' && $company['bn_digits']) ? \App\Services\Money::bnDigits($v) : (string) $v;
    $money = fn ($v) => \App\Services\Money::format($v, ($lang === 'bn' && $company['bn_digits']) ? 'bn' : 'en');
    $c = $company['color'];
    $sl = 0;
@endphp
<html>
<head>
<style>
  body { font-family: hindsiliguri; font-size: 10pt; color: #222; }
  .muted { color: #777; }
  table { border-collapse: collapse; }
  .head td { vertical-align: top; }
  .company-name { font-size: 16pt; font-weight: bold; color: #1b1a18; }
  .doc-title { font-size: 20pt; font-weight: bold; color: {{ $c }}; text-align: right; }
  .meta td { padding: 1px 0 1px 8px; font-size: 9.5pt; }
  .party { margin-top: 14px; border-left: 3px solid {{ $c }}; padding: 6px 10px; background: #faf7f2; }
  .items { width: 100%; margin-top: 14px; }
  .items th { background: #1b1a18; color: #fff; padding: 6px 6px; font-size: 9pt; font-weight: bold; text-align: left; }
  .items td { padding: 6px 6px; border-bottom: 1px solid #e7e1d6; font-size: 9.5pt; vertical-align: top; }
  .items .section td { background: #f3eee6; font-weight: bold; color: #1b1a18; }
  .r, .items th.r { text-align: right; }
  .c, .items th.c { text-align: center; }
  .totals { margin-top: 8px; }
  .totals td { padding: 4px 6px; font-size: 10pt; }
  .totals .grand td { background: {{ $c }}; color: #fff; font-weight: bold; font-size: 11pt; }
  .words { margin-top: 10px; padding: 6px 10px; background: #faf7f2; font-size: 9.5pt; }
  .block-title { font-weight: bold; color: #1b1a18; margin: 14px 0 4px; font-size: 10pt; }
  .small { font-size: 8.8pt; line-height: 1.5; }
  .sign td { padding-top: 40px; font-size: 9pt; text-align: center; }
  .sign .line { border-top: 1px solid #555; padding-top: 4px; }
  .note { margin-top: 10px; padding: 6px 10px; border: 1px dashed {{ $c }}; color: #8a6a33; font-size: 9pt; }
</style>
</head>
<body>

@if (!empty($doc['watermark']))
  <watermarktext content="{{ $doc['watermark'] }}" alpha="0.06" />
@endif

<htmlpagefooter name="footer">
  <table width="100%" style="border-top:1px solid #e7e1d6;"><tr>
    <td class="muted" style="font-size:8pt;padding-top:4px;">{{ $company['footer'] ?: $company['name'] }}</td>
    <td class="muted r" style="font-size:8pt;padding-top:4px;">{PAGENO} / {nbpg}</td>
  </tr></table>
</htmlpagefooter>
<sethtmlpagefooter name="footer" value="on" />

{{-- Header: company (left), document title + meta (right) --}}
<table width="100%" class="head"><tr>
  <td width="58%">
    @if ($company['logo'])
      <img src="{{ $company['logo'] }}" style="height:46px;" /><br />
    @else
      <div class="company-name">{{ $company['name'] }}</div>
    @endif
    @if ($company['logo'])<div style="font-weight:bold;font-size:11pt;margin-top:4px;">{{ $company['name'] }}</div>@endif
    <div class="small muted">
      @if ($company['address']){!! nl2br(e($company['address'])) !!}<br />@endif
      @if ($company['phone']){{ $t('phone') }}: {{ $company['phone'] }} @endif
      @if ($company['email']) · {{ $company['email'] }}@endif
      @if ($company['bin'])<br />{{ $t('bin') }}: {{ $company['bin'] }}@endif
      @if ($company['tin']) · {{ $t('tin') }}: {{ $company['tin'] }}@endif
    </div>
  </td>
  <td width="42%">
    <div class="doc-title">{{ $doc['title'] }}</div>
    <table class="meta" align="right">
      <tr><td class="muted">{{ $t('number') }}</td><td><b>{{ $doc['number'] }}</b></td></tr>
      <tr><td class="muted">{{ $t('date') }}</td><td>{{ $num($doc['date']) }}</td></tr>
      @foreach (($doc['meta'] ?? []) as $label => $value)
        <tr><td class="muted">{{ $label }}</td><td>{{ $num($value) }}</td></tr>
      @endforeach
    </table>
  </td>
</tr></table>

{{-- Party --}}
@if (!empty($doc['party']))
<div class="party">
  <div class="muted small">{{ $doc['party_label'] ?? $t('bill_to') }}</div>
  @foreach ($doc['party'] as $i => $line)
    <div @if($i === 0) style="font-weight:bold;font-size:11pt;" @endif>{{ $line }}</div>
  @endforeach
</div>
@endif

{{-- Items --}}
<table class="items">
  <thead><tr>
    <th width="6%" class="c">{{ $t('sl') }}</th>
    <th>{{ $t('description') }}</th>
    <th width="9%" class="c">{{ $t('unit') }}</th>
    <th width="9%" class="r">{{ $t('qty') }}</th>
    <th width="14%" class="r">{{ $t('rate') }}</th>
    <th width="16%" class="r">{{ $t('amount') }}</th>
  </tr></thead>
  <tbody>
  @foreach ($doc['sections'] as $section)
    @if (!empty($section['name']))
      <tr class="section"><td colspan="6">{{ $section['name'] }}</td></tr>
    @endif
    @foreach ($section['items'] as $item)
      @php $sl++; @endphp
      <tr>
        <td class="c">{{ $num($sl) }}</td>
        <td>{{ $item['description'] }}</td>
        <td class="c">{{ $item['unit'] ?? '' }}</td>
        <td class="r">{{ $num(rtrim(rtrim(number_format((float) $item['qty'], 2, '.', ''), '0'), '.')) }}</td>
        <td class="r">{{ $money($item['rate']) }}</td>
        <td class="r">{{ $money($item['amount']) }}</td>
      </tr>
    @endforeach
  @endforeach
  </tbody>
</table>

{{-- Totals --}}
<table width="100%"><tr>
  <td width="50%"></td>
  <td width="50%">
    <table class="totals" width="100%">
      <tr><td>{{ $t('subtotal') }}</td><td class="r">{{ $money($doc['subtotal']) }}</td></tr>
      @if ((float) ($doc['discount'] ?? 0) > 0)
        <tr><td>{{ $t('discount') }}</td><td class="r">- {{ $money($doc['discount']) }}</td></tr>
      @endif
      @if ((float) ($doc['tax'] ?? 0) > 0)
        <tr><td>{{ $doc['tax_label'] }}</td><td class="r">{{ $money($doc['tax']) }}</td></tr>
      @endif
      <tr class="grand"><td>{{ $t('total') }} ({{ $t('currency') }})</td><td class="r">{{ $money($doc['total']) }}</td></tr>
      @isset($doc['paid'])
        <tr><td>{{ $t('paid') }}</td><td class="r">{{ $money($doc['paid']) }}</td></tr>
        <tr><td><b>{{ $t('due') }}</b></td><td class="r"><b>{{ $money($doc['due']) }}</b></td></tr>
      @endisset
    </table>
  </td>
</tr></table>

<div class="words"><b>{{ $t('in_words') }}:</b> {{ \App\Services\AmountInWords::for($lang, $doc['total']) }}</div>

@if (!empty($doc['notes']))
  <div class="note">{{ $doc['notes'] }}</div>
@endif

@if ($company['terms'])
  <div class="block-title">{{ $t('terms') }}</div>
  <div class="small">{!! nl2br(e($company['terms'])) !!}</div>
@endif

@if ($company['bank'])
  <div class="block-title">{{ $t('bank_details') }}</div>
  <div class="small">{!! nl2br(e($company['bank'])) !!}</div>
@endif

{{-- Signatures --}}
<table width="100%" class="sign"><tr>
  <td width="40%"><div class="line">{{ $t('customer_signature') }}</div></td>
  <td width="20%"></td>
  <td width="40%">
    @if ($company['seal'] || $company['signature'])
      <div style="margin-top:-36px;">
        @if ($company['seal'])<img src="{{ $company['seal'] }}" style="height:56px;" />@endif
        @if ($company['signature'])<img src="{{ $company['signature'] }}" style="height:40px;" />@endif
      </div>
    @endif
    <div class="line">
      {{ $t('authorized_signature') }}
      @if ($company['signatory_name'])<br /><b>{{ $company['signatory_name'] }}</b>@endif
      @if ($company['signatory_title'])<br /><span class="muted">{{ $company['signatory_title'] }}</span>@endif
    </div>
  </td>
</tr></table>

</body>
</html>
