<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Mail\TemplatePreview;
use App\Models\Setting;
use App\Support\MailTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Editor šablon e-mailů zákazníkům (back-office → E-maily → Šablony). */
class MailTemplateController extends Controller
{
    public function edit(string $key)
    {
        abort_unless(MailTemplates::exists($key), 404);

        return view('backoffice.mail-template', [
            'key' => $key, 'def' => MailTemplates::definition($key), 'tpl' => MailTemplates::current($key),
            'saved' => MailTemplates::saved($key), 'mode' => Setting::siteMode(),
        ]);
    }

    public function save(Request $request, string $key)
    {
        abort_unless(MailTemplates::exists($key), 404);
        if ($request->input('action') === 'reset') {
            MailTemplates::reset($key);

            return redirect()->route('backoffice.mail.template', $key)->with('ok', 'Šablona je vrácená na výchozí text.');
        }
        $v = $this->validated($request);
        MailTemplates::save($key, $v['subject'], $v['body']);
        $unknown = MailTemplates::unknownPlaceholders($key, $v['subject']."\n".$v['body']);

        return redirect()->route('backoffice.mail.template', $key)->with('ok', 'Šablona je uložená.'
            .($unknown ? ' Pozor, neznámé zástupce zůstanou v e-mailu tak, jak jsou: '.implode(', ', $unknown).'.' : ''));
    }

    /** Náhled: GET = uložená verze, POST = neuložený text z editoru. */
    public function preview(Request $request, string $key)
    {
        abort_unless(MailTemplates::exists($key), 404);
        $text = $request->isMethod('post') ? $this->validated($request) : null;
        $mail = new TemplatePreview($key, MailTemplates::sample($key), $text);
        $subject = e($mail->envelope()->subject);

        return response('<div style="font:13px/1.4 system-ui,sans-serif;padding:10px 14px;background:#f4f2fa;border-bottom:1px solid #ddd">Předmět: <b>'.$subject.'</b></div>'.$mail->render());
    }

    public function test(Request $request, string $key)
    {
        abort_unless(MailTemplates::exists($key), 404);
        Mail::to($request->user())->send(new TemplatePreview($key, MailTemplates::sample($key), $this->validated($request), test: true));

        return back()->withInput()->with('ok', 'Zkušební e-mail (se vzorovými údaji) odešel na '.$request->user()->email.'. Text šablony se tím neuložil.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:20000',
        ], ['subject.required' => 'Předmět nesmí být prázdný.', 'body.required' => 'Text e-mailu nesmí být prázdný.']);
    }
}
