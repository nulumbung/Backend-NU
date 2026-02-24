<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\UserLoginDevice;

class AuthController extends Controller
{
    protected const ADMIN_ROLES = ['superadmin', 'admin', 'editor', 'redaksi'];

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|max:255',
            'avatar' => 'nullable|string|max:2048',
            'portal' => 'nullable|in:public,admin',
            'device_fingerprint' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'device_metadata' => 'nullable|array',
        ]);

        $portal = $validated['portal'] ?? 'public';
        if ($portal !== 'public') {
            return response()->json([
                'message' => 'Registrasi hanya tersedia untuk portal publik.',
            ], 403);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'avatar' => $validated['avatar'] ?? null,
            'auth_provider' => 'email',
        ]);

        return $this->buildAuthResponse(
            $user,
            $request,
            'email',
            201,
            'Registrasi berhasil.'
        );
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'portal' => 'nullable|in:public,admin',
            'device_fingerprint' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'device_metadata' => 'nullable|array',
        ]);

        $portal = $credentials['portal'] ?? 'public';

        if (Auth::attempt([
            'email' => strtolower($credentials['email']),
            'password' => $credentials['password'],
        ])) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (!$this->canAccessPortal($user, $portal)) {
                Auth::logout();
                return response()->json([
                    'message' => $this->portalDeniedMessage($portal),
                ], 403);
            }

            return $this->buildAuthResponse($user, $request, 'email');
        }

        return response()->json(['message' => 'Email atau password tidak valid.'], 401);
    }

    public function googleLogin(Request $request)
    {
        $validated = $request->validate([
            'id_token' => 'required|string',
            'portal' => 'nullable|in:public,admin',
            'device_fingerprint' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'device_metadata' => 'nullable|array',
        ]);

        $portal = $validated['portal'] ?? 'public';

        $clientId = config('services.google.client_id');
        if (empty($clientId)) {
            return response()->json([
                'message' => 'Google Login belum dikonfigurasi di server.',
            ], 500);
        }

        $googleClient = new \Google_Client(['client_id' => $clientId]);
        $payload = $googleClient->verifyIdToken($validated['id_token']);

        if (!$payload || empty($payload['email'])) {
            return response()->json(['message' => 'Token Google tidak valid.'], 401);
        }

        $emailVerified = $payload['email_verified'] ?? false;
        if ($emailVerified !== true && $emailVerified !== 'true') {
            return response()->json(['message' => 'Email Google belum terverifikasi.'], 401);
        }

        $providerId = (string) ($payload['sub'] ?? '');
        $email = strtolower((string) $payload['email']);
        $name = trim((string) ($payload['name'] ?? 'Pengguna Google'));
        $avatar = (string) ($payload['picture'] ?? '');

        $user = null;
        if (!empty($providerId)) {
            $user = User::where('provider_id', $providerId)->first();
        }

        if (!$user) {
            $user = User::where('email', $email)->first();
        }

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'role' => 'user',
                'avatar' => $avatar ?: null,
                'auth_provider' => 'google',
                'provider_id' => $providerId ?: null,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->fill([
                'name' => $user->name ?: $name,
                'avatar' => $avatar ?: $user->avatar,
                'auth_provider' => 'google',
            ]);

            if (empty($user->provider_id) && !empty($providerId)) {
                $user->provider_id = $providerId;
            }

            if (empty($user->email_verified_at)) {
                $user->email_verified_at = now();
            }

            $user->save();
        }

        Auth::login($user);

        if (!$this->canAccessPortal($user, $portal)) {
            Auth::logout();
            return response()->json([
                'message' => $this->portalDeniedMessage($portal),
            ], 403);
        }

        return $this->buildAuthResponse(
            $user,
            $request,
            'google',
            200,
            'Login Google berhasil.'
        );
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    protected function canAccessPortal(User $user, string $portal): bool
    {
        if ($portal === 'admin') {
            return in_array($user->role, self::ADMIN_ROLES, true);
        }

        return $user->role === 'user';
    }

    protected function portalDeniedMessage(string $portal): string
    {
        if ($portal === 'admin') {
            return 'Akun user publik tidak dapat mengakses panel admin.';
        }

        return 'Akun admin hanya bisa digunakan di panel admin.';
    }

    protected function buildAuthResponse(
        User $user,
        Request $request,
        string $provider = 'email',
        int $status = 200,
        string $message = 'Login berhasil.'
    ) {
        $tokenName = sprintf('%s-token-%s', $provider, now()->timestamp);
        $token = $user->createToken($tokenName)->plainTextToken;

        $this->recordLoginDevice($user, $request, $provider);

        return response()->json([
            'message' => $message,
            'user' => $user->fresh(),
            'token' => $token,
            'role' => $user->role,
        ], $status);
    }

    protected function recordLoginDevice(User $user, Request $request, string $provider): void
    {
        $now = now();
        $ipAddress = $request->ip();
        $userAgent = (string) ($request->userAgent() ?? '');
        $deviceFingerprint = $this->resolveDeviceFingerprint($request, $ipAddress, $userAgent);

        $user->forceFill([
            'last_login_at' => $now,
            'last_login_ip' => $ipAddress,
            'auth_provider' => $provider,
        ])->save();

        $device = UserLoginDevice::firstOrNew([
            'user_id' => $user->id,
            'device_fingerprint' => $deviceFingerprint,
        ]);

        $device->device_name = $request->input('device_name');
        $device->ip_address = $ipAddress;
        $device->user_agent = $userAgent;
        $device->login_provider = $provider;
        $device->metadata = $request->input('device_metadata');
        $device->last_login_at = $now;
        $device->last_seen_at = $now;
        $device->login_count = $device->exists ? ((int) $device->login_count + 1) : 1;
        $device->save();
    }

    protected function resolveDeviceFingerprint(Request $request, ?string $ipAddress, string $userAgent): string
    {
        $fingerprint = trim((string) $request->input('device_fingerprint', ''));
        if ($fingerprint !== '') {
            return Str::limit($fingerprint, 255, '');
        }

        return hash('sha256', implode('|', [
            (string) $ipAddress,
            $userAgent,
            (string) $request->header('accept-language'),
        ]));
    }
}
