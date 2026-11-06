import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { Paginated } from '@/lib/types'

export type ExpenseCategory = 'bbm' | 'cuci' | 'parkir_tol' | 'asuransi' | 'cicilan' | 'gaji' | 'sewa_tempat' | 'utilitas' | 'pemasaran' | 'lainnya'

export interface Expense {
  id: number
  car_id: number | null
  car: { id: number; name: string; plate_number: string } | null
  category: ExpenseCategory
  category_label: string
  title: string
  spent_at: string
  amount: number
  method: 'tunai' | 'transfer' | 'qris' | null
  method_label: string | null
  vendor: string | null
  notes: string | null
  has_proof: boolean
  proof_url: string | null
  created_by: string | null
  created_at: string
  can: { update: boolean; delete: boolean }
}

export interface ExpenseMeta {
  summary: { total: number; count: number; by_category: { category: ExpenseCategory; label: string; total: number }[] }
  categories: { value: ExpenseCategory; label: string; per_car: boolean }[]
}

export interface ExpenseFilters { from: string; to: string; car_id: string; category: string; search: string; page: number }

export function useExpenses(f: ExpenseFilters) {
  return useQuery({
    queryKey: ['expenses', f],
    queryFn: async () => (await api.get<Paginated<Expense, ExpenseMeta>>('/expenses', {
      params: { from: f.from, to: f.to, car_id: f.car_id || undefined, category: f.category || undefined, search: f.search || undefined, page: f.page },
    })).data,
    placeholderData: keepPreviousData,
  })
}

export interface ExpensePayload {
  car_id: number | null
  category: ExpenseCategory
  title: string
  spent_at: string
  amount: number
  method: string | null
  vendor: string | null
  notes: string | null
  proof: File | null
  remove_proof: boolean
}

/** Multipart agar bukti bisa ikut. Ubah memakai POST + _method=PUT (Laravel method spoofing). */
function toFormData(p: ExpensePayload, update: boolean): FormData {
  const fd = new FormData()
  if (update) fd.append('_method', 'PUT')
  fd.append('car_id', p.car_id ? String(p.car_id) : '')
  fd.append('category', p.category)
  fd.append('title', p.title)
  fd.append('spent_at', p.spent_at)
  fd.append('amount', String(p.amount))
  if (p.method) fd.append('method', p.method)
  if (p.vendor) fd.append('vendor', p.vendor)
  if (p.notes) fd.append('notes', p.notes)
  if (p.proof) fd.append('proof', p.proof)
  if (p.remove_proof) fd.append('remove_proof', '1')
  return fd
}

export function useSaveExpense() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, payload }: { id?: number; payload: ExpensePayload }) =>
      (await api.post<{ data: Expense; message: string }>(id ? `/expenses/${id}` : '/expenses', toFormData(payload, !!id))).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['expenses'] })
      qc.invalidateQueries({ queryKey: ['reports'] })
    },
  })
}

export function useDeleteExpense() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete<{ message: string }>(`/expenses/${id}`)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['expenses'] })
      qc.invalidateQueries({ queryKey: ['reports'] })
    },
  })
}

/** Daftar mobil untuk pilihan (maks. 100, termasuk nonaktif) — untuk filter & form. */
export function useCarList() {
  return useQuery({
    queryKey: ['options', 'cars-all'],
    queryFn: async () => (await api.get<Paginated<{ id: number; name: string; plate_number: string }>>('/cars', { params: { per_page: 100, sort: 'name' } })).data.data,
    staleTime: 60_000,
  })
}
