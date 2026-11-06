export const rupiah = (n: number) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n))

export const rupiahShort = (n: number) => {
  if (n >= 1_000_000) return `Rp ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(n / 1_000_000)} jt`
  if (n >= 1_000) return `Rp ${new Intl.NumberFormat('id-ID').format(Math.round(n / 1_000))} rb`
  return rupiah(n)
}

/** "2026-11-05" → "5 Nov 2026" */
export const dateId = (ymd: string) =>
  new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${ymd}T00:00:00Z`))

/** Nilai <input type="datetime-local"> dianggap WIB → ISO dengan offset +07:00 untuk API. */
export const wibToIso = (local: string) => (local ? `${local.length === 16 ? local + ':00' : local}+07:00` : '')

/** Waktu sekarang (WIB) dibulatkan ke jam berikutnya + offset jam, format datetime-local. */
export function wibLocal(hoursFromNow: number, base = Date.now()): string {
  const d = new Date(base + 7 * 3600_000 + hoursFromNow * 3600_000)
  d.setUTCMinutes(0, 0, 0)
  return d.toISOString().slice(0, 16)
}

export const waLink = (url: string | null | undefined, text: string) =>
  url ? `${url}?text=${encodeURIComponent(text)}` : null

/** Besok jam `hour` WIB (+ `days` hari), format datetime-local. */
export function wibTomorrowAt(hour: number, days = 1, base = Date.now()): string {
  const d = new Date(base + 7 * 3600_000)
  d.setUTCDate(d.getUTCDate() + days)
  d.setUTCHours(hour, 0, 0, 0)
  return d.toISOString().slice(0, 16)
}
