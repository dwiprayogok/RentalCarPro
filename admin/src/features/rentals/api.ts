import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { Paginated } from '@/lib/types'
import { carKeys } from '@/features/cars/api'
import type { AvailabilityBlock, ChecklistItem, FeeType, Invoice, Quote, Rental, RentalView, ReturnPreview } from './types'

export interface RentalFilters { search?: string; view?: RentalView; page?: number }

export const rentalKeys = {
  all: ['rentals'] as const,
  list: (f: RentalFilters) => ['rentals', 'list', f] as const,
  detail: (id: number) => ['rentals', 'detail', id] as const,
  availability: (carId: number, from: string, to: string) => ['rentals', 'availability', carId, from, to] as const,
}

const params = (f: RentalFilters) => ({ search: f.search || undefined, view: f.view && f.view !== 'semua' ? f.view : undefined, page: f.page })

export function useRentals(filters: RentalFilters) {
  return useQuery({
    queryKey: rentalKeys.list(filters),
    queryFn: async () => (await api.get<Paginated<Rental, { counts: Record<RentalView, number> }>>('/rentals', { params: params(filters) })).data,
    placeholderData: keepPreviousData,
    // Estimasi denda berjalan dihitung server; segarkan tiap menit.
    refetchInterval: 60_000,
  })
}

export function useRental(id: number | null) {
  return useQuery({
    queryKey: rentalKeys.detail(id ?? 0),
    queryFn: async () => (await api.get<{ data: Rental }>(`/rentals/${id}`)).data.data,
    enabled: !!id,
    refetchInterval: 60_000,
  })
}

export async function downloadRentalsCsv(filters: RentalFilters): Promise<void> {
  const res = await api.get<Blob>('/rentals/export', { params: params(filters), responseType: 'blob' })
  const name = /filename="?([^";]+)"?/.exec(res.headers['content-disposition'] ?? '')?.[1] ?? 'transaksi.csv'
  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = name
  a.click()
  URL.revokeObjectURL(url)
}

export interface QuoteInput {
  customer_id?: number | null
  car_id: number
  service_type: 'lepas_kunci' | 'dengan_driver'
  driver_id?: number | null
  start_now?: boolean
  start_at?: string | null
  planned_end_at: string
  deposit_required?: number | null
  discount?: number | null
}

export function useQuote(input: QuoteInput | null) {
  return useQuery({
    queryKey: ['rentals', 'quote', input],
    queryFn: async () => (await api.post<{ data: Quote }>('/rentals/quote', input)).data.data,
    enabled: !!input,
    placeholderData: keepPreviousData,
    staleTime: 10_000,
  })
}

export interface HandoverInput { odometer: number; fuel_level: number; checklist: ChecklistItem[]; notes?: string | null }
export interface PaymentInput { type: 'dp' | 'pelunasan' | 'deposit'; method: 'tunai' | 'transfer' | 'qris'; amount: number; reference_number?: string | null; notes?: string | null }

export interface CreateRentalInput extends QuoteInput {
  customer_id: number
  discount_reason?: string | null
  blacklist_override_reason?: string | null
  notes?: string | null
  handover?: HandoverInput | null
  payments?: PaymentInput[]
}

function useInvalidateRentals() {
  const qc = useQueryClient()
  return (rental?: Rental) => {
    qc.invalidateQueries({ queryKey: rentalKeys.all })
    qc.invalidateQueries({ queryKey: carKeys.all })
    qc.invalidateQueries({ queryKey: ['customers'] })
    if (rental) qc.setQueryData(rentalKeys.detail(rental.id), rental)
  }
}

export function useCreateRental() {
  const invalidate = useInvalidateRentals()
  return useMutation({
    mutationFn: async (input: CreateRentalInput) => (await api.post<{ data: Rental; message: string }>('/rentals', input)).data,
    onSuccess: ({ data }) => invalidate(data),
  })
}

export function useHandover(id: number) {
  const invalidate = useInvalidateRentals()
  return useMutation({
    mutationFn: async (input: HandoverInput) => (await api.post<{ data: Rental; message: string }>(`/rentals/${id}/handover`, input)).data,
    onSuccess: ({ data }) => invalidate(data),
  })
}

export function useCancelRental(id: number) {
  const invalidate = useInvalidateRentals()
  return useMutation({
    mutationFn: async (reason: string) => (await api.post<{ data: Rental; message: string }>(`/rentals/${id}/cancel`, { reason })).data,
    onSuccess: ({ data }) => invalidate(data),
  })
}

export function useAddPayment(id: number) {
  const invalidate = useInvalidateRentals()
  return useMutation({
    mutationFn: async ({ proof, ...input }: PaymentInput & { proof?: File | null }) => {
      const form = new FormData()
      Object.entries(input).forEach(([k, v]) => v !== null && v !== undefined && v !== '' && form.append(k, String(v)))
      if (proof) form.append('proof', proof)
      return (await api.post<{ data: Rental; message: string }>(`/rentals/${id}/payments`, form)).data
    },
    onSuccess: ({ data }) => invalidate(data),
  })
}

export function useDeletePayment(id: number) {
  const invalidate = useInvalidateRentals()
  return useMutation({
    mutationFn: async (paymentId: number) => (await api.delete<{ data: Rental; message: string }>(`/rentals/${id}/payments/${paymentId}`)).data,
    onSuccess: ({ data }) => invalidate(data),
  })
}

export async function uploadInspectionPhotos(rentalId: number, type: 'serah_terima' | 'kembali', files: File[]) {
  const form = new FormData()
  files.forEach((f) => form.append('photos[]', f))
  return (await api.post<{ data: Rental; message: string }>(`/rentals/${rentalId}/inspections/${type}/photos`, form)).data
}

export function useAvailability(carId: number | null, from: string, to: string) {
  return useQuery({
    queryKey: rentalKeys.availability(carId ?? 0, from, to),
    queryFn: async () => (await api.get<{ data: AvailabilityBlock[]; meta: { buffer_minutes: number; car_status: string } }>(`/cars/${carId}/availability`, { params: { from, to } })).data,
    enabled: !!carId,
    placeholderData: keepPreviousData,
  })
}

export interface ReturnInput {
  returned_at?: string | null
  odometer?: number | null
  fuel_level?: number
  checklist?: ChecklistItem[]
  notes?: string | null
  fees?: { type: FeeType; amount: number; reason: string }[]
  deposit_deduction?: number | null
  payment?: { amount: number; method: 'tunai' | 'transfer' | 'qris'; reference_number?: string | null } | null
  refund_method?: 'tunai' | 'transfer' | 'qris' | null
  car_status?: 'tersedia' | 'servis' | null
}

export function useReturnPreview(id: number, input: ReturnInput | null) {
  return useQuery({
    queryKey: ['rentals', 'return-preview', id, input],
    queryFn: async () => (await api.post<{ data: ReturnPreview }>(`/rentals/${id}/return/preview`, input)).data.data,
    enabled: !!input,
    placeholderData: keepPreviousData,
  })
}

export function useCompleteReturn(id: number) {
  const invalidate = useInvalidateRentals()
  return useMutation({
    mutationFn: async (input: ReturnInput) => (await api.post<{ data: Rental; message: string }>(`/rentals/${id}/return`, input)).data,
    onSuccess: ({ data }) => invalidate(data),
  })
}

export function useInvoice(id: number | null) {
  return useQuery({
    queryKey: ['rentals', 'invoice', id],
    queryFn: async () => (await api.get<{ data: Invoice }>(`/rentals/${id}/invoice`)).data.data,
    enabled: !!id,
  })
}

/**
 * Buka PDF nota (dibuat di server) di tab baru. Tab dibuka SEBELUM menunggu API agar tidak
 * diblokir popup blocker, lalu diarahkan ke signed URL 2 menit (tidak bergantung cookie/Referer).
 */
export async function openInvoicePdf(id: number): Promise<void> {
  const tab = window.open('', '_blank')
  try {
    const { data } = await api.post<{ data: { url: string } }>(`/rentals/${id}/invoice/print-url`)
    if (tab) tab.location.href = data.data.url
    else window.location.href = data.data.url
  } catch (e) {
    tab?.close()
    throw e
  }
}

export interface DashboardData {
  counts: { aktif: number; terlambat: number; kembali_hari_ini: number; ambil_hari_ini: number; belum_lunas: number; mobil_tersedia: number; mobil_total: number }
  receivable: number
  running_late_total: number
  overdue: Rental[]
  returns_today: Rental[]
  pickups_today: Rental[]
}

export function useDashboard() {
  return useQuery({
    queryKey: ['rentals', 'dashboard'],
    queryFn: async () => (await api.get<{ data: DashboardData }>('/dashboard')).data.data,
    refetchInterval: 60_000,
  })
}
