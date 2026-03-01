<x-mail::message>
# Halo, {{ $name }}

Kami menerima permintaan untuk mereset kata sandi akun Anda di NU Lumbung.

Silakan klik tombol di bawah ini untuk membuat kata sandi baru Anda:

<x-mail::button :url="$resetUrl" color="success">
Reset Kata Sandi
</x-mail::button>

Jika Anda tidak membuat permintaan ini, Anda dapat mengabaikan email ini dengan aman.

Terima kasih,<br>
Tim {{ config('app.name') }}
</x-mail::message>
