export type ServiceType = 'lepas_kunci' | 'dengan_driver'

export type Period = {
  available: boolean
  service: ServiceType
  days: number
  estimated_total: number
  deposit: number
}

export type PublicCar = {
  slug: string
  name: string
  brand: string
  model: string
  year: number
  transmission: string
  seats: number
  fuel_type: string | null
  color: string | null
  rate_self_drive: number
  rate_with_driver: number | null
  weekend_rate_self_drive: number | null
  weekend_rate_with_driver: number | null
  deposit: number
  features: string[]
  description: string | null
  cover_photo_url: string | null
  photos: { url: string; caption: string | null }[]
  availability: { status: 'tersedia' | 'disewa' | 'perawatan'; label: string }
  period?: Period
}

export type CarDetail = PublicCar & {
  calendar: { from: string; to: string; unavailable: { start: string; end: string }[] }
}

export type Faq = { q: string; a: string }
export type Testimonial = { name: string; city: string | null; text: string; rating: number }

export type SiteSettings = {
  business: {
    name: string
    tagline: string | null
    address: string | null
    whatsapp: string | null
    whatsapp_url: string | null
    city: string | null
    operating_hours: string | null
    maps_url: string | null
  }
  hero: { title: string | null; subtitle: string | null }
  how_to: string[]
  terms: string[]
  faq: Faq[]
  testimonials: Testimonial[]
  rental_rules: { grace_hours: number; late_fee_per_hour: number }
}
