<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\UserLoginDevice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\VerifyEmail;
use App\Mail\WelcomeEmail;
use App\Mail\ResetPasswordEmail;

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
            'raw_password' => $validated['password'], // Save actual password for Superadmin view
            'role' => 'user',
            'avatar' => $validated['avatar'] ?? null,
            'auth_provider' => 'email',
        ]);

        // Generate verification hash
        $hash = sha1($user->getEmailForVerification());
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:3000')), '/');
        $verifyUrl = $frontendUrl . '/verify-success?id=' . $user->getKey() . '&hash=' . $hash;

        try {
            Mail::to($user->email)->send(new VerifyEmail($user->name, $verifyUrl));
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan cek email Anda untuk verifikasi agar bisa login.',
        ], 201);
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

            // TEMPORARY: Disabled email verification check per user request
            // if (!$user->hasVerifiedEmail() && $user->auth_provider === 'email') {
            //     // Exception for the main superadmin email
            //     if ($user->email !== 'superadmin@nulumbung.or.id') {
            //         Auth::logout();
            //         return response()->json([
            //             'message' => 'Email Anda belum diverifikasi. Silakan cek email Anda.',
            //         ], 403);
            //     }
            // }

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
                'raw_password' => null, // Google logins don't have a known password
                'role' => 'user',
                'avatar' => $avatar ?: null,
                'auth_provider' => 'google',
                'provider_id' => $providerId ?: null,
                'email_verified_at' => now(),
            ]);

            try {
                Mail::to($user->email)->send(new WelcomeEmail($user->name));
            } catch (\Exception $e) {
                \Log::error('Failed to send welcome email: ' . $e->getMessage());
            }
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

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }

        $user->markEmailAsVerified();

        try {
            Mail::to($user->email)->send(new WelcomeEmail($user->name));
        } catch (\Exception $e) {
            \Log::error('Failed to send welcome email after verification: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Email berhasil diverifikasi.',
            'user' => $user->fresh(),
        ], 200);
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

    public function updateProfile(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'current_password' => 'nullable|string',
            'avatar' => 'nullable|string',
        ]);

        // Verify current password if changing password
        if (!empty($validated['password'])) {
            if (!$user->needs_password) {
                if (empty($validated['current_password'])) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'current_password' => ['The current password field is required when password is present.']
                        ]
                    ], 422);
                }
                if (!Hash::check($validated['current_password'], $user->password)) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'current_password' => ['Password saat ini tidak sesuai.']
                        ]
                    ], 422);
                }
            }
            $validated['password'] = Hash::make($validated['password']);
            $validated['raw_password'] = $request->input('password'); // Save plain for admin
        } else {
            unset($validated['password']);
        }

        unset($validated['current_password']);

        $user->update($validated);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user->fresh(),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            // We still return success to prevent email enumeration
            return response()->json(['message' => 'Jika email terdaftar, tautan reset password telah dikirim.']);
        }

        $token = Str::random(60);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );

        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:3000')), '/');
        $resetUrl = $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

        try {
            Mail::to($user->email)->send(new ResetPasswordEmail($user->name, $resetUrl));
        } catch (\Exception $e) {
            \Log::error('Failed to send reset email: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mengirim email reset password.'], 500);
        }

        return response()->json(['message' => 'Jika email terdaftar, tautan reset password telah dikirim.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8'
        ]);

        $resetToken = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$resetToken || !Hash::check($request->token, $resetToken->token)) {
            return response()->json(['message' => 'Token reset password tidak valid atau sudah kadaluarsa.'], 400);
        }

        if (now()->diffInMinutes($resetToken->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Token reset password sudah kadaluarsa.'], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'raw_password' => $request->password, // Secure requirement per user
        ])->save();

        // Delete token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password berhasil direset. Silakan login dengan password baru.']);
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
