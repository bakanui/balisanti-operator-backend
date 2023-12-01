<div style="font-size:11pt;background-color:#fafafa;font-family:Arial,sans-serif;width:50vw;min-width:600px;margin-left:auto;margin-right:auto">
    <div style="text-align: center;">
        <img src="{{asset('logo-primary.png')}}" alt="" width="200" height="auto">
    </div>
    <br>
    <br>
    <p>Dear {{$data->nama_penumpang}}</p>
    <p>
        Email ini dikirimkan untuk memberikan informasi bahwa tiket kapal Anda untuk perjalanan dengan
        kode booking {{$data->kode_booking}} telah terbit dan terlampir pada email ini. Selain itu, terdapat QR code
        pada tiket Anda.
    </p>
    <p>Berikut adalah rincian tiket Anda:</p>
    <table>
        <tr>
            <td>Nama Penumpang</td>
            <td>:</td>
            <td>{{$data->nama_penumpang}}</td>
        </tr>
        <tr>
            <td>Tanggal Keberangkatan</td>
            <td>:</td>
            <td>{{$data->tanggal}}</td>
        </tr>
        <tr>
            <td>Waktu Keberangkatan</td>
            <td>:</td>
            <td>{{$data->waktu_berangkat}}</td>
        </tr>
        <tr>
            <td>Pelabuhan Keberangkatan</td>
            <td>:</td>
            <td>{{$data->dermaga_awal}}</td>
        </tr>
        <tr>
            <td>Pelabuhan Tujuan</td>
            <td>:</td>
            <td>{{$data->dermaga_tujuan}}</td>
        </tr>
    </table>
    <p>
        Mohon diperhatikan bahwa Anda diharapkan untuk tiba di pelabuhan keberangkatan minimal 1 jam
        sebelum waktu keberangkatan.
    </p>
    <div style="text-align: center;">
        {{QrCode::size(200)->generate('https://techvblogs.com/blog/generate-qr-code-laravel-9')}}
    </div>
    <p>
        Silakan gunakan gambar QR code dibawah ini atau lihat pada tiket Anda untuk mempercepat proses
        check-in di pelabuhan keberangkatan.
    </p>
    <br>
    <p>
        Terima kasih atas kepercayaan Anda dan selamat menikmati perjalanan!
    </p>
    <br>
    <p>Salam hormat,<br>Bali Santi</p>
</div>