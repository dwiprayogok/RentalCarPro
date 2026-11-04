import { Download } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { useSearchParams } from 'react-router'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Feedback'
import { Field, Input, Select } from '@/components/ui/Field'
import { FilterChips } from '@/components/ui/FilterChips'
import { Pagination } from '@/components/ui/Pagination'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { useCarOptions } from '@/features/rentals/options'
import { errorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import { todayWib } from '@/lib/datetime'
import { dateOnly, dateTimeWib, number, rupiah } from '@/lib/format'
import type { PaginationMeta } from '@/lib/types'
import { downloadReportCsv, useReport, type ReportFilters, type ReportTab } from './api'

const TABS: { value: ReportTab; label: string }[] = [
  { value: 'pembayaran', label: 'Pembayaran masuk' },
  { value: 'piutang', label: 'Piutang' },
  { value: 'pendapatan', label: 'Pendapatan per mobil' },
  { value: 'utilisasi', label: 'Utilisasi armada' },
  { value: 'servis', label: 'Biaya servis' },
]

/** Rentang preset dalam tanggal WIB. */
function preset(kind: string): { from: string; to: string } {
  const today = todayWib()
  const [y, m, d] = today.split('-').map(Number)
  const fmt = (dt: Date) => dt.toISOString().slice(0, 10)
  const utc = (yy: number, mm: number, dd: number) => new Date(Date.UTC(yy, mm, dd))
  switch (kind) {
    case '7': return { from: fmt(utc(y, m - 1, d - 6)), to: today }
    case '30': return { from: fmt(utc(y, m - 1, d - 29)), to: today }
    case 'bulan_lalu': return { from: fmt(utc(y, m - 2, 1)), to: fmt(utc(y, m - 1, 0)) }
    default: return { from: fmt(utc(y, m - 1, 1)), to: today }
  }
}

function Stat({ label, value, hint, tone }: { label: string; value: ReactNode; hint?: ReactNode; tone?: 'red' | 'amber' }) {
  return (
    <div className="card px-4 py-3">
      <p className="text-xs font-semibold text-ink-muted">{label}</p>
      <p className={cn('mt-0.5 text-xl font-extrabold tabular-nums', tone === 'red' ? 'text-red-700' : tone === 'amber' ? 'text-amber-700' : 'text-ink')}>{value}</p>
      {hint && <p className="text-2xs text-ink-muted">{hint}</p>}
    </div>
  )
}

/** Batang proporsional satu warna di dalam sel tabel (magnitudo). */
function Meter({ value, max, label }: { value: number; max: number; label: string }) {
  const pct = max > 0 ? Math.max(0, Math.min(100, (value / max) * 100)) : 0
  return (
    <div className="flex items-center gap-2">
      <div className="h-2 w-24 shrink-0 overflow-hidden rounded-full bg-canvas" aria-hidden><div className="h-full rounded-full" style={{ width: `${pct}%`, background: '#0E8A6C' }} /></div>
      <span className="text-xs font-semibold tabular-nums">{label}</span>
    </div>
  )
}

function Table({ head, children }: { head: string[]; children: ReactNode }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[640px] text-sm">
        <thead><tr className="border-b border-line text-left text-2xs font-bold tracking-[0.12em] text-ink-muted uppercase">{head.map((h, i) => <th key={h} className={cn('px-4 py-3', i > 0 && /Jumlah|Total|Pendapatan|Sisa|Denda|Terbayar|Biaya|Hari|Sewa|Utilisasi/.test(h) && 'text-right')}>{h}</th>)}</tr></thead>
        <tbody className="divide-y divide-line">{children}</tbody>
      </table>
    </div>
  )
}
const Td = ({ children, right, className }: { children: ReactNode; right?: boolean; className?: string }) => <td className={cn('px-4 py-2.5', right && 'text-right tabular-nums', className)}>{children}</td>

/* eslint-disable @typescript-eslint/no-explicit-any */
function PaymentsTab({ f, onPage }: { f: ReportFilters; onPage: (p: number) => void }) {
  const q = useReport<any>('pembayaran', f)
  if (q.isLoading) return <Skeleton className="h-64" />
  if (q.isError) return <div className="card"><ErrorState error={q.error} onRetry={() => q.refetch()} /></div>
  const s = q.data.summary
  return (
    <div className={cn('space-y-4', q.isPlaceholderData && 'opacity-60')}>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Pendapatan diterima" value={rupiah(s.income_total)} hint="DP + pelunasan + potong deposit − refund" />
        {s.by_method.filter((m: any) => m.method !== 'deposit').map((m: any) => <Stat key={m.method} label={m.label} value={rupiah(m.income)} hint={`${m.count} transaksi`} />)}
      </div>
      <div className="card flex flex-wrap gap-x-6 gap-y-1 px-4 py-3 text-xs text-ink-soft">
        <span>Dari deposit: <strong>{rupiah(s.by_method.find((m: any) => m.method === 'deposit')?.income ?? 0)}</strong></span>
        <span>Refund: <strong>{rupiah(s.by_type.refund)}</strong></span>
        <span className="text-ink-muted">Deposit titipan (tidak dihitung pendapatan): masuk {rupiah(s.deposit_flow.received)} · dikembalikan {rupiah(s.deposit_flow.returned)}</span>
      </div>
      <div className="card overflow-hidden">
        {q.data.data.length === 0 ? <EmptyState title="Tidak ada pembayaran" description="Tidak ada pembayaran pada periode & filter ini." /> : (
          <>
            <Table head={['Tanggal bayar', 'Nota', 'Pelanggan', 'Mobil', 'Jenis', 'Metode', 'Jumlah']}>
              {q.data.data.map((p: any) => (
                <tr key={p.id}>
                  <Td className="text-xs text-ink-soft">{dateTimeWib(p.paid_at)}</Td>
                  <Td className="font-semibold">{p.code}</Td>
                  <Td>{p.customer_name}</Td>
                  <Td className="text-xs">{p.car}<br /><span className="text-ink-muted">{p.plate_number}</span></Td>
                  <Td><Badge tone={p.income > 0 ? 'green' : p.income < 0 ? 'red' : 'gray'} dot={false}>{p.type_label}</Badge></Td>
                  <Td className="text-xs">{p.method_label}{p.reference_number && <><br /><span className="text-ink-muted">{p.reference_number}</span></>}</Td>
                  <Td right className={p.income < 0 ? 'text-red-700' : p.income === 0 ? 'text-ink-muted' : ''}>{p.income < 0 ? '− ' : ''}{rupiah(p.amount)}</Td>
                </tr>
              ))}
            </Table>
            <div className="border-t border-line px-4 pb-4"><Pagination meta={{ ...q.data.meta, from: q.data.meta.from_row, to: q.data.meta.to_row } as PaginationMeta} onPage={onPage} /></div>
          </>
        )}
      </div>
    </div>
  )
}

function ReceivablesTab({ f, onPage }: { f: ReportFilters; onPage: (p: number) => void }) {
  const q = useReport<any>('piutang', f)
  if (q.isLoading) return <Skeleton className="h-64" />
  if (q.isError) return <div className="card"><ErrorState error={q.error} onRetry={() => q.refetch()} /></div>
  const s = q.data.summary
  return (
    <div className={cn('space-y-4', q.isPlaceholderData && 'opacity-60')}>
      <p className="text-xs text-ink-muted">Piutang dihitung per saat ini (tidak dibatasi periode). Filter mobil tetap berlaku.</p>
      <div className="grid gap-3 sm:grid-cols-3">
        <Stat label="Sisa tagihan" value={rupiah(s.balance_total)} hint={`${s.count} transaksi belum lunas`} tone={s.balance_total ? 'amber' : undefined} />
        <Stat label="Estimasi denda berjalan" value={rupiah(s.running_late_total)} hint="Sewa aktif yang terlambat" tone={s.running_late_total ? 'red' : undefined} />
        <Stat label="Total piutang" value={rupiah(s.grand_total)} />
      </div>
      <div className="card overflow-hidden">
        {q.data.data.length === 0 ? <EmptyState title="Tidak ada piutang" description="Semua tagihan sudah lunas." /> : (
          <>
            <Table head={['Nota', 'Pelanggan', 'Mobil', 'Mulai', 'Total', 'Terbayar', 'Sisa', 'Denda berjalan']}>
              {q.data.data.map((r: any) => (
                <tr key={r.rental_id}>
                  <Td><span className="font-semibold">{r.code}</span><br /><Badge tone={r.status === 'terlambat' ? 'red' : r.status === 'selesai' ? 'green' : 'blue'}>{r.status_label}</Badge></Td>
                  <Td>{r.customer_name}<br /><span className="text-2xs text-ink-muted">{r.customer_phone}</span></Td>
                  <Td className="text-xs">{r.car}<br /><span className="text-ink-muted">{r.plate_number}</span></Td>
                  <Td className="text-xs text-ink-soft">{dateTimeWib(r.start_at)}<br /><span className="text-ink-muted">{r.age_days} hari lalu</span></Td>
                  <Td right>{rupiah(r.total)}</Td>
                  <Td right>{rupiah(r.paid)}</Td>
                  <Td right className="font-semibold text-amber-700">{rupiah(r.balance)}</Td>
                  <Td right className={r.running_late_fee ? 'font-semibold text-red-700' : 'text-ink-muted'}>{rupiah(r.running_late_fee)}</Td>
                </tr>
              ))}
            </Table>
            <div className="border-t border-line px-4 pb-4"><Pagination meta={q.data.meta} onPage={onPage} /></div>
          </>
        )}
      </div>
    </div>
  )
}

function RevenueTab({ f }: { f: ReportFilters }) {
  const q = useReport<any>('pendapatan', f)
  if (q.isLoading) return <Skeleton className="h-64" />
  if (q.isError) return <div className="card"><ErrorState error={q.error} onRetry={() => q.refetch()} /></div>
  const max = Math.max(0, ...q.data.data.map((r: any) => r.income))
  return (
    <div className={cn('space-y-4', q.isPlaceholderData && 'opacity-60')}>
      <div className="grid gap-3 sm:grid-cols-2"><Stat label="Total pendapatan diterima" value={rupiah(q.data.summary.income_total)} /><Stat label="Sewa dimulai di periode" value={number(q.data.summary.rentals)} /></div>
      <div className="card overflow-hidden">
        <Table head={['Mobil', 'Pendapatan', 'Sewa', 'Hari sewa', 'Denda', 'Biaya tambahan']}>
          {q.data.data.map((r: any) => (
            <tr key={r.car_id}>
              <Td><span className="font-semibold">{r.car}</span><br /><span className="text-2xs text-ink-muted">{r.plate_number}</span></Td>
              <Td right><div className="flex justify-end"><Meter value={r.income} max={max} label={rupiah(r.income)} /></div></Td>
              <Td right>{r.rentals}</Td><Td right>{r.billed_days}</Td><Td right>{rupiah(r.late_fees)}</Td><Td right>{rupiah(r.extra_fees)}</Td>
            </tr>
          ))}
        </Table>
      </div>
      <p className="text-2xs text-ink-muted">Pendapatan dihitung per tanggal bayar. Jumlah sewa & hari sewa dihitung dari sewa yang dimulai dalam periode.</p>
    </div>
  )
}

function UtilizationTab({ f }: { f: ReportFilters }) {
  const q = useReport<any>('utilisasi', f)
  if (q.isLoading) return <Skeleton className="h-64" />
  if (q.isError) return <div className="card"><ErrorState error={q.error} onRetry={() => q.refetch()} /></div>
  return (
    <div className={cn('space-y-4', q.isPlaceholderData && 'opacity-60')}>
      <div className="grid gap-3 sm:grid-cols-2"><Stat label="Rata-rata utilisasi" value={`${q.data.summary.average_pct.toLocaleString('id-ID')}%`} /><Stat label="Panjang periode" value={`${q.data.summary.period_days.toLocaleString('id-ID')} hari`} hint="dibatasi sampai saat ini" /></div>
      <div className="card overflow-hidden">
        <Table head={['Mobil', 'Utilisasi', 'Hari tersewa', 'Sewa']}>
          {q.data.data.map((r: any) => (
            <tr key={r.car_id}>
              <Td><span className="font-semibold">{r.car}</span><br /><span className="text-2xs text-ink-muted">{r.plate_number}</span></Td>
              <Td right><div className="flex justify-end"><Meter value={r.utilization_pct} max={100} label={`${r.utilization_pct.toLocaleString('id-ID')}%`} /></div></Td>
              <Td right>{r.rented_days.toLocaleString('id-ID')}</Td><Td right>{r.rentals}</Td>
            </tr>
          ))}
        </Table>
      </div>
      <p className="text-2xs text-ink-muted">Utilisasi = waktu mobil benar-benar keluar (serah terima s/d kembali; sewa aktif s/d sekarang) dibanding total waktu periode.</p>
    </div>
  )
}

function MaintenanceTab({ f }: { f: ReportFilters }) {
  const q = useReport<any>('servis', f)
  if (q.isLoading) return <Skeleton className="h-64" />
  if (q.isError) return <div className="card"><ErrorState error={q.error} onRetry={() => q.refetch()} /></div>
  const s = q.data.summary
  return (
    <div className={cn('space-y-4', q.isPlaceholderData && 'opacity-60')}>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Total biaya servis" value={rupiah(s.total)} hint={`${s.records} catatan`} />
        {s.by_type.filter((t: any) => t.total > 0).slice(0, 3).map((t: any) => <Stat key={t.type} label={t.label} value={rupiah(t.total)} />)}
      </div>
      <div className="card overflow-hidden">
        {q.data.data.length === 0 ? <EmptyState title="Tidak ada biaya servis" description="Tidak ada catatan perawatan pada periode ini." /> : (
          <Table head={['Mobil', 'Catatan', 'Total biaya']}>
            {q.data.data.map((r: any) => (
              <tr key={r.car_id}><Td><span className="font-semibold">{r.car}</span><br /><span className="text-2xs text-ink-muted">{r.plate_number}</span></Td><Td right>{r.records}</Td><Td right className="font-semibold">{rupiah(r.total)}</Td></tr>
            ))}
          </Table>
        )}
      </div>
    </div>
  )
}
/* eslint-enable @typescript-eslint/no-explicit-any */

export function ReportsPage() {
  const toast = useToast()
  const [params, setParams] = useSearchParams()
  const def = preset('bulan_ini')
  const tab = (params.get('tab') ?? 'pembayaran') as ReportTab
  const f: ReportFilters = {
    from: params.get('from') ?? def.from,
    to: params.get('to') ?? def.to,
    method: params.get('method') ?? '',
    car_id: params.get('car') ?? '',
    page: Number(params.get('page') ?? 1),
  }
  const [range, setRange] = useState(params.get('from') ? 'kustom' : 'bulan_ini')
  const [exporting, setExporting] = useState(false)
  const cars = useCarOptions('')

  const update = (next: Record<string, string | number | null>) => {
    const p = new URLSearchParams(params)
    for (const [k, v] of Object.entries(next)) {
      if (v === null || v === '' || (k === 'page' && v === 1)) p.delete(k)
      else p.set(k, String(v))
    }
    setParams(p, { replace: true })
  }

  return (
    <>
      <PageHeader
        title="Laporan Pembayaran"
        subtitle="Pembayaran masuk, piutang, pendapatan, utilisasi, dan biaya servis. Semua tanggal dalam WIB."
        action={<Button variant="secondary" loading={exporting} onClick={async () => {
          setExporting(true)
          try { await downloadReportCsv(tab, f) } catch (e) { toast(errorMessage(e), 'error') } finally { setExporting(false) }
        }}><Download className="size-4" /> Ekspor CSV</Button>}
      />

      <div className="mb-4 grid gap-3 rounded-2xl border border-line bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <Field label="Periode">
          {(a) => (
            <Select {...a} value={range} onChange={(e) => {
              setRange(e.target.value)
              if (e.target.value !== 'kustom') { const r = preset(e.target.value); update({ from: r.from, to: r.to, page: 1 }) }
            }}>
              <option value="bulan_ini">Bulan ini</option>
              <option value="7">7 hari terakhir</option>
              <option value="30">30 hari terakhir</option>
              <option value="bulan_lalu">Bulan lalu</option>
              <option value="kustom">Kustom</option>
            </Select>
          )}
        </Field>
        <Field label="Dari">{(a) => <Input {...a} type="date" value={f.from} max={f.to} onChange={(e) => { setRange('kustom'); update({ from: e.target.value, page: 1 }) }} />}</Field>
        <Field label="Sampai">{(a) => <Input {...a} type="date" value={f.to} min={f.from} onChange={(e) => { setRange('kustom'); update({ to: e.target.value, page: 1 }) }} />}</Field>
        <Field label="Metode" hint={tab === 'pembayaran' ? undefined : 'Hanya untuk pembayaran'}>
          {(a) => (
            <Select {...a} value={f.method} disabled={tab !== 'pembayaran'} onChange={(e) => update({ method: e.target.value, page: 1 })}>
              <option value="">Semua metode</option><option value="tunai">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option><option value="deposit">Dari deposit</option>
            </Select>
          )}
        </Field>
        <Field label="Mobil">
          {(a) => (
            <Select {...a} value={f.car_id} onChange={(e) => update({ car: e.target.value, page: 1 })}>
              <option value="">Semua mobil</option>
              {(cars.data ?? []).map((c) => <option key={c.id} value={c.id}>{c.name} · {c.plate_number}</option>)}
            </Select>
          )}
        </Field>
      </div>

      <div className="mb-4 overflow-x-auto pb-1">
        <FilterChips<ReportTab> label="Jenis laporan" value={tab} onChange={(v) => update({ tab: v, page: 1 })} options={TABS} />
      </div>
      <p className="mb-4 text-xs text-ink-muted">Periode: {dateOnly(f.from)} – {dateOnly(f.to)}</p>

      {tab === 'pembayaran' && <PaymentsTab f={{ ...f, method: f.method }} onPage={(p) => update({ page: p })} />}
      {tab === 'piutang' && <ReceivablesTab f={{ ...f, method: '' }} onPage={(p) => update({ page: p })} />}
      {tab === 'pendapatan' && <RevenueTab f={{ ...f, method: '' }} />}
      {tab === 'utilisasi' && <UtilizationTab f={{ ...f, method: '' }} />}
      {tab === 'servis' && <MaintenanceTab f={{ ...f, method: '' }} />}
    </>
  )
}
