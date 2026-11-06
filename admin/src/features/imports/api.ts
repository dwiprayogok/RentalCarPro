import { useMutation } from '@tanstack/react-query'
import { api } from '@/lib/api'

export type ImportType = 'armada' | 'pelanggan' | 'driver'

export interface PreviewRow {
  row: number
  summary: string
  status: 'baru' | 'lewati' | 'error'
  message: string | null
  errors: { field: string | null; message: string }[]
}

export interface Preview {
  token: string | null
  expires_at: string | null
  file: string
  summary: { total: number; baru: number; lewati: number; error: number }
  rows: PreviewRow[]
}

export interface CommitResult {
  created: number
  skipped: { row: number; message: string }[]
}

export async function downloadTemplate(type: ImportType): Promise<void> {
  const res = await api.get<Blob>(`/imports/${type}/template`, { responseType: 'blob' })
  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = `template-import-${type}.xlsx`
  a.click()
  URL.revokeObjectURL(url)
}

export function usePreviewImport(type: ImportType) {
  return useMutation({
    mutationFn: async (file: File) => {
      const fd = new FormData()
      fd.append('file', file)
      return (await api.post<{ data: Preview }>(`/imports/${type}/preview`, fd)).data.data
    },
  })
}

export function useCommitImport(type: ImportType) {
  return useMutation({
    mutationFn: async (token: string) => (await api.post<{ data: CommitResult; message: string }>(`/imports/${type}/commit`, { token })).data,
  })
}
