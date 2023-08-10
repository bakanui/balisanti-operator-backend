<div style="font-size:11pt;background-color:#fafafa;font-family:Arial,sans-serif;width:50vw;min-width:600px;margin-left:auto;margin-right:auto">
    <div style="text-align: center;">
        <img src="{{$message->embed(public_path().'/logo-primary.png')}}" alt="" width="200" height="auto">
    </div>
    <br>
    <br>
    <p>Dear {{$data['nama_penumpang']}}</p>
    <p>
        This email was sent to inform you that your boat ticket with booking code {{$data['kode_booking']}} 
        for your upcoming travel has been issued and is attached to this email.
    </p>
    <p>Below are your ticket details:</p>
    <table style="font-size:11pt;font-family:Arial,sans-serif;">
        <tr>
            <td>Passenger Name</td>
            <td>:</td>
            <td>{{$data['nama_penumpang']}}</td>
        </tr>
        <tr>
            <td>Departure Date</td>
            <td>:</td>
            <td>{{$data['tanggal']}}</td>
        </tr>
        <tr>
            <td>Departure Time</td>
            <td>:</td>
            <td>{{$data['waktu_berangkat']}}</td>
        </tr>
        <tr>
            <td>Departure Port</td>
            <td>:</td>
            <td>{{$data['dermaga_awal']}}</td>
        </tr>
        <tr>
            <td>Destination Port</td>
            <td>:</td>
            <td>{{$data['dermaga_tujuan']}}</td>
        </tr>
    </table>
    <p>
        Please note that you are expected to arrive at the departure port at least 1 hour before the departure
        time.
    </p>
    <p>
        Please use the QR code image above or look at your ticket to speed up the check-in process at the
        departure port
    </p>
    <div style="text-align: center;">
        <img src="{!!$message->embed(public_path().'/storage/img/qrcodes/'.$data['kode_booking'].'.png')!!}">
    </div>
    <br>
    <p>
        Thank you for your trust in us and have a nice trip!
    </p>
    <br>
    <p>Best regards,<br>Wahana Virendra</p>
</div>