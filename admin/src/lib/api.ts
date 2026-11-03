import axios, { AxiosError, type AxiosRequestConfig } from 'axios'

export const API_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000').replace(/\/$/, '')

/**
 * Klien API untuk Sanctum SPA (cookie). Axios membaca cookie XSRF-TOKEN dan mengirimkannya
 * sebagai header X-XSRF-TOKEN (withXSRFToken diperlukan karena API beda port/subdomain).
 */
export const api = axios.create({
  baseURL: `${API_URL}/api/v1`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

export async function ensureCsrfCookie(): Promise<void> {
  await axios.get(`${API_URL}/sanctum/csrf-cookie`, { withCredentials: true, withXSRFToken: true })
}

// 419 (CSRF kedaluwarsa): ambil cookie baru lalu ulangi sekali.
api.interceptors.response.use(undefined, async (error: AxiosError) => {
  const config = error.config as (AxiosRequestConfig & { _retried?: boolean }) | undefined
  if (error.response?.status === 419 && config && !config._retried) {
    config._retried = true
    await ensureCsrfCookie()
    return api.request(config)
  }
  return Promise.reject(error)
})

export interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

export function isAxiosError(error: unknown): error is AxiosError<ApiErrorBody> {
  return axios.isAxiosError(error)
}

export function errorStatus(error: unknown): number | undefined {
  return isAxiosError(error) ? error.response?.status : undefined
}

/** Pesan error yang ramah untuk ditampilkan ke pengguna. */
export function errorMessage(error: unknown, fallback = 'Terjadi kesalahan. Silakan coba lagi.'): string {
  if (isAxiosError(error)) {
    if (!error.response) return 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.'
    const body = error.response.data
    const firstFieldError = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined
    if (error.response.status === 422 && firstFieldError) return firstFieldError
    if (body?.message) return body.message
    if (error.response.status >= 500) return 'Server sedang bermasalah. Silakan coba beberapa saat lagi.'
  }
  return fallback
}

export function fieldErrors(error: unknown): Record<string, string> {
  if (!isAxiosError(error) || error.response?.status !== 422) return {}
  const out: Record<string, string> = {}
  for (const [key, messages] of Object.entries(error.response.data?.errors ?? {})) {
    // "features.0" → "features", "photos.1" → "photos"
    const field = key.replace(/\.\d+$/, '')
    out[field] ??= messages[0]
  }
  return out
}
