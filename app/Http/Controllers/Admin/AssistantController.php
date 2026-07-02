<?php

namespace App\Http\Controllers\Admin;

use App\AI\AssistantService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
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
}
