import { PUBLIC_API } from './site'

export class ApiError extends Error {
  constructor(message: string, public status: number, public errors: Record<string, string[]> = {}, public retryAfter?: number) {
    super(message)
  }
}

/** Panggilan Public API dari browser (tanpa cookie/kredensial). */
export async function publicFetch<T>(path: string, init?: RequestInit): Promise<T> {
  let res: Response
  try {
    res = await fetch(`${PUBLIC_API}${path}`, {
      ...init,
      credentials: 'omit',
      headers: { Accept: 'application/json', ...(init?.body ? { 'Content-Type': 'application/json' } : {}), ...init?.headers },
    })
  } catch {
    throw new ApiError('Tidak bisa terhubung ke server. Periksa koneksi internet Anda lalu coba lagi.', 0)
  }
  const body = await res.json().catch(() => ({}))
  if (!res.ok) {
    if (res.status === 429) {
      const after = Number(res.headers.get('Retry-After') || 60)
      throw new ApiError(`Terlalu banyak percobaan. Coba lagi dalam ${Math.ceil(after / 60)} menit.`, 429, {}, after)
    }
    throw new ApiError(body.message || 'Terjadi kesalahan. Silakan coba lagi.', res.status, body.errors || {})
  }
  return body as T
}
