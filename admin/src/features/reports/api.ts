import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

export type ReportTab = 'pembayaran' | 'piutang' | 'pendapatan' | 'utilisasi' | 'servis'

export const REPORT_ENDPOINT: Record<ReportTab, string> = {
  pembayaran: 'payments',
  piutang: 'receivables',
  pendapatan: 'revenue-by-car',
  utilisasi: 'utilization',
  servis: 'maintenance',
}

export interface ReportFilters { from: string; to: string; method?: string; car_id?: string; page?: number }

const params = (f: ReportFilters) => ({
  from: f.from, to: f.to, method: f.method || undefined, car_id: f.car_id || undefined, page: f.page, per_page: 20,
})

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function useReport<T = any>(tab: ReportTab, f: ReportFilters) {
  return useQuery({
    queryKey: ['reports', tab, f],
    queryFn: async () => (await api.get<T>(`/reports/${REPORT_ENDPOINT[tab]}`, { params: params(f) })).data,
    placeholderData: keepPreviousData,
  })
}

export async function downloadReportCsv(tab: ReportTab, f: ReportFilters): Promise<void> {
  const res = await api.get<Blob>(`/reports/${REPORT_ENDPOINT[tab]}/export`, { params: { ...params(f), page: undefined, per_page: undefined }, responseType: 'blob' })
  const name = /filename="?([^";]+)"?/.exec(res.headers['content-disposition'] ?? '')?.[1] ?? `laporan-${tab}.csv`
  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = name
  a.click()
  URL.revokeObjectURL(url)
}
