@php($rp = fn ($v) => \App\Services\Rental\InvoiceService::rupiah((int) $v))
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Nota {{ $n['code'] }}</title>
<style>
  @page { margin: 34px 42px; }
  * { font-family: 'DejaVu Sans', sans-serif; }
  body { font-size: 10.5px; color: #14262d; line-height: 1.45; }
  h1 { font-size: 17px; margin: 0; color: #0f7b63; }
  .muted { color: #5b6f74; }
  .small { font-size: 8.5px; }
  .label { font-size: 7.5px; letter-spacing: .8px; text-transform: uppercase; color: #5b6f74; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; }
  td { vertical-align: top; padding: 2px 0; }
  .right { text-align: right; }
  .hr { border-top: 1px solid #dfe7e4; margin: 8px 0; }
  .dash { border-top: 1px dashed #c9d6d2; margin: 6px 0; }
  .badge { display: inline-block; padding: 1px 6px; border-radius: 4px; background: #e9f5f1; color: #0a5443; font-size: 8px; font-weight: bold; }
  .badge-warn { background: #fef3c7; color: #92400e; }
  .badge-red { background: #fee2e2; color: #991b1b; }
  .total td { font-size: 12px; font-weight: bold; }
  .box { background: #f4f7f6; border-radius: 6px; padding: 6px 8px; }
  .sign td { padding-top: 34px; text-align: center; }
  .sign .line { border-top: 1px solid #14262d; margin: 0 18px; padding-top: 3px; }
</style>
</head>
<body>
  <table>
    <tr>
      <td>
        <h1>{{ $n['business']['name'] }}</h1>
        <div class="muted">{{ $n['business']['tagline'] }}</div>
        @if($n['business']['address'])<div class="small muted">{{ $n['business']['address'] }}</div>@endif
        @if($n['business']['whatsapp'])<div class="small muted">WA {{ $n['business']['whatsapp'] }}</div>@endif
      </td>
      <td class="right">
        <div class="label">Nota Sewa</div>
        <div style="font-size:11px;font-weight:bold">{{ $n['code'] }}</div>
        <span class="badge {{ $n['status'] === 'terlambat' ? 'badge-red' : '' }}">{{ $n['status_label'] }}</span>
        <span class="badge {{ in_array($n['payment_status'], ['belum_lunas', 'belum_bayar', 'lunas_belum_denda']) ? 'badge-warn' : '' }}">{{ $n['payment_status_label'] }}</span>
        <div class="small muted">Dicetak {{ $n['issued_at'] }} WIB</div>
      </td>
    </tr>
  </table>

  <div class="hr"></div>

  <table>
    <tr>
      <td style="width:50%">
        <div class="label">Pelanggan</div>
        <div style="font-weight:bold;font-size:10.5px">{{ $n['customer']['name'] }}</div>
        <div>{{ $n['customer']['phone'] }}</div>
        <div class="small muted">KTP {{ $n['customer']['nik_masked'] }}</div>
      </td>
      <td>
        <div class="label">Kendaraan</div>
        <div style="font-weight:bold;font-size:10.5px">{{ $n['car']['name'] }}</div>
        <div>{{ $n['car']['plate_number'] }} · {{ $n['service_type_label'] }}</div>
        <div class="small muted">{{ $n['car']['year'] }} · {{ $n['car']['transmission'] }}@if($n['car']['color']) · {{ $n['car']['color'] }}@endif</div>
        @if($n['driver'])<div class="small">Driver: {{ $n['driver']['name'] }} ({{ $n['driver']['phone'] }})</div>@endif
      </td>
    </tr>
  </table>

  <div class="hr"></div>

  <table>
    <tr><td class="muted">Mulai (WIB)</td><td class="right">{{ $n['schedule']['start_at'] }}</td></tr>
    <tr><td class="muted">Kembali rencana (WIB)</td><td class="right">{{ $n['schedule']['planned_end_at'] }}</td></tr>
    @if($n['schedule']['returned_at'])<tr><td class="muted">Kembali aktual (WIB)</td><td class="right">{{ $n['schedule']['returned_at'] }}</td></tr>@endif
    @foreach($n['lines'] as $l)
      <tr>
        <td>{{ $l['label'] }}@if($l['detail'])<br><span class="small muted">{{ $l['detail'] }}</span>@endif</td>
        <td class="right">{{ $rp($l['amount']) }}</td>
      </tr>
    @endforeach
  </table>

  <div class="dash"></div>
  <table>
    <tr class="total"><td>Total tagihan</td><td class="right">{{ $rp($n['totals']['total']) }}</td></tr>
    <tr><td class="muted">Pembayaran diterima</td><td class="right">{{ $rp($n['totals']['paid']) }}</td></tr>
    <tr class="total"><td>Sisa tagihan</td><td class="right">{{ $rp($n['totals']['balance']) }}</td></tr>
    @if($n['totals']['running_late_fee'] > 0)
      <tr><td style="color:#991b1b">Estimasi denda berjalan</td><td class="right" style="color:#991b1b">{{ $rp($n['totals']['running_late_fee']) }}</td></tr>
    @endif
  </table>

  @if($n['deposit']['required'] > 0 || $n['deposit']['received'] > 0)
  <div class="hr"></div>
  <div class="label">Deposit / jaminan</div>
  <table>
    <tr><td class="muted">Diminta</td><td class="right">{{ $rp($n['deposit']['required']) }}</td></tr>
    <tr><td class="muted">Diterima</td><td class="right">{{ $rp($n['deposit']['received']) }}</td></tr>
    @if($n['deposit']['deducted'] > 0)<tr><td class="muted">Dipotong untuk tagihan</td><td class="right">{{ $rp(-$n['deposit']['deducted']) }}</td></tr>@endif
    @if($n['deposit']['refunded'] > 0)<tr><td class="muted">Dikembalikan</td><td class="right">{{ $rp(-$n['deposit']['refunded']) }}</td></tr>@endif
    <tr><td class="muted">Masih ditahan</td><td class="right">{{ $rp($n['deposit']['held']) }}</td></tr>
  </table>
  @endif

  @if(count($n['payments']))
  <div class="hr"></div>
  <div class="label">Riwayat pembayaran</div>
  <table>
    @foreach($n['payments'] as $p)
      <tr>
        <td>{{ $p['paid_at'] }} WIB · {{ $p['type_label'] }} · {{ $p['method_label'] }}@if($p['reference_number']) · {{ $p['reference_number'] }}@endif</td>
        <td class="right">{{ $p['direction'] === 'out' ? '− ' : '' }}{{ $rp($p['amount']) }}</td>
      </tr>
    @endforeach
  </table>
  @endif

  @if($n['inspections']['serah_terima'] || $n['inspections']['kembali'])
  <div class="hr"></div>
  <table>
    <tr>
      @foreach(['serah_terima' => 'Serah terima', 'kembali' => 'Kembali'] as $k => $label)
        <td style="width:50%">
          <div class="label">{{ $label }}</div>
          @if($i = $n['inspections'][$k])
            <div>Odometer {{ number_format($i['odometer'], 0, ',', '.') }} km · BBM {{ $i['fuel_label'] }}</div>
            @foreach($i['issues'] as $issue)<div class="small muted">• {{ $issue }}</div>@endforeach
          @else
            <div class="muted">-</div>
          @endif
        </td>
      @endforeach
    </tr>
  </table>
  @endif

  @if($n['note'])<div class="box small" style="margin-top:8px">{{ $n['note'] }}</div>@endif

  @if(count($n['terms']))
  <div class="label" style="margin-top:8px">Syarat singkat</div>
  <ol class="small muted" style="margin:2px 0 0 14px;padding:0">
    @foreach($n['terms'] as $t)<li>{{ $t }}</li>@endforeach
  </ol>
  @endif

  <table class="sign">
    <tr>
      <td style="width:50%"><div class="line">Penyewa<br><span class="muted">{{ $n['customer']['name'] }}</span></div></td>
      <td style="width:50%"><div class="line">Petugas<br><span class="muted">{{ $n['business']['name'] }}</span></div></td>
    </tr>
  </table>

  <p class="right small muted" style="margin-top:10px">{{ $n['footer'] }}</p>
</body>
</html>
