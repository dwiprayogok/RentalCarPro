import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { CarStatus, Paginated } from '@/lib/types'

export type ShareBasis = 'kotor' | 'bersih'

export interface CarOwner {
  id: number
  name: string
  phone: string
  email: string | null
  address: string | null
  bank_name: string | null
  bank_account_number: string | null
  bank_account_name: string | null
  share_basis: ShareBasis
  share_basis_label: string
  share_percent: number
  share_label: string
  notes: string | null
  is_active: boolean
  cars_count?: number
  unpaid_total?: number
  unpaid_count?: number
  last_period_to?: string | null
  cars?: { id: number; name: string; plate_number: string; status: CarStatus; status_label: string }[]
  can: { update: boolean; delete: boolean }
}

export interface SettlementLine {
  car_id: number
  car: string
  plate_number: string
  rentals: number
  billed_days: number
  income: number
  maintenance: number
  expenses: number
  net: number
}

export interface SettlementCalc {
  period_from: string
  period_to: string
  share_basis: ShareBasis
  share_basis_label: string
  share_percent: number
  share_label: string
  income: number
  maintenance: number
  expenses: number
  net: number
  base_amount: number
  owner_amount: number
  business_amount: number
  loss: boolean
  lines: SettlementLine[]
}

export interface Settlement {
  id: number
  code: string
  owner: { id: number; name: string; phone: string }
  period_from: string
  period_to: string
  share_basis: ShareBasis
  share_basis_label: string
  share_percent: number
  income: number
  maintenance: number
  expenses: number
  net: number
  base_amount: number
  owner_amount: number
  business_amount: number
  lines?: SettlementLine[]
  notes: string | null
  status: 'belum_dibayar' | 'dibayar'
  status_label: string
  paid_at: string | null
  paid_method: string | null
  paid_method_label: string | null
  paid_reference: string | null
  paid_by?: string | null
  proof_url?: string | null
  whatsapp_url?: string | null
  created_by?: string | null
  created_at: string
  can: { pay: boolean; delete: boolean }
}

export type OwnerPayload = Omit<CarOwner, 'id' | 'share_basis_label' | 'share_label' | 'cars_count' | 'unpaid_total' | 'unpaid_count' | 'last_period_to' | 'cars' | 'can'>

const invalidate = (qc: ReturnType<typeof useQueryClient>) => {
  qc.invalidateQueries({ queryKey: ['car-owners'] })
  qc.invalidateQueries({ queryKey: ['settlements'] })
}

export function useOwners(search: string, status = '', enabled = true) {
  return useQuery({
    enabled,
    queryKey: ['car-owners', { search, status }],
    queryFn: async () => (await api.get<Paginated<CarOwner>>('/car-owners', { params: { search: search || undefined, status: status || undefined, per_page: 100 } })).data,
    placeholderData: keepPreviousData,
  })
}

export function useOwner(id: number | null) {
  return useQuery({
    queryKey: ['car-owners', 'detail', id],
    queryFn: async () => (await api.get<{ data: CarOwner }>(`/car-owners/${id}`)).data.data,
    enabled: !!id,
  })
}

export function useSaveOwner() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, payload }: { id?: number; payload: OwnerPayload }) =>
      (id ? await api.put<{ data: CarOwner; message: string }>(`/car-owners/${id}`, payload) : await api.post<{ data: CarOwner; message: string }>('/car-owners', payload)).data,
    onSuccess: () => { invalidate(qc); qc.invalidateQueries({ queryKey: ['cars'] }) },
  })
}

export function useDeleteOwner() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete<{ message: string }>(`/car-owners/${id}`)).data,
    onSuccess: () => invalidate(qc),
  })
}

export function useSettlementPreview(ownerId: number | null, from: string, to: string) {
  return useQuery({
    queryKey: ['settlements', 'preview', ownerId, from, to],
    queryFn: async () => (await api.get<{ data: SettlementCalc }>(`/car-owners/${ownerId}/settlement-preview`, { params: { from, to } })).data.data,
    enabled: !!ownerId && !!from && !!to && from <= to,
    placeholderData: keepPreviousData,
  })
}

export function useSettlements(f: { owner_id?: number | null; status?: string; page?: number }) {
  return useQuery({
    queryKey: ['settlements', 'list', f],
    queryFn: async () => (await api.get<Paginated<Settlement, { unpaid: { count: number; total: number } }>>('/settlements', {
      params: { owner_id: f.owner_id || undefined, status: f.status || undefined, page: f.page },
    })).data,
    placeholderData: keepPreviousData,
  })
}

export function useSettlement(id: number | null) {
  return useQuery({
    queryKey: ['settlements', 'detail', id],
    queryFn: async () => (await api.get<{ data: Settlement }>(`/settlements/${id}`)).data.data,
    enabled: !!id,
  })
}

export function useCreateSettlement() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (p: { owner_id: number; from: string; to: string; notes: string | null }) =>
      (await api.post<{ data: Settlement; message: string }>('/settlements', p)).data,
    onSuccess: () => invalidate(qc),
  })
}

export function usePaySettlement(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (p: { method: string; paid_at: string; reference: string; proof: File | null }) => {
      const fd = new FormData()
      fd.append('method', p.method)
      if (p.paid_at) fd.append('paid_at', p.paid_at)
      if (p.reference) fd.append('reference', p.reference)
      if (p.proof) fd.append('proof', p.proof)
      return (await api.post<{ data: Settlement; message: string }>(`/settlements/${id}/pay`, fd)).data
    },
    onSuccess: () => invalidate(qc),
  })
}

export function useCancelSettlement() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete<{ message: string }>(`/settlements/${id}`)).data,
    onSuccess: () => invalidate(qc),
  })
}

/** Buka slip PDF di tab baru (tab dibuka dulu agar tidak diblokir popup blocker). */
export async function openSettlementPdf(id: number): Promise<void> {
  const tab = window.open('', '_blank')
  try {
    const { data } = await api.post<{ data: { url: string } }>(`/settlements/${id}/print-url`)
    if (tab) tab.location.href = data.data.url
    else window.location.href = data.data.url
  } catch (e) {
    tab?.close()
    throw e
  }
}

/** Rentang preset tanggal WIB untuk setoran. */
export function periodPreset(kind: 'bulan_lalu' | 'bulan_ini', today: string): { from: string; to: string } {
  const [y, m] = today.split('-').map(Number)
  const fmt = (d: Date) => d.toISOString().slice(0, 10)
  if (kind === 'bulan_ini') return { from: fmt(new Date(Date.UTC(y, m - 1, 1))), to: today }
  return { from: fmt(new Date(Date.UTC(y, m - 2, 1))), to: fmt(new Date(Date.UTC(y, m - 1, 0))) }
}
