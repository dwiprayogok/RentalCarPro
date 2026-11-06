@php($rp = fn ($v) => \App\Services\Owner\SettlementService::rupiah((int) $v))
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Slip Bagi Hasil {{ $n['code'] }}</title>
<style>
  @page { margin: 34px 42px; }
  * { font-family: 'DejaVu Sans', sans-serif; }
  body { font-size: 10px; color: #14262d; line-height: 1.45; }
  h1 { font-size: 17px; margin: 0; color: #0f7b63; }
  .muted { color: #5b6f74; }
  .small { font-size: 8.5px; }
  .label { font-size: 7.5px; letter-spacing: .8px; text-transform: uppercase; color: #5b6f74; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; padding: 3px 0; }
  .right { text-align: right; }
  .hr { border-top: 1px solid #dfe7e4; margin: 8px 0; }
  .grid th { font-size: 7.5px; letter-spacing: .6px; text-transform: uppercase; color: #5b6f74; text-align: left; border-bottom: 1px solid #dfe7e4; padding: 4px 4px; }
  .grid td { border-bottom: 1px solid #eef2f1; padding: 4px 4px; }
  .grid .right, .grid th.right { text-align: right; }
  .badge { display: inline-block; padding: 1px 6px; border-radius: 4px; background: #e9f5f1; color: #0a5443; font-size: 8px; font-weight: bold; }
  .badge-warn { background: #fef3c7; color: #92400e; }
  .box { background: #f4f7f6; border-radius: 6px; padding: 8px 10px; }
  .total td { font-size: 12px; font-weight: bold; padding-top: 6px; }
  .neg { color: #b42318; }
  .sign td { padding-top: 34px; text-align: center; }
  .sign .line { border-top: 1px solid #14262d; margin: 0 18px; padding-top: 3px; }
</style>
</head>
<body>
  <table>
    <tr>
      <td>
        <h1>{{ $n['business']['name'] }}</h1>
        @if($n['business']['address'])<div class="small muted">{{ $n['business']['address'] }}</div>@endif
        @if($n['business']['whatsapp'])<div class="small muted">WA {{ $n['business']['whatsapp'] }}</div>@endif
      </td>
      <td class="right">
        <div class="label">Slip Bagi Hasil Mobil Titipan</div>
        <div style="font-size:11px;font-weight:bold">{{ $n['code'] }}</div>
        <span class="badge {{ $n['paid'] ? '' : 'badge-warn' }}">{{ $n['paid'] ? 'Sudah dibayar' : 'Belum dibayar' }}</span>
        <div class="small muted">Dicetak {{ $n['issued_at'] }} WIB</div>
      </td>
    </tr>
  </table>

  <div class="hr"></div>

  <table>
    <tr>
      <td style="width:50%">
        <div class="label">Pemilik</div>
        <div style="font-weight:bold;font-size:10.5px">{{ $n['owner']['name'] }}</div>
        <div>{{ $n['owner']['phone'] }}</div>
        @if($n['owner']['bank'])<div class="small muted">{{ $n['owner']['bank'] }}</div>@endif
      </td>
      <td>
        <div class="label">Periode</div>
        <div style="font-weight:bold">{{ $n['period'] }}</div>
        <div class="label" style="margin-top:4px">Ketentuan</div>
        <div>Bagian pemilik {{ $n['share_label'] }}</div>
      </td>
    </tr>
  </table>

  <div class="hr"></div>

  <table class="grid">
    <thead>
      <tr>
        <th>Mobil</th><th class="right">Sewa</th><th class="right">Pendapatan</th><th class="right">Perawatan</th><th class="right">Pengeluaran</th><th class="right">Laba</th>
      </tr>
    </thead>
    <tbody>
      @foreach($n['lines'] as $l)
        <tr>
          <td><b>{{ $l['car'] }}</b><br><span class="small muted">{{ $l['plate_number'] }}</span></td>
          <td class="right">{{ $l['rentals'] }}× · {{ $l['billed_days'] }} hr</td>
          <td class="right">{{ $rp($l['income']) }}</td>
          <td class="right">{{ $rp($l['maintenance']) }}</td>
          <td class="right">{{ $rp($l['expenses']) }}</td>
          <td class="right {{ $l['net'] < 0 ? 'neg' : '' }}">{{ $rp($l['net']) }}</td>
        </tr>
      @endforeach
      <tr>
        <td><b>Total</b></td><td></td>
        <td class="right"><b>{{ $rp($n['income']) }}</b></td>
        <td class="right"><b>{{ $rp($n['maintenance']) }}</b></td>
        <td class="right"><b>{{ $rp($n['expenses']) }}</b></td>
        <td class="right {{ $n['net'] < 0 ? 'neg' : '' }}"><b>{{ $rp($n['net']) }}</b></td>
      </tr>
    </tbody>
  </table>

  <div style="height:10px"></div>
  <table>
    <tr>
      <td style="width:46%" class="small muted">
        Pendapatan = uang sewa yang diterima pada periode ini (DP, pelunasan, denda, potongan deposit, dikurangi refund). Deposit titipan pelanggan tidak dihitung.
        @if($n['share_basis'] === 'bersih') Dasar bagi hasil = laba setelah biaya perawatan & pengeluaran mobil. @else Dasar bagi hasil = pendapatan kotor; biaya perawatan & pengeluaran ditanggung rental. @endif
        @if($n['base_amount'] < 0) Periode ini rugi, sehingga bagian pemilik Rp 0. @endif
      </td>
      <td style="width:4%"></td>
      <td>
        <div class="box">
          <table>
            <tr><td>Dasar bagi hasil</td><td class="right">{{ $rp($n['base_amount']) }}</td></tr>
            <tr><td>Bagian rental</td><td class="right">{{ $rp($n['business_amount']) }}</td></tr>
            <tr class="total"><td>Bagian pemilik</td><td class="right">{{ $rp($n['owner_amount']) }}</td></tr>
          </table>
          @if($n['paid'])
            <div class="small muted" style="margin-top:4px">Dibayar {{ $n['paid_at'] }} WIB{{ $n['paid_method'] ? ' via '.$n['paid_method'] : '' }}{{ $n['paid_reference'] ? ' · Ref '.$n['paid_reference'] : '' }}</div>
          @endif
        </div>
      </td>
    </tr>
  </table>

  @if($n['notes'])
    <div style="margin-top:8px"><span class="label">Catatan</span><div>{{ $n['notes'] }}</div></div>
  @endif

  <table class="sign">
    <tr>
      <td style="width:50%"><div class="line">{{ $n['business']['name'] }}</div></td>
      <td><div class="line">{{ $n['owner']['name'] }}</div></td>
    </tr>
  </table>
</body>
</html>
