@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="background-color: {{ \App\Support\MailBrand::color() }}; border-color: {{ \App\Support\MailBrand::color() }}; border-top: 10px solid {{ \App\Support\MailBrand::color() }}; border-bottom: 10px solid {{ \App\Support\MailBrand::color() }}; border-left: 18px solid {{ \App\Support\MailBrand::color() }}; border-right: 18px solid {{ \App\Support\MailBrand::color() }}; color: {{ \App\Support\MailBrand::buttonText() }}; border-radius: 999px; font-weight: 700;">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
