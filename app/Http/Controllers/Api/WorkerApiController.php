<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceIssued;
use App\Models\Invoice;
use App\Models\WorkerJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * API pro pomocný worker na Mac Mini (Bearer WORKER_TOKEN). Worker si úlohy vyzvedává (pull),
 * Mac Mini proto nepotřebuje veřejnou adresu a token MyÚčta neopouští Mac Mini.
 */
class WorkerApiController extends Controller
{
    public function jobs(Request $request): JsonResponse
    {
        $types = array_filter(explode(',', (string) $request->query('types', 'invoice,credit_note')));
        $jobs = DB::transaction(function () use ($types) {
            $jobs = WorkerJob::whereIn('type', $types)
                ->where(fn ($q) => $q->where('status', 'queued')
                    ->orWhere(fn ($q) => $q->where('status', 'running')->where('locked_until', '<', now()))
                    ->orWhere(fn ($q) => $q->where('status', 'failed')->where('attempts', '<', 5)->where('updated_at', '<', now()->subMinutes(10))))
                ->orderBy('id')->limit(10)->lockForUpdate()->get();
            foreach ($jobs as $job) {
                $job->update(['status' => 'running', 'locked_until' => now()->addMinutes(10), 'attempts' => $job->attempts + 1]);
            }

            return $jobs;
        });

        return response()->json(['data' => $jobs->map(fn ($j) => [
            'id' => $j->id, 'type' => $j->type,
            'payload' => [...$j->payload, ...($j->result['myucto_id'] ?? null ? ['myucto_id' => $j->result['myucto_id']] : [])],
        ])]);
    }

    public function done(Request $request, WorkerJob $job): JsonResponse
    {
        $data = $request->validate(['myucto_id' => 'nullable|integer', 'number' => 'nullable|string|max:40', 'pdf' => 'nullable|file|mimes:pdf|max:10240']);
        $job->update(['status' => 'done', 'result' => array_intersect_key($data, array_flip(['myucto_id', 'number'])), 'error' => null, 'locked_until' => null]);

        if ($job->subject instanceof Invoice) {
            $invoice = $job->subject;
            $path = $request->file('pdf')?->storeAs('invoices', ($data['number'] ?? 'doklad-'.$invoice->id).'.pdf', 'local');
            $invoice->update(['status' => 'issued', 'myucto_id' => $data['myucto_id'] ?? null, 'number' => $data['number'] ?? null, 'pdf_path' => $path]);
            if ($path) {
                try {
                    Mail::to($invoice->order->email)->send(new InvoiceIssued($invoice));
                    $invoice->update(['status' => 'sent', 'sent_at' => now()]);
                } catch (Throwable $e) {
                    Log::error('Doklad se nepodařilo odeslat: '.$e->getMessage(), ['invoice' => $invoice->id]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    public function failed(Request $request, WorkerJob $job): JsonResponse
    {
        $job->update(['status' => 'failed', 'error' => mb_substr((string) $request->input('error'), 0, 2000), 'locked_until' => null]);
        Log::warning('Worker: úloha selhala', ['job' => $job->id, 'type' => $job->type, 'error' => $job->error]);

        return response()->json(['ok' => true]);
    }
}
