/** Sama dengan App\Support\Phone di backend. */
export function normalizePhone(input: string): string {
  const raw = input.trim()
  if (!raw) return ''
  const digits = raw.replace(/\D/g, '')
  if (raw.startsWith('+')) return `+${digits}`
  if (digits.startsWith('62')) return `+${digits}`
  if (digits.startsWith('0')) return `+62${digits.slice(1)}`
  if (digits.startsWith('8')) return `+62${digits}`
  return digits ? `+${digits}` : ''
}

export function isValidIndonesianPhone(input: string): boolean {
  return /^\+628\d{7,12}$/.test(normalizePhone(input))
}
