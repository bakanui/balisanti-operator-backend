<b>Halo, {{$data['name']}}!</b>
<p>
Ini contoh email lupa password. Silahkan klik link berikut untuk verifikasi: {{env('APP_URL')}}/api/user/reset-password?keyFP={{$data['keyFP']}}
</p>
<p>
Email ini dikirimkan secara otomatis oleh sistem, kami tidak melakukan pengecekan email yang dikirimkan ke email ini. Mohon untuk tidak membalas email ini.
</p>
<p>
Terima kasih,<br>
</p>
