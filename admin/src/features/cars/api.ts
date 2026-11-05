import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { Car, CarPhoto, CarStatus, Paginated } from '@/lib/types'

export interface CarFilters {
  search?: string
  status?: CarStatus | ''
  page?: number
}

export type CarCounts = Record<'semua' | CarStatus, number>

export const carKeys = {
  all: ['cars'] as const,
  list: (f: CarFilters) => ['cars', 'list', f] as const,
  detail: (id: number) => ['cars', 'detail', id] as const,
}

export function useCars(filters: CarFilters) {
  return useQuery({
    queryKey: carKeys.list(filters),
    queryFn: async () => {
      const { data } = await api.get<Paginated<Car, { counts: CarCounts }>>('/cars', {
        params: { search: filters.search || undefined, status: filters.status || undefined, page: filters.page, per_page: 12 },
      })
      return data
    },
    placeholderData: keepPreviousData,
  })
}

export function useCar(id: number | null) {
  return useQuery({
    queryKey: carKeys.detail(id ?? 0),
    queryFn: async () => (await api.get<{ data: Car }>(`/cars/${id}`)).data.data,
    enabled: !!id,
  })
}

export type CarPayload = Omit<Car,
  'id' | 'name' | 'transmission_label' | 'status_label' | 'cover_photo_url' | 'photos' | 'photos_count' | 'rentals_count' | 'can' | 'slug' | 'owner'
> & { slug?: string | null }

export function useSaveCar() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, payload }: { id?: number; payload: CarPayload }) => {
      const res = id
        ? await api.put<{ data: Car; message: string }>(`/cars/${id}`, payload)
        : await api.post<{ data: Car; message: string }>('/cars', payload)
      return res.data
    },
    onSuccess: ({ data }) => {
      qc.invalidateQueries({ queryKey: carKeys.all })
      qc.setQueryData(carKeys.detail(data.id), data)
    },
  })
}

export function useDeleteCar() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.delete<{ message: string }>(`/cars/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: carKeys.all }),
  })
}

function usePhotoMutation<V>(fn: (v: V) => Promise<{ data: CarPhoto[]; message: string }>) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () => qc.invalidateQueries({ queryKey: carKeys.all }),
  })
}

export function useUploadCarPhotos(carId: number) {
  return usePhotoMutation(async (files: File[]) => {
    const form = new FormData()
    files.forEach((f) => form.append('photos[]', f))
    return (await api.post(`/cars/${carId}/photos`, form)).data
  })
}

export function useSetCoverPhoto(carId: number) {
  return usePhotoMutation(async (photoId: number) => (await api.patch(`/cars/${carId}/photos/${photoId}`, { is_cover: true })).data)
}

export function useDeleteCarPhoto(carId: number) {
  return usePhotoMutation(async (photoId: number) => (await api.delete(`/cars/${carId}/photos/${photoId}`)).data)
}
