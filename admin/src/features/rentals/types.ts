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
  late_fee: number
  late_extra_days: number
  extra_fees_total: number
  fees?: { id: number; type: string; type_label: string; amount: number; reason: string }[]
  billing: Billing
  notes: string | null
  cancel_reason: string | null
  blacklist_override: { reason: string; by?: string | null } | null
  payments?: Payment[]
  inspections?: Partial<Record<'serah_terima' | 'kembali', Inspection>>
  created_by?: string | null
  created_at: string
  can: { handover: boolean; return: boolean; cancel: boolean; add_payment: boolean; delete_payment: boolean }
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

export type FeeType = 'bbm' | 'kebersihan' | 'kelebihan_km' | 'kerusakan' | 'lainnya'

export interface ReturnPreview {
  returned_at: string
  planned_end_at: string
  start_odometer: number
  distance_km: number | null
  late: { late_minutes: number; within_grace: boolean; extra_days: number; extra_days_amount: number; charged_hours: number; hourly_fee: number; capped: boolean; total: number; late_label: string }
  fees: { type: FeeType; amount: number; reason: string }[]
  extra_fees_total: number
  rental_amount: number
  discount: number
  total_amount: number
  paid_amount: number
  balance_before: number
  deposit_held: number
  deposit_deduction_max: number
  deposit_deduction: number
  payment_amount: number
  remaining: number
  deposit_refund: number
  has_damage: boolean
  car_status: 'tersedia' | 'servis'
  car_status_suggested: 'tersedia' | 'servis'
  customer_flag: boolean
  errors: Record<string, string>
}

export interface Invoice {
  business: { name: string; tagline: string; address: string; whatsapp: string }
  code: string
  status: string
  status_label: string
  payment_status: PaymentStatus
  payment_status_label: string
  issued_at: string
  customer: { name: string; phone: string; nik_masked: string; address: string | null }
  car: { name: string; plate_number: string; year: number; color: string | null; transmission: string }
  service_type_label: string
  driver: { name: string; phone: string } | null
  schedule: { start_at: string; planned_end_at: string; picked_up_at: string | null; returned_at: string | null; billed_days: number }
  lines: { label: string; detail: string | null; amount: number }[]
  totals: { total: number; paid: number; balance: number; running_late_fee: number; estimated_due: number }
  deposit: { required: number; received: number; deducted: number; refunded: number; held: number }
  payments: { type_label: string; method_label: string; paid_at: string; reference_number: string | null; amount: number; direction: 'in' | 'out' | 'internal' }[]
  inspections: Record<'serah_terima' | 'kembali', { odometer: number; fuel_label: string; at: string; issues: string[] } | null>
  note: string | null
  terms: string[]
  footer: string
  pdf_share_url: string
  whatsapp_url: string
  whatsapp_message: string
}
