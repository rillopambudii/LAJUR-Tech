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
            $answer = $result['answer'];
        } catch (\Throwable $e) {
            $answer = $e->getMessage();
        }

        return back()->with([
            'assistant_question' => $data['question'],
            'assistant_answer' => $answer,
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

