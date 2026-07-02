<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;

class ContactController extends Controller
{
    public function store(ContactRequest $request): RedirectResponse
    {
        // Honeypot anti-bot (FR-21 / NFR-10): silently discard bot submissions.
        if (filled($request->input('website'))) {
            return back()->with('contact_success', 'Pesan Anda telah terkirim. Terima kasih!');
        }

        $data = $request->validated();

        ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
            'is_read' => false,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('contact_success', 'Pesan Anda berhasil terkirim! Kami akan segera membalas.');
    }
}
