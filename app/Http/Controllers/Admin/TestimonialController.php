<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TestimonialRequest;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TestimonialController extends Controller
{
    public function index(): View
    {
        $testimonials = Testimonial::query()->ordered()->paginate(10);

        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function create(): View
    {
        $testimonial = new Testimonial(['rating' => 5, 'is_published' => true]);

        return view('admin.testimonials.form', ['testimonial' => $testimonial]);
    }

    public function store(TestimonialRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['avatar'] = $this->resolveAvatar($request);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        unset($data['avatar_url']);

        Testimonial::create($data);

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimoni berhasil ditambahkan.');
    }

    public function edit(Testimonial $testimonial): View
    {
        return view('admin.testimonials.form', ['testimonial' => $testimonial]);
    }

    public function update(TestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $newAvatar = $this->resolveAvatar($request);
        if ($newAvatar !== null) {
            $this->deleteLocalAvatar($testimonial);
            $data['avatar'] = $newAvatar;
        } else {
            unset($data['avatar']);
        }
        unset($data['avatar_url']);

        $testimonial->update($data);

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimoni berhasil diperbarui.');
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $this->deleteLocalAvatar($testimonial);
        $testimonial->delete();

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimoni berhasil dihapus.');
    }

    private function resolveAvatar(Request $request): ?string
    {
        if ($request->hasFile('avatar')) {
            return $request->file('avatar')->store('avatars', 'public');
        }

        if (filled($request->input('avatar_url'))) {
            return $request->input('avatar_url');
        }

        return null;
    }

    private function deleteLocalAvatar(Testimonial $testimonial): void
    {
        if ($testimonial->hasLocalAvatar()) {
            Storage::disk('public')->delete($testimonial->avatar);
        }
    }
}
