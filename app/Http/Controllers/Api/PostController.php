<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\Post;

class PostController extends Controller
{
    private function hasPostColumn(string $column): bool
    {
        static $columnCache = [];

        if (!array_key_exists($column, $columnCache)) {
            $columnCache[$column] = Schema::hasColumn('posts', $column);
        }

        return $columnCache[$column];
    }

    private function applyFlagFilters($query, Request $request)
    {
        if ($request->boolean('featured') && $this->hasPostColumn('is_featured')) {
            $query->where('is_featured', true);
        }
        if ($request->boolean('spotlight') && $this->hasPostColumn('is_spotlight')) {
            $query->where('is_spotlight', true);
        }
        if ($request->boolean('breaking') && $this->hasPostColumn('is_breaking')) {
            $query->where('is_breaking', true);
        }
        if ($request->boolean('headline') && $this->hasPostColumn('is_headline')) {
            $query->where('is_headline', true);
        }

        return $query;
    }

    private function syncHeadlinePost(Post $post): void
    {
        if (
            !$this->hasPostColumn('is_headline') ||
            !$post->is_headline
        ) {
            return;
        }

        Post::where('id', '!=', $post->id)
            ->where('is_headline', true)
            ->update(['is_headline' => false]);
    }

    /**
     * Display a listing of the resource (Public).
     */
    public function index(Request $request)
    {
        $query = Post::with('author', 'category')
            ->where('status', 'published');

        $this->applyFlagFilters($query, $request);

        return $query->latest()->paginate(10);
    }

    /**
     * Admin Index (All posts).
     */
    public function adminIndex()
    {
        return Post::with('author', 'category')
            ->latest()
            ->get(); // In real app, paginate this
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required',
            'excerpt' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:draft,published,archived',
            'image' => 'nullable|string',
            'tags' => 'nullable|array',
            'image_caption' => 'nullable|string',
            'image_credit' => 'nullable|string',
            'read_time' => 'nullable|string',
            'is_featured' => 'boolean',
            'is_spotlight' => 'boolean',
            'is_breaking' => 'boolean',
            'is_headline' => 'boolean',
        ]);

        $validated['author_id'] = $request->user()->id;
        $validated['slug'] = \Illuminate\Support\Str::slug($validated['title']) . '-' . time();

        if ($validated['status'] === 'published') {
            $validated['published_at'] = now();
        }
        
        // Auto calculate read time if not provided
        if (empty($validated['read_time'])) {
            $wordCount = str_word_count(strip_tags($validated['content']));
            $minutes = ceil($wordCount / 200); // Average reading speed
            $validated['read_time'] = $minutes . ' menit';
        }

        $post = Post::create($validated);
        $this->syncHeadlinePost($post);

        return response()->json($post, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (is_numeric($id)) {
            $post = Post::with('author', 'category')->find($id);
        } else {
            $post = Post::with('author', 'category')->where('slug', $id)->first();
        }

        if (!$post) {
            return response()->json(['message' => 'Post not found'], 404);
        }

        // Increment views
        $post->increment('views');

        return $post;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $post = Post::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes',
            'excerpt' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'sometimes|in:draft,published,archived',
            'image' => 'nullable|string',
            'tags' => 'nullable|array',
            'image_caption' => 'nullable|string',
            'image_credit' => 'nullable|string',
            'read_time' => 'nullable|string',
            'is_featured' => 'boolean',
            'is_spotlight' => 'boolean',
            'is_breaking' => 'boolean',
            'is_headline' => 'boolean',
        ]);

        if (isset($validated['title'])) {
             $validated['slug'] = \Illuminate\Support\Str::slug($validated['title']) . '-' . $post->id;
        }

        // Update published_at if status changes to published
        if (isset($validated['status']) && $validated['status'] === 'published' && $post->status !== 'published') {
            $validated['published_at'] = now();
        }

        $post->update($validated);
        $post->refresh();
        $this->syncHeadlinePost($post);
        $post->refresh();

        return response()->json($post);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $post = Post::findOrFail($id);
        $post->delete();
        return response()->json(['message' => 'Post deleted successfully']);
    }
    
    // Additional public methods
    public function latest(Request $request) {
        $limit = (int) $request->input('limit', 5);
        $limit = max(1, min($limit, 30));

        $query = Post::with('author', 'category')
            ->where('status', 'published');

        $this->applyFlagFilters($query, $request);

        return $query->latest()->take($limit)->get();
    }
    
    public function byCategory(Request $request, $slug) {
        $query = Post::with('author', 'category')
            ->whereHas('category', function($q) use ($slug) {
                $q->where('slug', $slug);
            })
            ->where('status', 'published');

        $this->applyFlagFilters($query, $request);

        return $query->latest()->paginate(10);
    }
}
