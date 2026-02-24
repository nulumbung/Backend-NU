<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Agenda;
use App\Models\User;
use App\Models\Multimedia;

class DashboardController extends Controller
{
    public function stats()
    {
        return response()->json([
            'total_posts' => Post::count(),
            'total_users' => User::count(),
            'total_agendas' => Agenda::count(),
            'total_videos' => Multimedia::where('type', 'video')->count(),
            'recent_posts' => Post::latest()->take(5)->get(),
        ]);
    }
}
