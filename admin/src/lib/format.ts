const TZ = 'Asia/Jakarta'

const rupiahFmt = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const numberFmt = new Intl.NumberFormat('id-ID')

/** Rp 550.000 */
export function rupiah(value: number | null | undefined): string {
  return rupiahFmt.format(value ?? 0).replace(/\u00a0/g, ' ')
}

export function number(value: number | null | undefined): string {
  return numberFmt.format(value ?? 0)
}

/** "21 Okt 2026, 15.41" dalam WIB. */
export function dateTimeWib(iso: string | null | undefined): string {
  if (!iso) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    timeZone: TZ, day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  }).format(new Date(iso))
}

/** Tanggal kalender "YYYY-MM-DD" → "21 Okt 2026" (tanpa geser zona waktu). */
export function dateOnly(ymd: string | null | undefined): string {
  if (!ymd) return '-'
  const [y, m, d] = ymd.split('-').map(Number)
  return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' })
    .format(new Date(Date.UTC(y, m - 1, d)))
}

/** Selisih hari dari hari ini (WIB) ke tanggal kalender. Negatif = lewat. */
export function daysUntil(ymd: string | null | undefined): number | null {
  if (!ymd) return null
  const todayWib = new Intl.DateTimeFormat('en-CA', { timeZone: TZ }).format(new Date())
  const toUtc = (s: string) => {
    const [y, m, d] = s.split('-').map(Number)
    return Date.UTC(y, m - 1, d)
  }
  return Math.round((toUtc(ymd) - toUtc(todayWib)) / 86_400_000)
}

/** +6281385270311 → +62 813-8527-0311 */
export function phone(value: string | null | undefined): string {
  if (!value) return '-'
  const m = value.match(/^\+62(\d{3})(\d{4})(\d+)$/)
  return m ? `+62 ${m[1]}-${m[2]}-${m[3]}` : value
}

/** Nomor HP untuk tautan wa.me. */
export function waLink(value: string): string {
  return `https://wa.me/${value.replace(/\D/g, '')}`
}

/** "Dwi Prayogo" → "DP" */
export function initialsOf(name: string): string {
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?'
}

/** Rupiah ringkas untuk sumbu/label grafik: 1.250.000 → "1,25 jt", 950.000 → "950 rb". */
export function rupiahCompact(value: number): string {
  const abs = Math.abs(value)
  const fmt = (n: number) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(n)
  if (abs >= 1_000_000_000) return `${fmt(value / 1_000_000_000)} M`
  if (abs >= 1_000_000) return `${fmt(value / 1_000_000)} jt`
  if (abs >= 1_000) return `${fmt(value / 1_000)} rb`
  return fmt(value)
}
