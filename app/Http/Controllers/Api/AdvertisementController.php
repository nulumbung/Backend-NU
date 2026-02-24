<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdvertisementController extends Controller
{
    protected const PLACEMENTS = [
        'post_detail_top',
        'post_detail_inline',
        'post_detail_sidebar',
        'post_detail_bottom',
    ];

    public function index(Request $request)
    {
        $query = Advertisement::query()->orderByDesc('priority')->orderByDesc('created_at');

        if ($request->filled('placement')) {
            $query->where('placement', $request->input('placement'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%')
                    ->orWhere('target_url', 'like', '%' . $search . '%');
            });
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $advertisement = Advertisement::create([
            ...$validated,
            'slug' => $this->generateUniqueSlug($validated['title']),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json($advertisement, 201);
    }

    public function show(string $id)
    {
        $ad = $this->resolveAd($id);
        if (!$ad) {
            return response()->json(['message' => 'Iklan tidak ditemukan.'], 404);
        }

        return response()->json($ad);
    }

    public function update(Request $request, string $id)
    {
        $ad = $this->resolveAd($id);
        if (!$ad) {
            return response()->json(['message' => 'Iklan tidak ditemukan.'], 404);
        }

        $validated = $request->validate($this->rules($ad->id));

        if (isset($validated['title']) && $validated['title'] !== $ad->title) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $ad->id);
        }

        $ad->update($validated);

        return response()->json($ad);
    }

    public function destroy(string $id)
    {
        $ad = $this->resolveAd($id);
        if (!$ad) {
            return response()->json(['message' => 'Iklan tidak ditemukan.'], 404);
        }

        $ad->delete();
        return response()->json(['message' => 'Iklan berhasil dihapus.']);
    }

    public function active(Request $request)
    {
        $validated = $request->validate([
            'placement' => ['nullable', Rule::in(self::PLACEMENTS)],
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $limit = (int) ($validated['limit'] ?? 5);

        $query = Advertisement::query()
            ->active()
            ->orderByDesc('priority')
            ->orderByDesc('created_at');

        if (!empty($validated['placement'])) {
            $query->where('placement', $validated['placement']);
        }

        $ads = $query->take($limit)->get();

        return response()->json($ads);
    }

    public function impression(Advertisement $advertisement)
    {
        $advertisement->increment('impressions');
        return response()->json(['message' => 'Impression tracked']);
    }

    public function click(Advertisement $advertisement)
    {
        $advertisement->increment('clicks');
        return response()->json([
            'message' => 'Click tracked',
            'target_url' => $advertisement->target_url,
        ]);
    }

    protected function rules(?int $adId = null): array
    {
        return [
            'title' => 'required|string|max:255',
            'placement' => ['required', Rule::in(self::PLACEMENTS)],
            'content_type' => ['required', Rule::in(['image', 'html'])],
            'image_url' => 'nullable|string|max:10000',
            'html_content' => 'nullable|string',
            'target_url' => 'nullable|url|max:10000',
            'alt_text' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'priority' => 'nullable|integer|min:0|max:9999',
            'slug' => [
                'sometimes',
                'string',
                Rule::unique('advertisements', 'slug')->ignore($adId),
            ],
        ];
    }

    protected function resolveAd(string $id): ?Advertisement
    {
        if (is_numeric($id)) {
            return Advertisement::find((int) $id);
        }

        return Advertisement::where('slug', $id)->first();
    }

    protected function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'advertisement';
        }

        $slug = $base;
        $counter = 1;

        while (
            Advertisement::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}
