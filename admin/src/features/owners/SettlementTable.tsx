import { cn } from '@/lib/cn'
import { rupiah } from '@/lib/format'
import type { SettlementCalc, SettlementLine } from './api'

type Totals = Pick<SettlementCalc, 'income' | 'maintenance' | 'expenses' | 'net' | 'base_amount' | 'owner_amount' | 'business_amount' | 'share_basis'> & { share_label: string }

const Money = ({ v, bold }: { v: number; bold?: boolean }) => <span className={cn('tabular-nums', v < 0 && 'text-red-700', bold && 'font-bold')}>{rupiah(v)}</span>

/** Rincian per mobil + ringkasan bagi hasil (dipakai di pratinjau dan detail setoran). */
export function SettlementTable({ lines, t }: { lines: SettlementLine[]; t: Totals }) {
  const net = t.share_basis === 'bersih'
  return (
    <div className="space-y-3">
      <div className="overflow-x-auto rounded-xl border border-line">
        <table className="w-full min-w-[560px] text-sm">
          <thead>
            <tr className="border-b border-line bg-canvas/60 text-left text-2xs font-bold tracking-[0.1em] text-ink-muted uppercase">
              <th className="px-3 py-2">Mobil</th><th className="px-3 py-2 text-right">Sewa</th><th className="px-3 py-2 text-right">Pendapatan</th>
              <th className="px-3 py-2 text-right">Perawatan</th><th className="px-3 py-2 text-right">Pengeluaran</th><th className="px-3 py-2 text-right">Laba</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {lines.map((l) => (
              <tr key={l.car_id}>
                <td className="px-3 py-2"><span className="font-semibold">{l.car}</span><br /><span className="text-2xs text-ink-muted">{l.plate_number}</span></td>
                <td className="px-3 py-2 text-right text-xs text-ink-soft">{l.rentals}× · {l.billed_days} hari</td>
                <td className="px-3 py-2 text-right"><Money v={l.income} /></td>
                <td className={cn('px-3 py-2 text-right', !net && 'text-ink-muted')}><Money v={l.maintenance} /></td>
                <td className={cn('px-3 py-2 text-right', !net && 'text-ink-muted')}><Money v={l.expenses} /></td>
                <td className="px-3 py-2 text-right"><Money v={l.net} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <dl className="grid gap-x-6 gap-y-1.5 rounded-xl bg-canvas p-4 text-sm sm:grid-cols-2">
        <div className="flex justify-between"><dt className="text-ink-soft">Pendapatan diterima</dt><dd><Money v={t.income} /></dd></div>
        <div className="flex justify-between"><dt className="text-ink-soft">Perawatan + pengeluaran</dt><dd><Money v={t.maintenance + t.expenses} /></dd></div>
        <div className="flex justify-between"><dt className="text-ink-soft">Dasar bagi hasil ({net ? 'laba bersih' : 'pendapatan kotor'})</dt><dd><Money v={t.base_amount} bold /></dd></div>
        <div className="flex justify-between"><dt className="text-ink-soft">Bagian rental</dt><dd><Money v={t.business_amount} /></dd></div>
        <div className="flex items-baseline justify-between border-t border-line pt-2 sm:col-span-2">
          <dt className="font-semibold text-ink">Bagian pemilik <span className="font-normal text-ink-muted">({t.share_label})</span></dt>
          <dd className="text-xl font-extrabold text-brand-700 tabular-nums">{rupiah(t.owner_amount)}</dd>
        </div>
        {t.base_amount < 0 && <p className="text-xs text-red-700 sm:col-span-2">Periode ini rugi, jadi bagian pemilik Rp 0. Kerugian tidak dibawa ke periode berikutnya.</p>}
        {!net && (t.maintenance + t.expenses > 0) && <p className="text-xs text-ink-muted sm:col-span-2">Dasar kotor: biaya perawatan & pengeluaran ditanggung rental, tidak mengurangi bagian pemilik.</p>}
      </dl>
    </div>
  )
}
