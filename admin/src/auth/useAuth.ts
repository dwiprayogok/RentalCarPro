import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback } from 'react'
import { api, ensureCsrfCookie, errorStatus } from '@/lib/api'
import type { User } from '@/lib/types'

export const ME_KEY = ['auth', 'me'] as const

async function fetchMe(): Promise<User | null> {
  try {
    const { data } = await api.get<{ data: User }>('/auth/me')
    return data.data
  } catch (error) {
    if (errorStatus(error) === 401 || errorStatus(error) === 403) return null
    throw error
  }
}

export function useMe() {
  return useQuery({ queryKey: ME_KEY, queryFn: fetchMe, staleTime: 5 * 60_000, retry: 1 })
}

export function useCan() {
  const { data: user } = useMe()
  return useCallback(
    (...permissions: string[]) => !!user && permissions.some((p) => user.permissions.includes(p)),
    [user],
  )
}

export function useLogin() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (input: { email: string; password: string; remember: boolean }) => {
      await ensureCsrfCookie()
      const { data } = await api.post<{ data: User }>('/auth/login', input)
      return data.data
    },
    onSuccess: (user) => qc.setQueryData(ME_KEY, user),
  })
}

export function useLogout() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.post('/auth/logout'),
    onSettled: () => {
      qc.clear()
      qc.setQueryData(ME_KEY, null)
    },
  })
}

export function useForgotPassword() {
  return useMutation({
    mutationFn: async (email: string) => {
      const { data } = await api.post<{ message: string }>('/auth/forgot-password', { email })
      return data.message
    },
  })
}

export function useResetPassword() {
  return useMutation({
    mutationFn: async (input: { token: string; email: string; password: string; password_confirmation: string }) => {
      const { data } = await api.post<{ message: string }>('/auth/reset-password', input)
      return data.message
    },
  })
}
