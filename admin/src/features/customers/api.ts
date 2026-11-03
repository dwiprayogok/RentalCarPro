import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { Customer, Paginated, RentalSummary, RiskStatus } from '@/lib/types'

export interface CustomerFilters {
  search?: string
  risk_status?: RiskStatus | ''
  page?: number
}

export type RiskCounts = Record<'semua' | RiskStatus, number>

export const customerKeys = {
  all: ['customers'] as const,
  list: (f: CustomerFilters) => ['customers', 'list', f] as const,
  detail: (id: number) => ['customers', 'detail', id] as const,
  rentals: (id: number, page: number) => ['customers', 'rentals', id, page] as const,
}

export function useCustomers(filters: CustomerFilters) {
  return useQuery({
    queryKey: customerKeys.list(filters),
    queryFn: async () => (await api.get<Paginated<Customer, { counts: RiskCounts }>>('/customers', {
      params: { search: filters.search || undefined, risk_status: filters.risk_status || undefined, page: filters.page },
    })).data,
    placeholderData: keepPreviousData,
  })
}

export function useCustomer(id: number | null) {
  return useQuery({
    queryKey: customerKeys.detail(id ?? 0),
    queryFn: async () => (await api.get<{ data: Customer }>(`/customers/${id}`)).data.data,
    enabled: !!id,
    // Signed URL dokumen berlaku 10 menit; segarkan sebelum kedaluwarsa.
    staleTime: 5 * 60_000,
  })
}

export function useCustomerRentals(id: number | null, page: number) {
  return useQuery({
    queryKey: customerKeys.rentals(id ?? 0, page),
    queryFn: async () => (await api.get<Paginated<RentalSummary>>(`/customers/${id}/rentals`, { params: { page } })).data,
    enabled: !!id,
    placeholderData: keepPreviousData,
  })
}

export interface CustomerPayload {
  name: string
  phone: string
  nik: string | null
  for_self_drive: boolean
  sim_number: string | null
  sim_type: string | null
  sim_expires_at: string | null
  address: string | null
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  emergency_contact_relation: string | null
  risk_status: RiskStatus
  risk_note: string | null
  notes: string | null
}

export function useSaveCustomer() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, payload }: { id?: number; payload: CustomerPayload }) =>
      (id
        ? await api.put<{ data: Customer; message: string }>(`/customers/${id}`, payload)
        : await api.post<{ data: Customer; message: string }>('/customers', payload)
      ).data,
    onSuccess: ({ data }) => {
      qc.invalidateQueries({ queryKey: customerKeys.all })
      qc.setQueryData(customerKeys.detail(data.id), data)
    },
  })
}

export function useUploadCustomerDocument() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, type, file }: { id: number; type: 'ktp' | 'sim'; file: File }) => {
      const form = new FormData()
      form.append('type', type)
      form.append('file', file)
      return (await api.post<{ data: Customer; message: string }>(`/customers/${id}/documents`, form)).data
    },
    onSuccess: ({ data }) => {
      qc.setQueryData(customerKeys.detail(data.id), data)
      qc.invalidateQueries({ queryKey: customerKeys.all })
    },
  })
}

export function useDeleteCustomerDocument() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, type }: { id: number; type: 'ktp' | 'sim' }) =>
      (await api.delete<{ data: Customer; message: string }>(`/customers/${id}/documents/${type}`)).data,
    onSuccess: ({ data }) => {
      qc.setQueryData(customerKeys.detail(data.id), data)
      qc.invalidateQueries({ queryKey: customerKeys.all })
    },
  })
}

export function useDeleteCustomer() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete<{ message: string }>(`/customers/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: customerKeys.all }),
  })
}
