import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Skeleton } from '@/components/ui/Feedback'
import { ErrorState } from '@/components/ui/States'
import { useAvailability } from '@/features/rentals/api'
import type { AvailabilityBlock } from '@/features/rentals/types'
import { cn } from '@/lib/cn'
import { dateTimeWib } from '@/lib/format'
import { todayWib } from '@/lib/datetime'

const DAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']
const pad = (n: number) => String(n).padStart(2, '0')
const ymd = (y: number, m: number, d: number) => `${y}-${pad(m + 1)}-${pad(d)}`

/** Tanggal WIB dari ISO ber-offset +07:00 (cukup ambil 10 karakter pertama). */
const wibDate = (iso: string) => iso.slice(0, 10)

function tone(b: AvailabilityBlock) {
  if (b.is_overdue) return 'bg-red-500'
  return b.status === 'aktif' ? 'bg-brand-500' : 'bg-sky-500'
}

/** Kalender ketersediaan bulanan per mobil (WIB). Blok = sewa Booking/Aktif. */
export function AvailabilityCalendar({ carId, compact = false }: { carId: number; compact?: boolean }) {
  const today = todayWib()
  const [cursor, setCursor] = useState(() => ({ y: Number(today.slice(0, 4)), m: Number(today.slice(5, 7)) - 1 }))
  const daysInMonth = new Date(Date.UTC(cursor.y, cursor.m + 1, 0)).getUTCDate()
  const from = ymd(cursor.y, cursor.m, 1)
  const to = ymd(cursor.y, cursor.m, daysInMonth)
  const { data, isLoading, isError, error, refetch, isFetching } = useAvailability(carId, from, to)
  const blocks = useMemo(() => data?.data ?? [], [data])

  const lead = (new Date(Date.UTC(cursor.y, cursor.m, 1)).getUTCDay() + 6) % 7 // Senin = 0
  const byDay = useMemo(() => {
    const map: Record<string, AvailabilityBlock[]> = {}
    for (let d = 1; d <= daysInMonth; d++) {
      const day = ymd(cursor.y, cursor.m, d)
      map[day] = blocks.filter((b) => wibDate(b.start_at) <= day && wibDate(b.end_at) >= day)
    }
    return map
  }, [blocks, cursor, daysInMonth])

  const monthLabel = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(cursor.y, cursor.m, 1)))
  const move = (delta: number) => setCursor(({ y, m }) => ({ y: y + Math.floor((m + delta) / 12), m: (((m + delta) % 12) + 12) % 12 }))

  return (
    <div>
      <div className="mb-3 flex items-center justify-between">
        <button type="button" onClick={() => move(-1)} className="rounded-lg p-1.5 text-ink-soft hover:bg-canvas" aria-label="Bulan sebelumnya"><ChevronLeft className="size-5" /></button>
        <p className={cn('text-sm font-bold text-ink capitalize', isFetching && 'opacity-60')}>{monthLabel}</p>
        <button type="button" onClick={() => move(1)} className="rounded-lg p-1.5 text-ink-soft hover:bg-canvas" aria-label="Bulan berikutnya"><ChevronRight className="size-5" /></button>
      </div>

      {isLoading ? (
        <Skeleton className="h-56 w-full" />
      ) : isError ? (
        <ErrorState error={error} onRetry={() => refetch()} />
      ) : (
        <>
          <div className="grid grid-cols-7 gap-1 text-center" role="grid" aria-label={`Ketersediaan ${monthLabel}`}>
            {DAYS.map((d) => <div key={d} className="pb-1 text-2xs font-semibold text-ink-muted">{d}</div>)}
            {Array.from({ length: lead }, (_, i) => <div key={`x${i}`} />)}
            {Array.from({ length: daysInMonth }, (_, i) => {
              const day = ymd(cursor.y, cursor.m, i + 1)
              const items = byDay[day] ?? []
              const busy = items.length > 0
              return (
                <div
                  key={day}
                  role="gridcell"
                  title={busy ? items.map((b) => `${b.code}${b.customer_name ? ` · ${b.customer_name}` : ''}`).join('\n') : 'Tersedia'}
                  className={cn(
                    'relative flex flex-col items-center justify-center rounded-lg border text-xs',
                    compact ? 'h-9' : 'h-12',
                    busy ? 'border-transparent bg-canvas font-semibold text-ink' : 'border-line text-ink-soft',
                    day === today && 'ring-2 ring-brand-300',
                  )}
                >
                  {i + 1}
                  {busy && (
                    <span className="absolute bottom-1 flex gap-0.5">
                      {items.slice(0, 3).map((b) => <span key={b.rental_id} className={cn('h-1.5 w-3 rounded-full', tone(b))} />)}
                    </span>
                  )}
                </div>
              )
            })}
          </div>

          <div className="mt-3 flex flex-wrap gap-3 text-2xs text-ink-muted">
            <span className="flex items-center gap-1"><span className="h-1.5 w-3 rounded-full bg-sky-500" /> Booking</span>
            <span className="flex items-center gap-1"><span className="h-1.5 w-3 rounded-full bg-brand-500" /> Aktif</span>
            <span className="flex items-center gap-1"><span className="h-1.5 w-3 rounded-full bg-red-500" /> Terlambat</span>
            {data && <span>Jeda antar sewa {data.meta.buffer_minutes / 60} jam</span>}
          </div>

          {!compact && (
            blocks.length === 0 ? (
              <p className="mt-4 rounded-xl bg-canvas px-4 py-3 text-center text-xs text-ink-muted">Tidak ada jadwal sewa di bulan ini. Mobil tersedia setiap hari.</p>
            ) : (
              <ul className="mt-4 divide-y divide-line rounded-xl border border-line">
                {blocks.map((b) => (
                  <li key={b.rental_id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs">
                    <span className="flex items-center gap-2 font-semibold text-ink"><span className={cn('h-2 w-2 rounded-full', tone(b))} />{b.code}{b.customer_name && <span className="font-normal text-ink-muted">· {b.customer_name}</span>}</span>
                    <span className="text-ink-muted">{dateTimeWib(b.start_at)} → {dateTimeWib(b.end_at)}</span>
                  </li>
                ))}
              </ul>
            )
          )}
        </>
      )}
    </div>
  )
}
