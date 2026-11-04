/**
 * Input <input type="datetime-local"> selalu diperlakukan sebagai WIB (UTC+7),
 * apa pun zona waktu perangkat pengguna. Dikirim ke API sebagai ISO dengan offset +07:00.
 */
const WIB_OFFSET_MIN = 7 * 60

/** Date → "YYYY-MM-DDTHH:mm" dalam WIB. */
export function toWibInput(date: Date): string {
  const wib = new Date(date.getTime() + WIB_OFFSET_MIN * 60_000)
  return wib.toISOString().slice(0, 16)
}

/** "YYYY-MM-DDTHH:mm" (WIB) → "YYYY-MM-DDTHH:mm:00+07:00". */
export function fromWibInput(value: string): string {
  return `${value}:00+07:00`
}

/** "YYYY-MM-DDTHH:mm" (WIB) → Date. */
export function parseWibInput(value: string): Date {
  return new Date(fromWibInput(value))
}

/** Waktu sekarang dibulatkan ke atas ke kelipatan 30 menit, sebagai input WIB. */
export function nextHalfHourWib(addHours = 0): string {
  const d = new Date(Date.now() + addHours * 3_600_000)
  d.setUTCSeconds(0, 0)
  const m = d.getUTCMinutes()
  d.setUTCMinutes(m === 0 ? 0 : m <= 30 ? 30 : 60)
  return toWibInput(d)
}

export function addHoursToWibInput(value: string, hours: number): string {
  return toWibInput(new Date(parseWibInput(value).getTime() + hours * 3_600_000))
}

/** "YYYY-MM-DD" hari ini di WIB. */
export function todayWib(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date())
}

/** Durasi menit → "2 hari 3 jam". */
export function humanDuration(minutes: number): string {
  if (minutes <= 0) return '0 jam'
  const d = Math.floor(minutes / 1440)
  const h = Math.floor((minutes % 1440) / 60)
  const m = minutes % 60
  return [d && `${d} hari`, h && `${h} jam`, !d && m && `${m} menit`].filter(Boolean).join(' ') || '0 jam'
}
