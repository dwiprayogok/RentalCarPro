const DOW = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']

/** Kalender ketersediaan publik (tanggal WIB). Hanya menampilkan tanggal terisi, tanpa detail sewa. */
export function AvailabilityCalendar({ from, to, unavailable }: { from: string; to: string; unavailable: { start: string; end: string }[] }) {
  const busy = (ymd: string) => unavailable.some((r) => ymd >= r.start && ymd <= r.end)
  const start = new Date(`${from}T00:00:00Z`)
  const months = [0, 1, 2].map((m) => new Date(Date.UTC(start.getUTCFullYear(), start.getUTCMonth() + m, 1))).filter((d) => d.toISOString().slice(0, 10) <= to)
  const fmtMonth = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'UTC' })
  const fmtDay = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', timeZone: 'UTC' })

  return (
    <div>
      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {months.map((m) => {
          const days = new Date(Date.UTC(m.getUTCFullYear(), m.getUTCMonth() + 1, 0)).getUTCDate()
          const lead = (m.getUTCDay() + 6) % 7
          return (
            <div key={m.toISOString()}>
              <p className="mb-2 text-sm font-bold capitalize">{fmtMonth.format(m)}</p>
              <div className="grid grid-cols-7 gap-1 text-center" role="grid" aria-label={fmtMonth.format(m)}>
                {DOW.map((d) => <span key={d} className="text-2xs font-semibold text-muted" aria-hidden>{d}</span>)}
                {Array.from({ length: lead }, (_, k) => <span key={`x${k}`} aria-hidden />)}
                {Array.from({ length: days }, (_, k) => {
                  const date = new Date(Date.UTC(m.getUTCFullYear(), m.getUTCMonth(), k + 1))
                  const ymd = date.toISOString().slice(0, 10)
                  const past = ymd < from || ymd > to
                  const isBusy = !past && busy(ymd)
                  return (
                    <span key={ymd} role="gridcell"
                      aria-label={`${fmtDay.format(date)}: ${past ? 'di luar jangkauan' : isBusy ? 'terisi' : 'kosong'}`}
                      className={`grid h-9 place-items-center rounded-lg text-sm ${past ? 'text-ink/25' : isBusy ? 'bg-[repeating-linear-gradient(135deg,#fde2c4_0_4px,#fdf3e6_4px_8px)] font-semibold text-amber line-through' : 'bg-brand-50 font-medium text-brand'}`}>
                      {k + 1}
                    </span>
                  )
                })}
              </div>
            </div>
          )
        })}
      </div>
      <ul className="mt-4 flex flex-wrap gap-4 text-2xs text-muted">
        <li className="flex items-center gap-1.5"><span className="size-3 rounded bg-brand-50 ring-1 ring-brand/30" aria-hidden />Kosong</li>
        <li className="flex items-center gap-1.5"><span className="size-3 rounded bg-[repeating-linear-gradient(135deg,#fde2c4_0_3px,#fdf3e6_3px_6px)] ring-1 ring-amber/30" aria-hidden />Terisi (termasuk waktu persiapan)</li>
      </ul>
    </div>
  )
}
