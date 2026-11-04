import 'server-only'
import type { CarDetail, PublicCar, SiteSettings } from './types'

/** Semua fetch server memakai tag `public` → satu webhook revalidate menyegarkan seluruh landing. */
export const PUBLIC_TAG = 'public'

const API = (process.env.API_URL_INTERNAL || process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/public').replace(/\/$/, '')
const REVALIDATE = Number(process.env.REVALIDATE_SECONDS || 600)

class NotFound extends Error {}

async function get<T>(path: string): Promise<T> {
  const res = await fetch(`${API}${path}`, {
    headers: { Accept: 'application/json' },
    next: { tags: [PUBLIC_TAG], revalidate: REVALIDATE },
  })
  if (res.status === 404) throw new NotFound(path)
  if (!res.ok) throw new Error(`Public API ${path} → HTTP ${res.status}`)
  return ((await res.json()) as { data: T }).data
}

/**
 * Bila API tidak bisa dihubungi (mis. saat build tanpa backend), halaman tetap tampil
 * dengan konten cadangan; ISR akan mengisi data asli pada revalidate berikutnya.
 */
async function safe<T>(fn: () => Promise<T>, fallback: T): Promise<T> {
  try {
    return await fn()
  } catch (e) {
    if (e instanceof NotFound) throw e
    console.warn('[landing]', (e as Error).message)
    return fallback
  }
}

export const FALLBACK_SETTINGS: SiteSettings = {
  business: { name: 'RentalPro', tagline: null, address: null, whatsapp: null, whatsapp_url: null, city: null, operating_hours: null, maps_url: null },
  hero: { title: 'Sewa mobil bersih, nyaman, dan tepat waktu', subtitle: 'Lepas kunci atau dengan driver. Cek ketersediaan dan pesan lewat WhatsApp.' },
  how_to: [],
  terms: [],
  faq: [],
  testimonials: [],
  rental_rules: { grace_hours: 2, late_fee_per_hour: 50000 },
}

export const getSiteSettings = () => safe(() => get<SiteSettings>('/site-settings'), FALLBACK_SETTINGS)

export const getCars = () => safe(() => get<PublicCar[]>('/cars'), [] as PublicCar[])

/** null = mobil tidak ada / tidak dipublish. */
export async function getCar(slug: string): Promise<CarDetail | null> {
  try {
    return await get<CarDetail>(`/cars/${encodeURIComponent(slug)}`)
  } catch (e) {
    if (e instanceof NotFound) return null
    console.warn('[landing]', (e as Error).message)
    return null
  }
}
