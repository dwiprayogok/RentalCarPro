export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  is_active: boolean
  roles: string[]
  permissions: string[]
  last_login_at: string | null
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Paginated<T, M = object> {
  data: T[]
  meta: PaginationMeta & M
}

export type CarStatus = 'tersedia' | 'disewa' | 'servis' | 'nonaktif'

export interface CarPhoto {
  id: number
  url: string
  caption: string | null
  is_cover: boolean
  sort_order: number
}

export interface Car {
  id: number
  name: string
  brand: string
  model: string
  plate_number: string
  owner_id: number | null
  owner: { id: number; name: string; share_label: string } | null
  transmission: 'matic' | 'manual'
  transmission_label: string
  year: number
  color: string | null
  seats: number
  fuel_type: string | null
  rate_self_drive: number
  rate_with_driver: number
  weekend_rate_self_drive: number | null
  weekend_rate_with_driver: number | null
  default_deposit: number
  odometer: number
  status: CarStatus
  status_label: string
  tax_due_date: string | null
  stnk_due_date: string | null
  next_service_date: string | null
  next_service_odometer: number | null
  service_interval_km: number | null
  slug: string
  is_published: boolean
  description: string | null
  features: string[]
  notes: string | null
  cover_photo_url: string | null
  photos?: CarPhoto[]
  photos_count?: number
  rentals_count?: number
  can: { update: boolean; delete: boolean; publish: boolean }
}

export type RiskStatus = 'lancar' | 'perhatian' | 'blacklist'

export interface CustomerDocument {
  uploaded: boolean
  url: string | null
}

export interface Customer {
  id: number
  name: string
  initials: string
  phone: string
  nik_masked: string
  sim_number: string | null
  sim_type: string | null
  sim_expires_at: string | null
  address: string | null
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  emergency_contact_relation: string | null
  self_drive_ready: boolean
  self_drive_missing: string[]
  risk_status: RiskStatus
  risk_status_label: string
  risk_note: string | null
  risk_updated_at: string | null
  notes: string | null
  documents: { ktp: CustomerDocument; sim: CustomerDocument }
  rentals_count?: number
  created_at: string
  can: { update: boolean; delete: boolean; change_risk: boolean; manage_documents: boolean }
}

export interface RentalSummary {
  id: number
  code: string
  car?: { id: number; name: string; plate_number: string }
  service_type: string
  service_type_label: string
  status: string
  status_label: string
  is_overdue: boolean
  start_at: string
  planned_end_at: string
  returned_at: string | null
  total_amount: number
  paid_amount: number
  balance: number
}
