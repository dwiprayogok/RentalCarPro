import { AlertTriangle, CalendarClock, CarFront, KeyRound, Wallet } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { useMe } from '@/auth/useAuth'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Feedback'
import { ErrorState } from '@/components/ui/States'
import { useDashboard } from '@/features/rentals/api'
import { PaymentBadge, RentalStatusBadge } from '@/features/rentals/badges'
import { RentalDetailModal } from '@/features/rentals/RentalDetailModal'
import type { Rental } from '@/features/rentals/types'
import { cn } from '@/lib/cn'
import { dateTimeWib, rupiah } from '@/lib/format'
import { humanDuration } from '@/lib/datetime'

function Stat({ icon, label, value, hint, tone }: { icon: ReactNode; label: string; value: ReactNode; hint?: string; tone?: 'red' | 'amber' }) {
  return (
    <div className="card p-4">
      <div className={cn('mb-3 grid size-9 place-items-center rounded-xl', tone === 'red' ? 'bg-red-50 text-red-600' : tone === 'amber' ? 'bg-amber-50 text-amber-700' : 'bg-brand-50 text-brand-600')}>{icon}</div>
      <p className="text-xs font-semibold text-ink-muted">{label}</p>
      <p className="mt-0.5 text-2xl font-extrabold text-ink tabular-nums">{value}</p>
      {hint && <p className="mt-0.5 text-2xs text-ink-muted">{hint}</p>}
    </div>
  )
}

function RentalList({ title, items, empty, onOpen, overdue }: { title: string; items: Rental[]; empty: string; onOpen: (id: number) => void; overdue?: boolean }) {
  return (
    <section className="card overflow-hidden">
      <h2 className="border-b border-line px-5 py-3 text-sm font-bold text-ink">{title}</h2>
      {items.length === 0 ? (
        <p className="px-5 py-8 text-center text-xs text-ink-muted">{empty}</p>
      ) : (
        <ul className="divide-y divide-line">
          {items.map((r) => (
            <li key={r.id}>
              <button type="button" onClick={() => onOpen(r.id)} className="flex w-full flex-wrap items-center justify-between gap-3 px-5 py-3 text-left hover:bg-canvas/60">
                <div className="min-w-0">
                  <p className="text-sm font-semibold text-ink">{r.code} · {r.customer.name}</p>
                  <p className="text-2xs text-ink-muted">{r.car.name} ({r.car.plate_number}) · {overdue ? `seharusnya kembali ${dateTimeWib(r.planned_end_at)}` : r.status === 'booking' ? `ambil ${dateTimeWib(r.start_at)}` : `kembali ${dateTimeWib(r.planned_end_at)}`}</p>
                </div>
                <div className="flex items-center gap-2">
                  {overdue && r.billing.running_late && (
                    <span className="text-right text-2xs">
                      <span className="block font-semibold text-red-700">{rupiah(r.billing.running_late_fee)}</span>
                      <span className="text-ink-muted">{humanDuration(r.billing.running_late.late_minutes)}</span>
                    </span>
                  )}
                  {!overdue && <PaymentBadge status={r.billing.payment_status} label={r.billing.payment_status_label} />}
                  <RentalStatusBadge rental={r} />
                </div>
              </button>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

export function DashboardPage() {
  const { data: user } = useMe()
  const { data, isLoading, isError, error, refetch } = useDashboard()
  const [openId, setOpenId] = useState<number | null>(null)

  return (
    <>
      <PageHeader title={`Halo, ${user?.name.split(' ')[0] ?? ''}`} subtitle="Ringkasan operasional hari ini (WIB). Diperbarui otomatis tiap menit." action={<Link to="/transaksi"><Button variant="secondary">Lihat semua transaksi</Button></Link>} />

      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-32" />)}</div>
      ) : isError ? (
        <div className="card"><ErrorState error={error} onRetry={() => refetch()} /></div>
      ) : data && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Stat icon={<KeyRound className="size-5" />} label="Sewa aktif" value={data.counts.aktif} hint={`${data.counts.kembali_hari_ini} dijadwalkan kembali hari ini`} />
            <Stat icon={<AlertTriangle className="size-5" />} label="Terlambat" value={data.counts.terlambat} hint={data.running_late_total > 0 ? `Estimasi denda berjalan ${rupiah(data.running_late_total)}` : 'Tidak ada denda berjalan'} tone={data.counts.terlambat ? 'red' : undefined} />
            <Stat icon={<Wallet className="size-5" />} label="Piutang" value={<span className="text-xl">{rupiah(data.receivable)}</span>} hint={`${data.counts.belum_lunas} transaksi belum lunas (belum termasuk denda berjalan)`} tone={data.receivable ? 'amber' : undefined} />
            <Stat icon={<CarFront className="size-5" />} label="Mobil tersedia" value={`${data.counts.mobil_tersedia} / ${data.counts.mobil_total}`} hint={`${data.counts.ambil_hari_ini} booking diambil hari ini`} />
          </div>

          <RentalList title="Sewa terlambat" items={data.overdue} empty="Tidak ada sewa terlambat. 🎉" onOpen={setOpenId} overdue />
          <div className="grid gap-6 lg:grid-cols-2">
            <RentalList title="Kembali hari ini" items={data.returns_today} empty="Tidak ada jadwal kembali hari ini." onOpen={setOpenId} />
            <RentalList title="Diambil hari ini" items={data.pickups_today} empty="Tidak ada booking yang diambil hari ini." onOpen={setOpenId} />
          </div>
          <p className="flex items-center gap-1.5 text-2xs text-ink-muted"><CalendarClock className="size-3.5" /> Pengingat pajak, STNK, dan servis akan ditambahkan di phase berikutnya.</p>
        </div>
      )}

      <RentalDetailModal rentalId={openId} onClose={() => setOpenId(null)} />
    </>
  )
}
