<x-mail::message>
# Verifikasi Email Anda

Halo **{{ $userName }}**,

Terima kasih telah mendaftar di portal **Nulumbung**. Untuk mulai menggunakan layanan kami dan mendapatkan akses penuh, Anda perlu memverifikasi alamat email ini.

<x-mail::button :url="$verificationUrl" color="primary">
Verifikasi Email Saya
</x-mail::button>

Jika Anda ragu atau tidak merasa mendaftar di Nulumbung, silakan abaikan email ini.

Terima kasih,<br>
Tim {{ config('app.name') }}
</x-mail::message>
