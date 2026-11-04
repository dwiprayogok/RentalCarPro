import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Inbox, MessageCircle, Phone, X } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, RiskBadge, type Tone } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Feedback'
import { Field, Textarea } from '@/components/ui/Field'
import { FilterChips } from '@/components/ui/FilterChips'
import { Modal } from '@/components/ui/Modal'
import { Pagination } from '@/components/ui/Pagination'
import { SearchInput } from '@/components/ui/SearchInput'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { CustomerFormModal } from '@/features/customers/CustomerFormModal'
import { NewRentalModal, type RentalPrefill } from '@/features/rentals/NewRentalModal'
import { RentalDetailModal } from '@/features/rentals/RentalDetailModal'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import { toWibInput } from '@/lib/datetime'
import { dateTimeWib, phone as fmtPhone, waLink } from '@/lib/format'
import type { Car, Customer, Paginated } from '@/lib/types'
import { useDebounce } from '@/lib/useDebounce'

type Status = 'baru' | 'dihubungi' | 'dikonfirmasi' | 'ditolak'
interface BookingRequest {
  id: number
  name: string
  phone: string
  car: { id: number; name: string; plate_number: string; status: string } | null
  start_at: string
  end_at: string
  service_type: 'lepas_kunci' | 'dengan_driver'
  service_type_label: string
  notes: string | null
  status: Status
  status_label: string
  rejection_reason: string | null
  handled_by: string | null
  handled_at: string | null
  rental: { id: number; code: string } | null
  matched_customer: { id: number; name: string; risk_status: 'lancar' | 'perhatian' | 'blacklist'; risk_status_label: string; self_drive_ready: boolean } | null
  created_at: string
  can: { update: boolean; convert: boolean }
}

const tone: Record<Status, Tone> = { baru: 'blue', dihubungi: 'yellow', dikonfirmasi: 'green', ditolak: 'gray' }

function useUpdateStatus() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, status, rejection_reason }: { id: number; status: Status; rejection_reason?: string }) =>
      (await api.patch<{ data: BookingRequest; message: string }>(`/booking-requests/${id}`, { status, rejection_reason })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['booking-requests'] }),
  })
}

function RejectModal({ req, onClose }: { req: BookingRequest | null; onClose: () => void }) {
  const update = useUpdateStatus()
  const toast = useToast()
  const [reason, setReason] = useState('')
  const err = update.error ? fieldErrors(update.error).rejection_reason ?? errorMessage(update.error) : undefined
  return (
    <Modal open={!!req} onClose={onClose} size="sm" title="Tolak permintaan?" description={req ? `${req.name} · ${req.car?.name ?? 'mobil apa saja'}` : undefined}
      footer={<><Button variant="secondary" onClick={onClose}>Batal</Button>
        <Button variant="danger" loading={update.isPending} onClick={() => req && update.mutate({ id: req.id, status: 'ditolak', rejection_reason: reason }, { onSuccess: (r) => { toast(r.message); setReason(''); onClose() } })}>Tolak</Button></>}>
      <Field label="Alasan penolakan" required error={err}>{(a) => <Textarea {...a} rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Mis. mobil sudah terpesan di tanggal tersebut." />}</Field>
    </Modal>
  )
}

export function BookingRequestsPage() {
  const toast = useToast()
  const qc = useQueryClient()
  const [params, setParams] = useSearchParams()
  const status = (params.get('status') ?? '') as '' | Status
  const page = Number(params.get('page') ?? 1)
  const [search, setSearch] = useState('')
  const q = useDebounce(search.trim())
  const update = useUpdateStatus()
  const [rejecting, setRejecting] = useState<BookingRequest | null>(null)
  const [customerFor, setCustomerFor] = useState<BookingRequest | null>(null)
  const [prefill, setPrefill] = useState<RentalPrefill | null>(null)
  const [openRental, setOpenRental] = useState<number | null>(null)

  const query = useQuery({
    queryKey: ['booking-requests', { status, page, q }],
    queryFn: async () => (await api.get<Paginated<BookingRequest, { counts: Record<'semua' | Status, number> }>>('/booking-requests', { params: { status: status || undefined, page, search: q || undefined } })).data,
    placeholderData: keepPreviousData,
  })
  const counts = query.data?.meta.counts
  const rows = query.data?.data ?? []

  const setParam = (k: string, v: string | number | null) => {
    const p = new URLSearchParams(params)
    if (v === null || v === '' || (k === 'page' && v === 1)) p.delete(k); else p.set(k, String(v))
    if (k !== 'page') p.delete('page')
    setParams(p, { replace: true })
  }

  /** "Jadikan Sewa": pakai pelanggan yang cocok (no. HP), atau buat pelanggan dulu. */
  const convert = async (req: BookingRequest, customer?: Customer) => {
    try {
      const c = customer ?? (req.matched_customer ? (await api.get<{ data: Customer }>(`/customers/${req.matched_customer.id}`)).data.data : null)
      if (!c) { setCustomerFor(req); return }
      const car = req.car ? (await api.get<{ data: Car }>(`/cars/${req.car.id}`)).data.data : null
      setPrefill({
        customer: c, car, service_type: req.service_type,
        start_at: toWibInput(new Date(req.start_at)), planned_end_at: toWibInput(new Date(req.end_at)),
        notes: [`Dari permintaan booking #${req.id}`, req.notes].filter(Boolean).join('. '),
        booking_request_id: req.id,
      })
    } catch (e) {
      toast(errorMessage(e), 'error')
    }
  }

  return (
    <>
      <PageHeader title="Permintaan Booking" subtitle="Permintaan sewa dari landing page. Hubungi calon penyewa, lalu jadikan sewa atau tolak." />

      <div className="mb-5 flex flex-col gap-3">
        <SearchInput label="Cari permintaan" placeholder="Cari nama atau nomor HP…" value={search} onChange={(v) => { setSearch(v); setParam('page', 1) }} />
        <FilterChips<'' | Status> label="Filter status" value={status} onChange={(v) => setParam('status', v)}
          options={[{ value: '', label: 'Semua', count: counts?.semua }, { value: 'baru', label: 'Baru', count: counts?.baru }, { value: 'dihubungi', label: 'Dihubungi', count: counts?.dihubungi },
            { value: 'dikonfirmasi', label: 'Dikonfirmasi', count: counts?.dikonfirmasi }, { value: 'ditolak', label: 'Ditolak', count: counts?.ditolak }]} />
      </div>

      <div className="card overflow-hidden">
        {query.isLoading ? <div className="space-y-3 p-5">{Array.from({ length: 3 }, (_, i) => <Skeleton key={i} className="h-16" />)}</div>
          : query.isError ? <ErrorState error={query.error} onRetry={() => query.refetch()} />
            : rows.length === 0 ? <EmptyState icon={<Inbox className="size-7" />} title={status || q ? 'Tidak ada yang cocok' : 'Belum ada permintaan'} description="Permintaan dari form landing page akan muncul di sini." />
              : (
                <ul className={cn('divide-y divide-line', query.isPlaceholderData && 'opacity-60')}>
                  {rows.map((r) => (
                    <li key={r.id} className="grid gap-3 px-5 py-4 lg:grid-cols-[1.2fr_1fr_auto] lg:items-center">
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <p className="font-bold text-ink">{r.name}</p>
                          <Badge tone={tone[r.status]}>{r.status_label}</Badge>
                          {r.matched_customer
                            ? <RiskBadge status={r.matched_customer.risk_status} label={`Pelanggan · ${r.matched_customer.risk_status_label}`} />
                            : <Badge tone="gray" dot={false}>Pelanggan baru</Badge>}
                        </div>
                        <p className="mt-0.5 text-xs text-ink-muted">{fmtPhone(r.phone)} · masuk {dateTimeWib(r.created_at)}</p>
                        {r.notes && <p className="mt-1 text-xs text-ink-soft">“{r.notes}”</p>}
                        {r.status === 'ditolak' && r.rejection_reason && <p className="mt-1 text-xs text-ink-muted">Ditolak: {r.rejection_reason}</p>}
                      </div>
                      <div className="text-xs text-ink-soft">
                        <p className="font-semibold text-ink">{r.car ? `${r.car.name} · ${r.car.plate_number}` : 'Mobil belum dipilih'}</p>
                        <p>{dateTimeWib(r.start_at)} → {dateTimeWib(r.end_at)}</p>
                        <p className="text-ink-muted">{r.service_type_label}{r.handled_by && ` · ditangani ${r.handled_by}`}</p>
                      </div>
                      <div className="flex flex-wrap gap-2 lg:justify-end">
                        {r.rental ? (
                          <Button size="sm" variant="secondary" onClick={() => setOpenRental(r.rental!.id)}>{r.rental.code}</Button>
                        ) : (
                          <>
                            <a href={waLink(r.phone)} target="_blank" rel="noreferrer" onClick={() => r.can.update && r.status === 'baru' && update.mutate({ id: r.id, status: 'dihubungi' })}
                              className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line-strong px-3 text-xs font-semibold text-ink hover:bg-canvas" aria-label={`Hubungi ${r.name} via WhatsApp`}>
                              <MessageCircle className="size-3.5" /> Hubungi
                            </a>
                            {r.can.update && r.status === 'baru' && <Button size="sm" variant="ghost" onClick={() => update.mutate({ id: r.id, status: 'dihubungi' }, { onSuccess: (x) => toast(x.message) })}><Phone className="size-3.5" /> Tandai dihubungi</Button>}
                            {r.can.update && <Button size="sm" variant="ghost" className="text-red-600" onClick={() => setRejecting(r)}><X className="size-3.5" /> Tolak</Button>}
                            {r.can.convert && <Button size="sm" onClick={() => convert(r)}>Jadikan Sewa</Button>}
                          </>
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
        {query.data && rows.length > 0 && <div className="border-t border-line px-4 pb-4"><Pagination meta={query.data.meta} onPage={(p) => setParam('page', p)} /></div>}
      </div>

      <RejectModal req={rejecting} onClose={() => setRejecting(null)} />
      <CustomerFormModal
        open={!!customerFor}
        prefill={customerFor ? { name: customerFor.name, phone: customerFor.phone } : null}
        onClose={() => setCustomerFor(null)}
        onSaved={(c) => { const req = customerFor; setCustomerFor(null); if (req) void convert(req, c) }}
      />
      <NewRentalModal
        open={!!prefill}
        prefill={prefill}
        onClose={() => setPrefill(null)}
        onCreated={(r) => { qc.invalidateQueries({ queryKey: ['booking-requests'] }); setOpenRental(r.id) }}
      />
      <RentalDetailModal rentalId={openRental} onClose={() => setOpenRental(null)} />
    </>
  )
}
