export type RentalStatus = 'booking' | 'aktif' | 'selesai' | 'batal'
export type RentalView = 'semua' | 'booking' | 'aktif' | 'terlambat' | 'selesai' | 'batal' | 'belum_lunas'
export type PaymentStatus = 'belum_bayar' | 'belum_lunas' | 'lunas' | 'lunas_belum_denda' | 'batal'

export interface Billing {
  total_amount: number
  paid_amount: number
  balance: number
  running_late_fee: number
  running_late: { late_minutes: number; extra_days: number; charged_hours: number; hourly_fee: number; capped: boolean; within_grace: boolean; total: number } | null
  estimated_due: number
  deposit_required: number
  deposit_held: number
  deposit_outstanding: number
  payment_status: PaymentStatus
  payment_status_label: string
}

export interface ChecklistItem { item: string; ok: boolean; note?: string | null }

export interface Inspection {
  odometer: number
  fuel_level: number
  checklist: ChecklistItem[]
  notes: string | null
  photo_urls: string[]
  inspected_at: string
}

export interface Payment {
  id: number
  type: string
  type_label: string
  method: string | null
  method_label: string | null
  amount: number
  paid_at: string
  reference_number: string | null
  notes: string | null
  proof_url: string | null
  received_by: string | null
}

export interface Rental {
  id: number
  code: string
  status: RentalStatus
  display_status: RentalStatus | 'terlambat'
  display_status_label: string
  is_overdue: boolean
  service_type: 'lepas_kunci' | 'dengan_driver'
  service_type_label: string
  customer: { id: number; name: string; initials: string; phone: string; nik_masked: string; risk_status: 'lancar' | 'perhatian' | 'blacklist'; risk_status_label: string }
  car: { id: number; name: string; plate_number: string; year: number; color: string | null; transmission_label: string; odometer: number }
  driver: { id: number; name: string; phone: string } | null
  start_at: string
  planned_end_at: string
  picked_up_at: string | null
  returned_at: string | null
  daily_rate: number
  billed_days: number
  rental_amount: number
  price_breakdown?: { billed_days: number; duration_minutes: number; lines: { date: string; rate: number; special: boolean }[] } | null
  discount: number
  discount_reason: string | null
  billing: Billing
  notes: string | null
  cancel_reason: string | null
  blacklist_override: { reason: string; by?: string | null } | null
  payments?: Payment[]
  inspections?: Partial<Record<'serah_terima' | 'kembali', Inspection>>
  created_by?: string | null
  created_at: string
  can: { handover: boolean; cancel: boolean; add_payment: boolean; delete_payment: boolean }
}

export interface Quote {
  pricing: { billed_days: number; duration_minutes: number; grace_minutes: number; base_rate: number; lines: { date: string; rate: number; special: boolean }[]; rental_amount: number } | null
  daily_rate: number
  rental_amount: number
  discount: number
  total_amount: number
  deposit_required: number
  car_odometer: number
  buffer_minutes: number
  available: boolean
  errors: Record<string, string>
  warnings: string[]
}

export interface AvailabilityBlock {
  rental_id: number
  code: string
  status: RentalStatus
  is_overdue: boolean
  customer_name: string | null
  start_at: string
  end_at: string
  blocked_until: string
}
