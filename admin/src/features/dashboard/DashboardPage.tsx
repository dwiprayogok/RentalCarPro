import { AlertTriangle, ArrowDownRight, ArrowUpRight, CalendarClock, CarFront, CheckCircle2, Clock, Inbox, KeyRound, Wallet, Wrench } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { useCan, useMe } from '@/auth/useAuth'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, CarStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Feedback'
import { ErrorState } from '@/components/ui/States'
import { CarIllustration } from '@/features/cars/CarIllustration'
import { useDashboard, type DashboardData, type Reminder } from '@/features/rentals/api'
import { PaymentBadge, RentalStatusBadge } from '@/features/rentals/badges'
import { NewRentalModal } from '@/features/rentals/NewRentalModal'
import { RentalDetailModal } from '@/features/rentals/RentalDetailModal'
import type { Rental } from '@/features/rentals/types'
import { cn } from '@/lib/cn'
import { humanDuration } from '@/lib/datetime'
import { dateOnly, dateTimeWib, number, rupiah } from '@/lib/format'
import { P } from '@/lib/permissions'
import { CashFlowChart } from './CashFlowChart'

function Banner({ b, newRequests }: { b: DashboardData['banner']; newRequests: number }) {
  const styles = {
    danger: { box: 'bg-red-50 ring-red-200', icon: <AlertTriangle className="size-5 text-red-600" />, title: 'text-red-900' },
    warning: { box: 'bg-amber-50 ring-amber-200', icon: <Clock className="size-5 text-amber-600" />, title: 'text-amber-900' },
    success: { box: 'bg-sidebar ring-sidebar', icon: <CheckCircle2 className="size-5 text-brand-300" />, title: 'text-white' },
  }[b.tone]
  return (
    <div className={cn('flex flex-wrap items-center gap-4 rounded-2xl px-5 py-4 ring-1', styles.box)} role={b.tone === 'danger' ? 'alert' : 'status'}>
      {styles.icon}
      <div className="min-w-0 flex-1">
        <p className={cn('text-base font-bold', styles.title)}>{b.title}</p>
        <p className={cn('text-sm', b.tone === 'success' ? 'text-sidebar-muted' : 'text-ink-soft')}>
          {b.message}{newRequests > 0 && <> · <Link to="/permintaan-booking" className="font-semibold underline">{newRequests} permintaan booking baru</Link></>}
        </p>
      </div>
      {b.action && <Link to={b.action.to}><Button size="sm" variant={b.tone === 'success' ? 'secondary' : 'primary'}>{b.action.label}</Button></Link>}
    </div>
  )
}

function Kpi({ icon, label, value, children, tone }: { icon: ReactNode; label: string; value: ReactNode; children?: ReactNode; tone?: 'red' | 'amber' }) {
  return (
    <div className="card flex flex-col p-4">
      <div className="flex items-center justify-between">
        <p className="text-xs font-semibold text-ink-muted">{label}</p>
        <span className={cn('grid size-8 place-items-center rounded-lg', tone === 'red' ? 'bg-red-50 text-red-600' : tone === 'amber' ? 'bg-amber-50 text-amber-700' : 'bg-brand-50 text-brand-600')}>{icon}</span>
      </div>
      <p className="mt-2 text-2xl font-extrabold tracking-tight text-ink tabular-nums">{value}</p>
      <div className="mt-1 text-2xs text-ink-muted">{children}</div>
    </div>
  )
}

function ReturnRow({ r, onOpen }: { r: Rental; onOpen: () => void }) {
  return (
    <li>
      <button type="button" onClick={onOpen} className={cn('flex w-full items-center justify-between gap-3 px-5 py-3 text-left hover:bg-canvas/60', r.is_overdue && 'bg-red-50/50')}>
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-ink">{r.car.name} <span className="font-normal text-ink-muted">· {r.car.plate_number}</span></p>
          <p className="truncate text-2xs text-ink-muted">{r.code} · {r.customer.name} · kembali {dateTimeWib(r.planned_end_at)}</p>
        </div>
        <div className="shrink-0 text-right">
          {r.is_overdue && r.billing.running_late ? (
            <>
              <p className="text-xs font-bold text-red-700">{r.billing.running_late_fee > 0 ? rupiah(r.billing.running_late_fee) : 'Toleransi'}</p>
              <p className="text-2xs text-red-700">telat {humanDuration(r.billing.running_late.late_minutes)}</p>
            </>
          ) : <RentalStatusBadge rental={r} />}
        </div>
      </button>
    </li>
  )
}

function ReminderRow({ m }: { m: Reminder }) {
  const due = m.days_left !== null
    ? (m.days_left < 0 ? `lewat ${-m.days_left} hari` : m.days_left === 0 ? 'hari ini' : `${m.days_left} hari lagi`)
    : m.km_left !== null ? (m.km_left < 0 ? `lewat ${number(-m.km_left)} km` : `${number(m.km_left)} km lagi`) : ''
  return (
    <li className="flex items-center justify-between gap-3 px-5 py-3">
      <div className="min-w-0">
        <p className="truncate text-sm font-semibold text-ink">{m.label}</p>
        <p className="truncate text-2xs text-ink-muted">{m.car} · {m.plate_number}{m.due_date && ` · ${dateOnly(m.due_date)}`}</p>
      </div>
      <Badge tone={m.overdue ? 'red' : (m.days_left ?? 99) <= 7 || (m.km_left ?? 9999) <= 300 ? 'yellow' : 'gray'}>{due}</Badge>
    </li>
  )
}

function Panel({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section className="card overflow-hidden">
      <div className="flex items-center justify-between gap-2 border-b border-line px-5 py-3">
        <h2 className="text-sm font-bold text-ink">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  )
}

const Empty = ({ children }: { children: ReactNode }) => <p className="px-5 py-8 text-center text-xs text-ink-muted">{children}</p>

export function DashboardPage() {
  const { data: user } = useMe()
  const can = useCan()
  const { data, isLoading, isError, error, refetch } = useDashboard()
  const [openId, setOpenId] = useState<number | null>(null)
  const [creating, setCreating] = useState(false)

  return (
    <>
      <PageHeader
        title={`Halo, ${user?.name.split(' ')[0] ?? ''}`}
        subtitle="Ringkasan operasional hari ini (WIB). Diperbarui otomatis tiap menit."
        action={can(P.rentalCreate) && <Button onClick={() => setCreating(true)}><KeyRound className="size-4" /> Sewa Baru</Button>}
      />

      {isLoading ? (
        <div className="space-y-4"><Skeleton className="h-20" /><div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-28" />)}</div><Skeleton className="h-64" /></div>
      ) : isError ? (
        <div className="card"><ErrorState error={error} onRetry={() => refetch()} /></div>
      ) : data && (
        <div className="space-y-6">
          <Banner b={data.banner} newRequests={data.new_booking_requests} />

          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Kpi icon={<Wallet className="size-4" />} label={`Pembayaran ${data.kpi.payments_month.month_label}`} value={<span className="text-xl">{rupiah(data.kpi.payments_month.amount)}</span>}>
              {data.kpi.payments_month.change_pct === null ? 'Belum ada pembanding bulan lalu' : (
                <span className={cn('inline-flex items-center gap-0.5 font-semibold', data.kpi.payments_month.change_pct >= 0 ? 'text-brand-700' : 'text-red-700')}>
                  {data.kpi.payments_month.change_pct >= 0 ? <ArrowUpRight className="size-3" /> : <ArrowDownRight className="size-3" />}
                  {Math.abs(data.kpi.payments_month.change_pct).toLocaleString('id-ID')}% vs periode sama bulan lalu
                </span>
              )}
            </Kpi>
            <Kpi icon={<KeyRound className="size-4" />} label="Sewa aktif" value={data.kpi.active.count}>{data.kpi.active.booking} booking menunggu diambil</Kpi>
            <Kpi icon={<CarFront className="size-4" />} label="Mobil tersedia" value={`${data.kpi.cars.available}/${data.kpi.cars.total}`}>{data.kpi.cars.rented} disewa · {data.kpi.cars.service} servis</Kpi>
            <Kpi icon={<Clock className="size-4" />} label="Perlu pengembalian" value={data.kpi.need_return.count} tone={data.kpi.need_return.overdue ? 'red' : undefined}>
              {data.kpi.need_return.overdue} terlambat · {data.kpi.need_return.today} jatuh tempo hari ini
            </Kpi>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="card flex items-center justify-between gap-3 px-5 py-3">
              <div><p className="text-xs font-semibold text-ink-muted">Piutang (sisa tagihan)</p><p className="text-lg font-extrabold text-ink tabular-nums">{rupiah(data.receivable)}</p></div>
              {can(P.reportView) && <Link to="/laporan?tab=piutang" className="text-xs font-semibold text-brand-600 hover:underline">Lihat piutang</Link>}
            </div>
            <div className={cn('card flex items-center justify-between gap-3 px-5 py-3', data.running_late_total > 0 && 'border-red-200 bg-red-50/40')}>
              <div><p className="text-xs font-semibold text-ink-muted">Estimasi denda berjalan</p><p className={cn('text-lg font-extrabold tabular-nums', data.running_late_total > 0 ? 'text-red-700' : 'text-ink')}>{rupiah(data.running_late_total)}</p></div>
              <span className="text-2xs text-ink-muted">final saat Kembali</span>
            </div>
          </div>

          <div className="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
            <CashFlowChart data={data.cash_flow} />
            <Panel title="Jadwal pengembalian prioritas" action={<Link to="/transaksi?view=aktif" className="text-xs font-semibold text-brand-600 hover:underline">Semua</Link>}>
              {data.priority_returns.length === 0 ? <Empty>Tidak ada pengembalian dalam 2 hari ke depan.</Empty> : (
                <ul className="divide-y divide-line">{data.priority_returns.map((r) => <ReturnRow key={r.id} r={r} onOpen={() => setOpenId(r.id)} />)}</ul>
              )}
            </Panel>
          </div>

          <div className="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
            <Panel title="Transaksi terbaru" action={<Link to="/transaksi" className="text-xs font-semibold text-brand-600 hover:underline">Lihat semua</Link>}>
              {data.recent_rentals.length === 0 ? <Empty>Belum ada transaksi.</Empty> : (
                <ul className="divide-y divide-line">
                  {data.recent_rentals.map((r) => (
                    <li key={r.id}>
                      <button type="button" onClick={() => setOpenId(r.id)} className="flex w-full items-center justify-between gap-3 px-5 py-3 text-left hover:bg-canvas/60">
                        <div className="min-w-0">
                          <p className="truncate text-sm font-semibold text-ink">{r.code} · {r.customer.name}</p>
                          <p className="truncate text-2xs text-ink-muted">{r.car.name} · {dateTimeWib(r.start_at)}</p>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                          <span className="hidden text-xs font-semibold tabular-nums sm:inline">{rupiah(r.billing.total_amount)}</span>
                          <PaymentBadge status={r.billing.payment_status} label={r.billing.payment_status_label} />
                        </div>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </Panel>
            <Panel title="Pengingat pajak & servis" action={<Wrench className="size-4 text-ink-muted" />}>
              {data.reminders.length === 0 ? <Empty>Tidak ada pajak, STNK, atau servis yang mendekati jatuh tempo.</Empty> : (
                <ul className="divide-y divide-line">{data.reminders.slice(0, 6).map((m, i) => <ReminderRow key={`${m.car_id}-${m.type}-${i}`} m={m} />)}</ul>
              )}
            </Panel>
          </div>

          <Panel title="Armada pilihan" action={<Link to="/armada" className="text-xs font-semibold text-brand-600 hover:underline">Semua armada</Link>}>
            {data.featured_cars.length === 0 ? <Empty>Belum ada armada.</Empty> : (
              <div className="grid gap-4 p-4 sm:grid-cols-3">
                {data.featured_cars.map((c) => (
                  <div key={c.id} className="overflow-hidden rounded-xl border border-line">
                    <div className="relative aspect-[16/9] bg-canvas">
                      {c.cover_photo_url ? <img src={c.cover_photo_url} alt={c.name} className="size-full object-cover" /> : <div className="flex size-full items-center justify-center px-6"><CarIllustration className="w-full max-w-[180px]" /></div>}
                      <div className="absolute top-2 left-2"><CarStatusBadge status={c.status} label={c.status_label} /></div>
                    </div>
                    <div className="p-3">
                      <p className="truncate text-sm font-bold text-ink">{c.name}</p>
                      <p className="text-2xs text-ink-muted">{c.plate_number} · {c.recent_rentals} sewa / 90 hari</p>
                      <p className="mt-1 text-sm font-extrabold tabular-nums">{rupiah(c.rate_self_drive)} <span className="text-2xs font-medium text-ink-muted">/ hari</span></p>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </Panel>

          {data.pickups_today.length > 0 && (
            <Panel title="Diambil hari ini" action={<CalendarClock className="size-4 text-ink-muted" />}>
              <ul className="divide-y divide-line">{data.pickups_today.map((r) => <ReturnRow key={r.id} r={r} onOpen={() => setOpenId(r.id)} />)}</ul>
            </Panel>
          )}
          {data.new_booking_requests > 0 && can(P.bookingRequestView) && (
            <Link to="/permintaan-booking" className="card flex items-center gap-3 px-5 py-3 text-sm hover:bg-canvas/60">
              <Inbox className="size-4 text-brand-600" /> <span><strong>{data.new_booking_requests}</strong> permintaan booking baru dari landing page menunggu ditindaklanjuti.</span>
            </Link>
          )}
        </div>
      )}

      <RentalDetailModal rentalId={openId} onClose={() => setOpenId(null)} />
      <NewRentalModal open={creating} onClose={() => setCreating(false)} onCreated={(r) => setOpenId(r.id)} />
    </>
  )
}
