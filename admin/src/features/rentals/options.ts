import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { Car, Customer, Paginated } from '@/lib/types'
import { useDebounce } from '@/lib/useDebounce'

export interface DriverOption { id: number; name: string; phone: string; license_expired: boolean; is_active: boolean }

function useOptions<T>(key: string, url: string, search: string, extra: Record<string, unknown> = {}) {
  const q = useDebounce(search.trim(), 250)
  return useQuery({
    queryKey: ['options', key, q, extra],
    queryFn: async () => (await api.get<Paginated<T>>(url, { params: { search: q || undefined, per_page: 20, ...extra } })).data.data,
    staleTime: 30_000,
  })
}

export const useCustomerOptions = (search: string) => useOptions<Customer>('customers', '/customers', search, { sort: 'name' })
export const useCarOptions = (search: string) => useOptions<Car>('cars', '/cars', search, { sort: 'name' })
export const useDriverOptions = (search: string) => useOptions<DriverOption>('drivers', '/drivers', search, { is_active: 1 })
