import { Download, FileText, Plus } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { useCan } from '@/auth/useAuth'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Feedback'
import { FilterChips } from '@/components/ui/FilterChips'
import { Pagination } from '@/components/ui/Pagination'
import { SearchInput } from '@/components/ui/SearchInput'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { errorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import { dateTimeWib, rupiah } from '@/lib/format'
import { P } from '@/lib/permissions'
import { useDebounce } from '@/lib/useDebounce'
import { downloadRentalsCsv, useRentals } from './api'
import { PaymentBadge, RentalStatusBadge } from './badges'
import { NewRentalModal } from './NewRentalModal'
import { RentalDetailModal } from './RentalDetailModal'
import type { Rental, RentalView } from './types'

const VIEWS: { value: RentalView; label: string }[] = [
  { value: 'semua', label: 'Semua' },
  { value: 'aktif', label: 'Aktif' },
  { value: 'terlambat', label: 'Terlambat' },
  { value: 'selesai', label: 'Selesai' },
  { value: 'belum_lunas', label: 'Belum Lunas' },
]

function Amount({ r }: { r: Rental }) {
  return (
    <div className="space-y-1">
      <p className="font-semibold text-ink tabular-nums">{rupiah(r.billing.total_amount)}</p>
      <PaymentBadge status={r.billing.payment_status} label={r.billing.payment_status_label} />
      {r.billing.running_late_fee > 0 && <p className="text-2xs font-semibold text-red-700">+ denda berjalan {rupiah(r.billing.running_late_fee)}</p>}
    </div>
  )
}

export function RentalsPage() {
  const can = useCan()
  const toast = useToast()
  const [params, setParams] = useSearchParams()
  const view = (params.get('view') ?? 'semua') as RentalView
  const page = Number(params.get('page') ?? 1)
  const [search, setSearch] = useState(params.get('q') ?? '')
  const debounced = useDebounce(search.trim())
  const [creating, setCreating] = useState(false)
  const [detailId, setDetailId] = useState<number | null>(null)
  const [exporting, setExporting] = useState(false)

  const update = (next: Record<string, string | number | null>) => {
    const p = new URLSearchParams(params)
    for (const [k, v] of Object.entries(next)) {
      if (v === null || v === '' || (k === 'page' && v === 1) || (k === 'view' && v === 'semua')) p.delete(k)
      else p.set(k, String(v))
    }
    setParams(p, { replace: true })
  }

  const query = useRentals({ search: debounced, view, page })
  const rows = query.data?.data ?? []
  const counts = query.data?.meta.counts
  const filtered = !!debounced || view !== 'semua'

  const exportCsv = async () => {
    setExporting(true)
    try { await downloadRentalsCsv({ search: debounced, view }) } catch (e) { toast(errorMessage(e), 'error') } finally { setExporting(false) }
  }

  return (
    <>
      <PageHeader
        title="Transaksi Sewa"
        subtitle="Kelola booking, serah terima, dan pembayaran."
        action={
          <div className="flex gap-2">
            <Button variant="secondary" onClick={exportCsv} loading={exporting}><Download className="size-4" /> Unduh CSV</Button>
            {can(P.rentalCreate) && <Button onClick={() => setCreating(true)}><Plus className="size-4" /> Sewa Baru</Button>}
          </div>
        }
      />

      <div className="mb-5 flex flex-col gap-3">
        <SearchInput label="Cari transaksi" placeholder="Cari no. nota, pelanggan, atau plat…" value={search} onChange={(v) => { setSearch(v); update({ q: v || null, page: 1 }) }} />
        <FilterChips<RentalView> label="Filter transaksi" value={view} onChange={(v) => update({ view: v, page: 1 })}
          options={VIEWS.map((v) => ({ ...v, count: counts?.[v.value] }))} />
      </div>

      <div className="card overflow-hidden">
        {query.isLoading ? (
          <div className="divide-y divide-line">{Array.from({ length: 5 }, (_, i) => <div key={i} className="flex gap-4 px-5 py-4"><Skeleton className="h-4 w-28" /><Skeleton className="h-4 w-40" /><Skeleton className="ml-auto h-4 w-24" /></div>)}</div>
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState
            icon={<FileText className="size-7" />}
            title={filtered ? 'Tidak ada transaksi yang cocok' : 'Belum ada transaksi'}
            description={filtered ? 'Coba kata kunci atau filter lain.' : 'Buat Sewa Baru untuk mencatat booking atau serah terima pertama.'}
            action={filtered
              ? <Button variant="secondary" onClick={() => { setSearch(''); update({ q: null, view: null, page: 1 }) }}>Reset filter</Button>
              : can(P.rentalCreate) && <Button onClick={() => setCreating(true)}><Plus className="size-4" /> Sewa Baru</Button>}
          />
        ) : (
          <div className={cn(query.isPlaceholderData && 'opacity-60')}>
            <table className="hidden w-full text-sm lg:table">
              <thead>
                <tr className="border-b border-line text-left text-2xs font-bold tracking-[0.12em] text-ink-muted uppercase">
                  <th className="px-5 py-3">Nota</th>
                  <th className="px-3 py-3">Pelanggan</th>
                  <th className="px-3 py-3">Mobil</th>
                  <th className="px-3 py-3">Jadwal (WIB)</th>
                  <th className="px-3 py-3">Tagihan</th>
                  <th className="px-3 py-3">Status</th>
                  <th className="px-5 py-3">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {rows.map((r) => (
                  <tr key={r.id} className={cn('align-top hover:bg-canvas/60', r.is_overdue && 'bg-red-50/40')}>
                    <td className="px-5 py-3.5"><p className="font-semibold text-ink">{r.code}</p><p className="text-2xs text-ink-muted">{r.service_type_label}</p></td>
                    <td className="px-3 py-3.5"><p className="font-semibold text-ink">{r.customer.name}</p><p className="text-2xs text-ink-muted">{r.customer.phone}</p></td>
                    <td className="px-3 py-3.5"><p className="text-ink">{r.car.name}</p><p className="text-2xs text-ink-muted">{r.car.plate_number}{r.driver && ` · ${r.driver.name}`}</p></td>
                    <td className="px-3 py-3.5 text-xs text-ink-soft"><p>{dateTimeWib(r.start_at)}</p><p className={r.is_overdue ? 'font-semibold text-red-700' : ''}>→ {dateTimeWib(r.planned_end_at)}</p></td>
                    <td className="px-3 py-3.5"><Amount r={r} /></td>
                    <td className="px-3 py-3.5"><RentalStatusBadge rental={r} /></td>
                    <td className="px-5 py-3.5"><Button size="sm" variant="secondary" onClick={() => setDetailId(r.id)} aria-label={`Detail ${r.code}`}>Detail</Button></td>
                  </tr>
                ))}
              </tbody>
            </table>

            <ul className="divide-y divide-line lg:hidden">
              {rows.map((r) => (
                <li key={r.id} className={cn('space-y-2 px-4 py-4', r.is_overdue && 'bg-red-50/40')}>
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0"><p className="font-bold text-ink">{r.code}</p><p className="text-xs text-ink-muted">{r.customer.name} · {r.car.name} ({r.car.plate_number})</p></div>
                    <RentalStatusBadge rental={r} />
                  </div>
                  <p className="text-xs text-ink-soft">{dateTimeWib(r.start_at)} → <span className={r.is_overdue ? 'font-semibold text-red-700' : ''}>{dateTimeWib(r.planned_end_at)}</span></p>
                  <div className="flex items-end justify-between gap-2">
                    <Amount r={r} />
                    <Button size="sm" variant="secondary" onClick={() => setDetailId(r.id)}>Detail</Button>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}
        {query.data && rows.length > 0 && <div className="border-t border-line px-4 pb-4"><Pagination meta={query.data.meta} onPage={(p) => update({ page: p })} /></div>}
      </div>

      <NewRentalModal open={creating} onClose={() => setCreating(false)} onCreated={(r) => setDetailId(r.id)} />
      <RentalDetailModal rentalId={detailId} onClose={() => setDetailId(null)} />
    </>
  )
}
