<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ public_path('css/pdfinvoice.css') }}" data-precedence="high">
    <link href="{{ public_path('css/pdfinvoice.css') }}" rel="stylesheet">

    <title>Invoice___</title>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('logo-primary.png') }}" style="height:80px; margin-top:20px;" alt="logo">
    </div>
    <div class="container">
        <div class="w-full h-auto mr-4 bg-white p-2 sm:p-8 rounded-xl">
            <div class="flex items-center">
                <table class="no-border">
                    <tr>
                        <td>INVOICE</td>
                        <td><div class="{{$pembayaran['sisa_tagihan'] > 0 ? 'belumlunas' : 'lunas'}}">{{$pembayaran > 0 ? 'Belum Lunas' : 'Lunas'}}</div></td>
                    </tr>
                </table>
            </div>
            <div class="sm:grid gap-x-6 grid-cols-3 mt-6">
                <table class="no-border">
                    <tr>
                        <td>
                            <div class="text-xs font-robotoregular mt-2">No Invoice</div>
                            <div class="text-[18px] font-robotomedium">{{$pembayaran['no_invoice']}}</div>
                        </td>
                        <td>
                            <div class="text-xs font-robotoregular mt-2">Tanggal</div>
                            <div class="text-[18px] font-robotomedium">{{$tanggal_pembuatan}}</div>
                        </td>
                        <td>
                            <div class="text-xs font-robotoregular mt-2">Nama Agen</div>
                            <div class="text-[18px] font-robotomedium">{{$agen ? $agen->nama_agen : '-'}}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="text-xs font-robotoregular mt-2">Dermaga Asal</div>
                            <div class="text-[18px] font-robotomedium">{{$penumpang[0]->dermaga_awal}}</div>
                        </td>
                        <td>
                            <div class="text-xs font-robotoregular mt-2">Dermaga Tujuan</div>
                            <div class="text-[18px] font-robotomedium">{{$penumpang[0]->dermaga_tujuan}}</div>
                        </td>
                        <td>
                            <div class="text-xs font-robotoregular mt-2">Nama Kapal</div>
                            <div class="text-[18px] font-robotomedium">{{$penumpang[0]->nama_kapal}}</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="text-xs font-robotoregular mt-2">Waktu Keberangkatan</div>
                            <div class="text-[18px] font-robotomedium">{{$penumpang[0]->waktu_berangkat}}</div>
                        </td>
                        <td></td>
                    </tr>
                </table>
            </div>
            <br>
            <h3>Penumpang</h3>
            <table class="penumpang">
                <thead class="border border-[black] border-x-0 ">
                    <tr>
                        <th class="text-sm font-robotomedium pl-4 py-2" style="width:30px;">No</th>
                        <th class="text-sm font-robotomedium py-2" style="width:170px;">Nama</th>
                        <th class="text-sm font-robotomedium py-2" style="width:150px;">No. Identitas</th>
                        <th class="text-sm font-robotomedium py-2" style="width:150px;">Jenis Kelamin</th>
                        <th class="text-sm font-robotomedium py-2" style="width:150px;">Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($penumpang as $key => $p) { ?>
                    <tr class="text-sm font-robotoregular">
                        <td class="pl-4 py-2">{{$key+1}}</td>
                        <td class="py-2">{{$p->nama_penumpang}}</td>
                        <td class="py-2">{{$p->no_identitas}}</td>
                        <td class="py-2">{{$p->jenis_kelamin == 'l' ? 'Laki-laki' : 'Perempuan'}}</td>
                        <td class="py-2">{{$p->email}}</td>
                    </tr>
                    <?php } ?>
                    <tr class="border border-[black] border-x-0"></tr>
                </tbody>
            </table>
            <br>
            <h3>Detail Tagihan</h3>
            <table class="penumpang">
                <thead class="border border-[black] border-x-0 ">
                    <tr>
                        <th class="text-sm font-robotomedium pl-4 py-2" style="width:30px;">No</th>
                        <th class="text-sm font-robotomedium py-2" style="width:170px;">Keterangan</th>
                        <th class="text-sm font-robotomedium py-2" style="width:150px;">Jenis Penumpang</th>
                        <th class="text-sm font-robotomedium py-2" style="width:30px;">Qty</th>
                        <th class="text-sm font-robotomedium py-2" style="width:90px;">Tarif</th>
                        <th class="text-sm font-robotomedium py-2" style="width:80px;">Diskon</th>
                        <th class="text-sm font-robotomedium py-2" style="width:100px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="text-sm font-robotoregular undefined undefined">
                        <td class="pl-4 py-2">1</td>
                        <td class="py-2">Tiket 
                            <span class="font-robotoregular text-primary">{{$data[0]->is_pp == 'true' ? ' - (Pulang Pergi)' : ''}}</span>
                        </td>
                        <td class="py-2">{{$data[0]->jenis_penumpang}}</td>
                        <td class="py-2">{{$data[0]->jumlah_tiket}}</td>
                        <td class="py-2">Rp. {{ number_format($data[0]->harga_tiket, 0, '', '.') }}</td>
                        <td class="py-2">Rp. {{ number_format($data[0]->diskon_agen, 0, '', '.') }}</td>
                        <td class="py-2">Rp. {{ number_format($data[0]->subtotal_tiket - $data[0]->subtotal_diskon, 0, '', '.') }}</td>
                    </tr>
                    <tr class="text-sm font-robotoregular bg-[#F0F0F0] dark:bg-slate-500 undefined">
                        <td class="pl-4 py-2">2</td>
                        <td class="py-2">Penjemputan </td>
                        <td class="py-2">-</td>
                        <td class="py-2">{{$data[0]->jumlah_service}}</td>
                        <td class="py-2">Rp. {{ number_format($data[0]->harga_service, 0, '', '.') }}</td>
                        <td class="py-2">-</td>
                        <td class="py-2">Rp. {{ number_format($data[0]->subtotal_service, 0, '', '.') }}</td>
                    </tr>
                    <tr class="text-sm font-robotoregular bg-[#F0F0F0] dark:bg-slate-500 undefined">
                        <td class="pl-4 py-2">3</td>
                        <td class="py-2">Collect </td>
                        <td class="py-2">-</td>
                        <td class="py-2">1</td>
                        @if (is_null($collect))
                        <td class="py-2">Rp. {{ number_format(0, 0, '', '.') }}</td>
                        <td class="py-2">-</td>
                        <td class="py-2">Rp. {{ number_format(0, 0, '', '.') }}</td>
                        @else
                        <td class="py-2">Rp. {{ number_format($collect->jumlah, 0, '', '.') }}</td>
                        <td class="py-2">-</td>
                        <td class="py-2">Rp. {{ number_format($collect->jumlah, 0, '', '.') }}</td>
                        @endif
                    </tr>
                    <tr class="text-sm font-robotoregular border border-[black] border-x-0">
                        <td class="pl-4 py-2"></td>
                        <td colspan="4" class="font-robotobold text-lg">TOTAL</td>
                        @if (is_null($collect))
                        <td colspan="2" class="text-lg py-1 font-robotobold">Rp. {{ number_format($data[0]->subtotal, 0, '', '.') }}</td>
                        @else
                        <td colspan="2" class="text-lg py-1 font-robotobold">Rp. {{ number_format($data[0]->subtotal + (double) $collect->jumlah, 0, '', '.') }}</td>
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>