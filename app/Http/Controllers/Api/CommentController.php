<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveStream;
use App\Models\Multimedia;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(string $type, string $target)
    {
        $commentable = $this->resolveCommentable($type, $target);
        if (!$commentable) {
            return response()->json(['message' => 'Konten tidak ditemukan.'], 404);
        }

        $comments = $commentable->comments()
            ->with('user:id,name,avatar')
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($comments);
    }

    public function store(Request $request, string $type, string $target)
    {
        $commentable = $this->resolveCommentable($type, $target);
        if (!$commentable) {
            return response()->json(['message' => 'Konten tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'content' => 'required|string|min:2|max:2000',
        ]);

        $comment = $commentable->comments()->create([
            'user_id' => $request->user()->id,
            'content' => trim($validated['content']),
            'status' => 'approved',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return response()->json(
            $comment->load('user:id,name,avatar'),
            201
        );
    }

    protected function resolveCommentable(string $type, string $target): ?Model
    {
        $normalizedType = strtolower(trim($type));

        if (in_array($normalizedType, ['post', 'posts', 'berita'], true)) {
            if (is_numeric($target)) {
                return Post::find((int) $target);
            }

            return Post::where('slug', $target)->first();
        }

        if (in_array($normalizedType, ['multimedia', 'media'], true)) {
            if (is_numeric($target)) {
                return Multimedia::find((int) $target);
            }

            return Multimedia::where('slug', $target)->first();
        }

        if (in_array($normalizedType, ['live', 'live-stream', 'live-streams', 'livestream'], true)) {
            if ($target === 'active') {
                return LiveStream::active()->first();
            }

            return LiveStream::find(is_numeric($target) ? (int) $target : 0);
        }

        return null;
    }
}
