<?php

namespace App\Http\Controllers\Admin;

use App\AI\AssistantService;
use App\AI\DashboardInsightService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function __construct(private AssistantService $assistant)
    {
    }

    public function index(): View
    {
        return view('admin.assistant', [
            'configured' => $this->assistant->isConfigured(),
        ]);
    }

    public function ask(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ], [
            'question.required' => 'Silakan tulis pertanyaan terlebih dahulu.',
        ]);

        try {
            $result = $this->assistant->ask($data['question']);
        } catch (\Throwable $e) {
            // Jangan bocorkan pesan exception internal (URL endpoint, body upstream,
            // kunci config) ke layar tenant sebagai kalau itu jawaban AI.
            \Illuminate\Support\Facades\Log::warning('AI assistant gagal', ['error' => $e->getMessage()]);

            return back()->with([
                'assistant_question' => $data['question'],
                'assistant_error' => 'Asisten AI sedang tidak dapat menjawab. Coba lagi beberapa saat, atau hubungi admin bila terus berlanjut.',
            ]);
        }

        return back()->with([
            'assistant_question' => $data['question'],
            'assistant_answer' => $result['answer'],
        ]);
    }

    /** Async JSON endpoint for the dashboard AI summary card. */
    public function insight(Request $request, DashboardInsightService $insight): JsonResponse
    {
        try {
            return response()->json($insight->get($request->boolean('fresh')));
        } catch (\Throwable) {
            return response()->json(['text' => 'Ringkasan belum tersedia saat ini.', 'source' => 'error']);
        }
    }
}

