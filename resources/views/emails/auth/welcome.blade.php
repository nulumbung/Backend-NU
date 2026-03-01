<x-mail::message>
# Selamat Datang di Nulumbung!

Halo **{{ $userName }}**,

Selamat! Akun Anda telah berhasil diverifikasi dan kini bergabung menjadi bagian dari komunitas Nulumbung. 

Kini Anda dapat:
- Mengakses konten eksklusif.
- Meninggalkan komentar dan berdiskusi.
- Menyimpan artikel favorit.

<x-mail::button :url="config('app.frontend_url')" color="primary">
Jelajahi Nulumbung Sekarang
</x-mail::button>

Kami sangat senang Anda ada di sini.

Terima kasih,<br>
Tim {{ config('app.name') }}
</x-mail::message>
