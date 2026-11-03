import { AlertTriangle, CheckCircle2, FileText, History, MessageCircle } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Avatar } from '@/components/ui/Avatar'
import { Badge, RiskBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Alert, Skeleton } from '@/components/ui/Feedback'
import { Modal } from '@/components/ui/Modal'
import { Pagination } from '@/components/ui/Pagination'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { dateOnly, dateTimeWib, phone, rupiah, waLink } from '@/lib/format'
import type { Customer } from '@/lib/types'
import { useCustomer, useCustomerRentals } from './api'

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex justify-between gap-4 py-1.5 text-sm">
      <dt className="text-ink-muted">{label}</dt>
      <dd className="text-right font-medium text-ink">{children}</dd>
    </div>
  )
}

function DocThumb({ label, doc }: { label: string; doc: Customer['documents']['ktp'] }) {
  if (!doc.uploaded) return <div className="flex h-20 flex-1 items-center justify-center rounded-xl border border-dashed border-line-strong text-xs text-ink-muted">{label}: belum ada</div>
  if (!doc.url) return <div className="flex h-20 flex-1 items-center justify-center rounded-xl bg-canvas text-xs text-ink-muted">{label}: tersimpan (akses dibatasi)</div>
  return (
    <a href={doc.url} target="_blank" rel="noreferrer" className="group relative block h-20 flex-1 overflow-hidden rounded-xl bg-canvas ring-1 ring-line">
      <img src={doc.url} alt={label} className="size-full object-cover" onError={(e) => (e.currentTarget.style.display = 'none')} />
      <span className="absolute inset-0 flex items-center justify-center gap-1 bg-sidebar/0 text-xs font-semibold text-transparent group-hover:bg-sidebar/50 group-hover:text-white">
        <FileText className="size-4" /> Buka {label}
      </span>
    </a>
  )
}

export function CustomerHistoryModal({ customerId, onClose, onEdit }: { customerId: number | null; onClose: () => void; onEdit: (c: Customer) => void }) {
  const [page, setPage] = useState(1)
  const detail = useCustomer(customerId)
  const rentals = useCustomerRentals(customerId, page)
  const c = detail.data

  return (
    <Modal
      open={customerId !== null}
      onClose={() => { setPage(1); onClose() }}
      size="lg"
      title="Riwayat pelanggan"
      description="Profil, dokumen, dan riwayat transaksi."
      footer={c?.can.update ? <Button variant="secondary" onClick={() => onEdit(c)}>Edit data</Button> : undefined}
    >
      {detail.isLoading && <div className="space-y-3"><Skeleton className="h-12 w-2/3" /><Skeleton className="h-32 w-full" /></div>}
      {detail.isError && <ErrorState error={detail.error} onRetry={() => detail.refetch()} />}
      {c && (
        <div className="space-y-6">
          <div className="flex flex-wrap items-center gap-3">
            <Avatar initials={c.initials} className="size-12 text-sm" />
            <div className="min-w-0 flex-1">
              <p className="text-lg font-bold text-ink">{c.name}</p>
              <div className="mt-0.5 flex flex-wrap items-center gap-2">
                <RiskBadge status={c.risk_status} label={c.risk_status_label} />
                {c.self_drive_ready
                  ? <Badge tone="teal"><CheckCircle2 className="size-3" /> Siap lepas kunci</Badge>
                  : <Badge tone="gray" dot={false}>Belum lengkap untuk lepas kunci</Badge>}
              </div>
            </div>
            <a href={waLink(c.phone)} target="_blank" rel="noreferrer" className="inline-flex h-8 items-center gap-1.5 rounded-lg bg-brand-500 px-3 text-xs font-semibold text-white hover:bg-brand-600">
              <MessageCircle className="size-4" /> WhatsApp
            </a>
          </div>

          {c.risk_status !== 'lancar' && c.risk_note && (
            <Alert tone={c.risk_status === 'blacklist' ? 'red' : 'yellow'}>
              <span className="flex gap-2"><AlertTriangle className="mt-0.5 size-4 shrink-0" /><span><strong>Catatan risiko:</strong> {c.risk_note}</span></span>
            </Alert>
          )}

          <div className="grid gap-6 md:grid-cols-2">
            <dl className="divide-y divide-line">
              <Row label="Nomor HP">{phone(c.phone)}</Row>
              <Row label="Nomor KTP"><span className="tabular-nums">{c.nik_masked}</span></Row>
              <Row label="SIM">{c.sim_number ? `${c.sim_type ?? ''} · ${c.sim_number}` : '-'}</Row>
              <Row label="SIM berlaku s.d.">{dateOnly(c.sim_expires_at)}</Row>
              <Row label="Kontak darurat">
                {c.emergency_contact_name ? `${c.emergency_contact_name}${c.emergency_contact_relation ? ` (${c.emergency_contact_relation})` : ''} · ${phone(c.emergency_contact_phone)}` : '-'}
              </Row>
              <Row label="Alamat"><span className="whitespace-pre-line">{c.address ?? '-'}</span></Row>
            </dl>
            <div>
              <p className="mb-2 text-2xs font-semibold tracking-wide text-ink-muted uppercase">Dokumen</p>
              <div className="flex gap-3">
                <DocThumb label="KTP" doc={c.documents.ktp} />
                <DocThumb label="SIM" doc={c.documents.sim} />
              </div>
              {!c.self_drive_ready && c.self_drive_missing.length > 0 && (
                <p className="mt-3 text-xs text-ink-muted">Kurang untuk lepas kunci: {c.self_drive_missing.join(', ')}.</p>
              )}
            </div>
          </div>

          <section>
            <h3 className="mb-2 text-xs font-bold tracking-wide text-ink uppercase">Riwayat transaksi</h3>
            {rentals.isLoading ? (
              <Skeleton className="h-24 w-full" />
            ) : rentals.isError ? (
              <ErrorState error={rentals.error} onRetry={() => rentals.refetch()} />
            ) : rentals.data && rentals.data.data.length === 0 ? (
              <div className="rounded-xl border border-line">
                <EmptyState icon={<History className="size-7" />} title="Belum ada transaksi" description="Riwayat sewa pelanggan ini akan muncul di sini." />
              </div>
            ) : rentals.data ? (
              <>
                <ul className="divide-y divide-line rounded-xl border border-line">
                  {rentals.data.data.map((r) => (
                    <li key={r.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                      <div className="min-w-0">
                        <p className="text-sm font-semibold text-ink">{r.code} · {r.car?.name} <span className="text-ink-muted">({r.car?.plate_number})</span></p>
                        <p className="text-xs text-ink-muted">{dateTimeWib(r.start_at)} → {dateTimeWib(r.returned_at ?? r.planned_end_at)} · {r.service_type_label}</p>
                      </div>
                      <div className="flex items-center gap-3">
                        <span className="text-sm font-semibold tabular-nums">{rupiah(r.total_amount)}</span>
                        <Badge tone={r.is_overdue ? 'red' : r.status === 'selesai' ? 'green' : r.status === 'batal' ? 'gray' : 'blue'}>{r.status_label}</Badge>
                      </div>
                    </li>
                  ))}
                </ul>
                {rentals.data.meta.last_page > 1 && <Pagination meta={rentals.data.meta} onPage={setPage} />}
              </>
            ) : null}
          </section>
        </div>
      )}
    </Modal>
  )
}
