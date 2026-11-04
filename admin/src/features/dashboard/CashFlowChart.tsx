import { BarChart3, Table2 } from 'lucide-react'
import { useId, useState } from 'react'
import { cn } from '@/lib/cn'
import { rupiah, rupiahCompact } from '@/lib/format'

/** Warna batang: teal merek yang lolos validasi kroma & kontras (lihat skill dataviz). */
const BAR = '#0E8A6C'
const W = 560
const H = 200
const PAD = { top: 22, right: 8, bottom: 26, left: 44 }

function niceMax(v: number): number {
  if (v <= 0) return 1
  const pow = 10 ** Math.floor(Math.log10(v))
  const n = v / pow
  return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * pow
}

const dayLabel = (ymd: string) => {
  const [y, m, d] = ymd.split('-').map(Number)
  return new Intl.DateTimeFormat('id-ID', { weekday: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(y, m - 1, d)))
}

/**
 * Arus pembayaran 7 hari (satu seri → tanpa legenda; judul menamai seri).
 * Batang ≤ 24px dengan ujung atas membulat 4px, label hanya pada nilai tertinggi,
 * tooltip per batang (hover & fokus keyboard), dan tampilan tabel.
 */
export function CashFlowChart({ data }: { data: { date: string; amount: number }[] }) {
  const [active, setActive] = useState<number | null>(null)
  const [asTable, setAsTable] = useState(false)
  const titleId = useId()
  const total = data.reduce((s, d) => s + d.amount, 0)
  const max = niceMax(Math.max(0, ...data.map((d) => d.amount)))
  const innerW = W - PAD.left - PAD.right
  const innerH = H - PAD.top - PAD.bottom
  const band = innerW / Math.max(1, data.length)
  const barW = Math.min(24, band * 0.5)
  const y = (v: number) => PAD.top + innerH - (Math.max(0, v) / max) * innerH
  const ticks = [0, max / 2, max]
  const peak = data.reduce((best, d, i) => (d.amount > (data[best]?.amount ?? 0) ? i : best), 0)
  const empty = total === 0

  return (
    <section className="card p-5" aria-labelledby={titleId}>
      <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 id={titleId} className="text-sm font-bold text-ink">Arus pembayaran 7 hari</h2>
          <p className="text-xs text-ink-muted">Uang masuk untuk tagihan per tanggal bayar (WIB) · total {rupiah(total)}</p>
        </div>
        <button
          type="button"
          onClick={() => setAsTable((t) => !t)}
          className="inline-flex items-center gap-1.5 rounded-lg border border-line-strong px-2.5 py-1 text-xs font-semibold text-ink-soft hover:bg-canvas"
          aria-pressed={asTable}
        >
          {asTable ? <BarChart3 className="size-3.5" /> : <Table2 className="size-3.5" />} {asTable ? 'Grafik' : 'Tabel'}
        </button>
      </div>

      {asTable ? (
        <table className="w-full text-sm">
          <thead><tr className="text-left text-2xs font-bold tracking-wide text-ink-muted uppercase"><th className="py-1.5">Tanggal</th><th className="py-1.5 text-right">Uang masuk</th></tr></thead>
          <tbody className="divide-y divide-line">
            {data.map((d) => <tr key={d.date}><td className="py-1.5 text-ink-soft">{dayLabel(d.date)}</td><td className="py-1.5 text-right font-semibold tabular-nums">{rupiah(d.amount)}</td></tr>)}
          </tbody>
        </table>
      ) : (
        <div className="relative">
          <svg viewBox={`0 0 ${W} ${H}`} className="h-auto w-full" role="img" aria-label={`Arus pembayaran 7 hari, total ${rupiah(total)}`} onMouseLeave={() => setActive(null)}>
            {ticks.map((t) => (
              <g key={t}>
                <line x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} stroke="#e5ebe9" strokeWidth={1} />
                <text x={PAD.left - 8} y={y(t)} textAnchor="end" dominantBaseline="middle" className="fill-ink-muted text-[11px]">{rupiahCompact(t)}</text>
              </g>
            ))}
            {data.map((d, i) => {
              const cx = PAD.left + band * i + band / 2
              const top = y(d.amount)
              const h = PAD.top + innerH - top
              const r = Math.min(4, h)
              const x0 = cx - barW / 2
              const base = PAD.top + innerH
              // Ujung atas membulat 4px, dasar persegi menempel di baseline.
              const path = h <= 0 ? '' : `M${x0},${base} V${top + r} Q${x0},${top} ${x0 + r},${top} H${x0 + barW - r} Q${x0 + barW},${top} ${x0 + barW},${top + r} V${base} Z`
              return (
                <g key={d.date}>
                  {path && <path d={path} fill={BAR} opacity={active === null || active === i ? 1 : 0.45} />}
                  {!empty && i === peak && d.amount > 0 && (
                    <text x={cx} y={top - 6} textAnchor="middle" className="fill-ink text-[11px] font-semibold">{rupiahCompact(d.amount)}</text>
                  )}
                  <text x={cx} y={H - 8} textAnchor="middle" className={cn('text-[11px]', i === data.length - 1 ? 'fill-ink font-semibold' : 'fill-ink-muted')}>{dayLabel(d.date)}</text>
                  {/* Area hit selebar kolom (lebih besar dari batang) untuk hover & fokus */}
                  <rect
                    x={PAD.left + band * i} y={PAD.top} width={band} height={innerH} fill="transparent"
                    tabIndex={0} role="button" aria-label={`${dayLabel(d.date)}: ${rupiah(d.amount)}`}
                    onMouseEnter={() => setActive(i)} onFocus={() => setActive(i)} onBlur={() => setActive(null)}
                    className="cursor-default outline-none"
                  />
                </g>
              )
            })}
          </svg>

          {active !== null && (
            <div
              className="pointer-events-none absolute -translate-x-1/2 rounded-lg bg-sidebar px-3 py-2 text-xs text-white shadow-lg"
              style={{ left: `${((PAD.left + band * active + band / 2) / W) * 100}%`, top: 0 }}
              role="status"
            >
              <p className="text-sidebar-muted">{dayLabel(data[active].date)}</p>
              <p className="font-bold tabular-nums">{rupiah(data[active].amount)}</p>
            </div>
          )}

          {empty && (
            <div className="absolute inset-0 flex items-center justify-center">
              <p className="rounded-xl bg-white/90 px-4 py-2 text-center text-xs text-ink-muted ring-1 ring-line">Belum ada pembayaran masuk dalam 7 hari terakhir.</p>
            </div>
          )}
        </div>
      )}
    </section>
  )
}
