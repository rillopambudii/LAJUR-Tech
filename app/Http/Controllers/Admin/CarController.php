<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarRequest;
use App\Models\Car;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CarController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $cars = Car::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->ordered()
            ->paginate(10)
            ->withQueryString();

        return view('admin.cars.index', compact('cars', 'search'));
    }

    public function create(): View
    {
        $car = new Car(['seats' => 4, 'is_available' => true, 'transmission' => 'Automatic', 'fuel_type' => 'Bensin']);

        return view('admin.cars.form', ['car' => $car]);
    }

    public function store(CarRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['image'] = $this->resolveImage($request);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        unset($data['image_url']);

        Car::create($data);

        return redirect()->route('admin.cars.index')->with('success', 'Mobil berhasil ditambahkan.');
    }

    public function edit(Car $car): View
    {
        return view('admin.cars.form', ['car' => $car]);
    }

    public function update(CarRequest $request, Car $car): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? 0;

        // Only replace the image when a new file/URL is supplied (FR-33).
        $newImage = $this->resolveImage($request);
        if ($newImage !== null) {
            $this->deleteLocalImage($car);
            $data['image'] = $newImage;
        } else {
            unset($data['image']);
        }
        unset($data['image_url']);

        $car->update($data);

        return redirect()->route('admin.cars.index')->with('success', 'Mobil berhasil diperbarui.');
    }

    public function destroy(Car $car): RedirectResponse
    {
        $this->deleteLocalImage($car); // FR-34
        $car->delete();

        return redirect()->route('admin.cars.index')->with('success', 'Mobil berhasil dihapus.');
    }

    /**
     * Resolve a new image source from the request, or null to keep the current one.
     * Uploaded file takes precedence over an external URL.
     */
    private function resolveImage(Request $request): ?string
    {
        if ($request->hasFile('image')) {
            // Stored with a hashed filename to prevent collisions & path traversal (NFR-06).
            return $request->file('image')->store('cars', 'public');
        }

        if (filled($request->input('image_url'))) {
            return $request->input('image_url');
        }

        return null;
    }

    private function deleteLocalImage(Car $car): void
    {
        if ($car->hasLocalImage()) {
            Storage::disk('public')->delete($car->image);
        }
    }
}
