import { Banknote, Car as CarIcon, Handshake, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { useCan } from '@/auth/useAuth'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, CarStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Skeleton } from '@/components/ui/Feedback'
import { FilterChips } from '@/components/ui/FilterChips'
import { Modal } from '@/components/ui/Modal'
import { Pagination } from '@/components/ui/Pagination'
import { SearchInput } from '@/components/ui/SearchInput'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { errorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import { dateOnly, phone as fmtPhone, rupiah } from '@/lib/format'
import { P } from '@/lib/permissions'
import { useDebounce } from '@/lib/useDebounce'
import { useDeleteOwner, useOwner, useOwners, useSettlements, type CarOwner, type Settlement } from './api'
import { NewSettlementModal } from './NewSettlementModal'
import { OwnerFormModal } from './OwnerFormModal'
import { SettlementDetailModal } from './SettlementDetailModal'

type Tab = 'pemilik' | 'setoran'

function SettlementRows({ rows, onOpen, showOwner = true }: { rows: Settlement[]; onOpen: (id: number) => void; showOwner?: boolean }) {
  return (
    <ul className="divide-y divide-line">
      {rows.map((s) => (
        <li key={s.id}>
          <button type="button" onClick={() => onOpen(s.id)} className="grid w-full gap-1 px-5 py-3 text-left hover:bg-canvas/60 sm:grid-cols-[1fr_auto] sm:items-center sm:gap-4">
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-bold text-ink">{s.code}</span>
                <Badge tone={s.status === 'dibayar' ? 'green' : 'yellow'}>{s.status_label}</Badge>
              </div>
              <p className="mt-0.5 text-xs text-ink-muted">
                {showOwner && `${s.owner.name} · `}{dateOnly(s.period_from)} – {dateOnly(s.period_to)} · {s.share_basis === 'kotor' ? 'kotor' : 'bersih'} {s.share_percent.toLocaleString('id-ID')}%
                {s.paid_at && ` · dibayar ${dateOnly(s.paid_at.slice(0, 10))}`}
              </p>
            </div>
            <div className="sm:text-right">
              <p className="text-base font-bold tabular-nums">{rupiah(s.owner_amount)}</p>
              <p className="text-2xs text-ink-muted">dari pendapatan {rupiah(s.income)}</p>
            </div>
          </button>
        </li>
      ))}
    </ul>
  )
}

function OwnerDetailModal({ id, onClose, onEdit, onSettle, onOpenSettlement }: {
  id: number | null; onClose: () => void; onEdit: (o: CarOwner) => void; onSettle: (o: CarOwner) => void; onOpenSettlement: (id: number) => void
}) {
  const can = useCan()
  const q = useOwner(id)
  const hist = useSettlements({ owner_id: id })
  const o = q.data
  return (
    <Modal open={!!id} onClose={onClose} size="lg" title={o?.name ?? 'Pemilik'} description={o ? `${fmtPhone(o.phone)} · ${o.share_label}` : undefined}
      footer={o && (
        <>
          {o.can.update && <Button variant="secondary" onClick={() => onEdit(o)}><Pencil className="size-4" /> Ubah</Button>}
          {can(P.settlementCreate) && <Button disabled={!o.cars?.length} onClick={() => onSettle(o)}><Banknote className="size-4" /> Buat setoran</Button>}
        </>
      )}>
      {q.isLoading ? <Skeleton className="h-60" /> : q.isError ? <ErrorState error={q.error} onRetry={() => q.refetch()} /> : o && (
        <div className="space-y-5">
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="rounded-xl bg-canvas p-3 text-sm">
              <p className="text-2xs font-bold tracking-[0.1em] text-ink-muted uppercase">Rekening</p>
              {o.bank_name ? <p className="mt-1 font-semibold">{o.bank_name} · {o.bank_account_number}</p> : <p className="mt-1 text-ink-muted">Belum diisi</p>}
              {o.bank_account_name && <p className="text-xs text-ink-soft">a.n. {o.bank_account_name}</p>}
            </div>
            <div className="rounded-xl bg-canvas p-3 text-sm">
              <p className="text-2xs font-bold tracking-[0.1em] text-ink-muted uppercase">Bagi hasil</p>
              <p className="mt-1 font-semibold">{o.share_label}</p>
              <p className="text-xs text-ink-soft">{o.share_basis === 'kotor' ? 'Biaya servis & pengeluaran ditanggung rental.' : 'Biaya servis & pengeluaran mobil ikut dikurangkan.'}</p>
            </div>
          </div>
          <section>
            <h3 className="mb-2 text-sm font-bold">Mobil titipan ({o.cars?.length ?? 0})</h3>
            {o.cars?.length ? (
              <ul className="divide-y divide-line rounded-xl border border-line">
                {o.cars.map((c) => (
                  <li key={c.id} className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                    <span><span className="font-semibold">{c.name}</span> <span className="text-ink-muted">· {c.plate_number}</span></span>
                    <CarStatusBadge status={c.status} label={c.status_label} />
                  </li>
                ))}
              </ul>
            ) : <p className="rounded-xl border border-dashed border-line p-4 text-sm text-ink-muted">Belum ada mobil. Pilih pemilik ini di form Armada (bagian Operasional → Kepemilikan).</p>}
          </section>
          <section>
            <h3 className="mb-2 text-sm font-bold">Riwayat setoran</h3>
            <div className="overflow-hidden rounded-xl border border-line">
              {hist.isLoading ? <Skeleton className="h-24" /> : hist.data?.data.length ? <SettlementRows rows={hist.data.data} onOpen={onOpenSettlement} showOwner={false} /> : <p className="p-4 text-sm text-ink-muted">Belum ada setoran.</p>}
            </div>
          </section>
          {o.notes && <p className="text-sm text-ink-soft"><span className="font-semibold text-ink">Catatan:</span> {o.notes}</p>}
        </div>
      )}
    </Modal>
  )
}

export function OwnersPage() {
  const toast = useToast()
  const can = useCan()
  const [params, setParams] = useSearchParams()
  const tab = (params.get('tab') === 'setoran' ? 'setoran' : 'pemilik') as Tab
  const status = params.get('status') ?? ''
  const page = Number(params.get('page') ?? 1)
  const [search, setSearch] = useState('')
  const q = useDebounce(search.trim())
  const owners = useOwners(q)
  const settlements = useSettlements({ status, page })
  const del = useDeleteOwner()

  const [form, setForm] = useState<{ open: boolean; owner: CarOwner | null }>({ open: false, owner: null })
  const [detail, setDetail] = useState<number | null>(null)
  const [settleFor, setSettleFor] = useState<{ open: boolean; ownerId: number | null }>({ open: false, ownerId: null })
  const [openSettlement, setOpenSettlement] = useState<number | null>(null)
  const [deleting, setDeleting] = useState<CarOwner | null>(null)

  const setParam = (next: Record<string, string | number | null>) => {
    const p = new URLSearchParams(params)
    for (const [k, v] of Object.entries(next)) { if (v === null || v === '' || (k === 'page' && v === 1)) p.delete(k); else p.set(k, String(v)) }
    setParams(p, { replace: true })
  }
  const unpaid = settlements.data?.meta.unpaid
  const ownerRows = owners.data?.data ?? []

  return (
    <>
      <PageHeader
        title="Mobil Titipan"
        subtitle="Pemilik mobil titipan, ketentuan bagi hasil, dan setoran per periode."
        action={
          <div className="flex flex-wrap gap-2">
            {can(P.settlementCreate) && <Button variant="secondary" onClick={() => setSettleFor({ open: true, ownerId: null })}><Banknote className="size-4" /> Buat setoran</Button>}
            {can(P.carOwnerManage) && <Button onClick={() => setForm({ open: true, owner: null })}><Plus className="size-4" /> Tambah pemilik</Button>}
          </div>
        }
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <FilterChips<Tab> label="Tampilan" value={tab} onChange={(v) => setParam({ tab: v === 'pemilik' ? null : v, page: 1, status: null })}
          options={[{ value: 'pemilik', label: 'Pemilik', count: owners.data?.meta.total }, { value: 'setoran', label: 'Setoran', count: unpaid?.count ? unpaid.count : undefined }]} />
        {unpaid && unpaid.count > 0 && (
          <button type="button" onClick={() => setParam({ tab: 'setoran', status: 'belum_dibayar', page: 1 })} className="rounded-xl bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 hover:bg-amber-100">
            {unpaid.count} setoran belum dibayar · {rupiah(unpaid.total)}
          </button>
        )}
      </div>

      {tab === 'pemilik' ? (
        <>
          <div className="mb-3"><SearchInput label="Cari pemilik" placeholder="Cari nama atau nomor HP…" value={search} onChange={setSearch} /></div>
          <div className="card overflow-hidden">
            {owners.isLoading ? <div className="space-y-3 p-5">{Array.from({ length: 3 }, (_, i) => <Skeleton key={i} className="h-14" />)}</div>
              : owners.isError ? <ErrorState error={owners.error} onRetry={() => owners.refetch()} />
                : ownerRows.length === 0 ? (
                  <EmptyState icon={<Handshake className="size-7" />} title={q ? 'Tidak ada yang cocok' : 'Belum ada pemilik titipan'}
                    description="Tambahkan pemilik, lalu pilih pemilik tersebut di form Armada untuk mobil yang dititipkan."
                    action={!q && can(P.carOwnerManage) ? <Button onClick={() => setForm({ open: true, owner: null })}><Plus className="size-4" /> Tambah pemilik</Button> : undefined} />
                ) : (
                  <ul className={cn('divide-y divide-line', owners.isPlaceholderData && 'opacity-60')}>
                    {ownerRows.map((o) => (
                      <li key={o.id} className="grid gap-3 px-5 py-4 lg:grid-cols-[1.3fr_1fr_auto] lg:items-center">
                        <button type="button" className="min-w-0 text-left" onClick={() => setDetail(o.id)}>
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="font-bold text-ink hover:underline">{o.name}</span>
                            {!o.is_active && <Badge tone="gray">Nonaktif</Badge>}
                          </div>
                          <p className="mt-0.5 text-xs text-ink-muted">{fmtPhone(o.phone)}{o.bank_name && ` · ${o.bank_name} ${o.bank_account_number}`}</p>
                        </button>
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-soft">
                          <span className="inline-flex items-center gap-1"><CarIcon className="size-3.5" /> {o.cars_count} mobil</span>
                          <Badge tone="teal" dot={false}>{o.share_label}</Badge>
                          {o.unpaid_total ? <span className="font-semibold text-amber-700">Belum dibayar {rupiah(o.unpaid_total)}</span>
                            : o.last_period_to ? <span>Setoran s/d {dateOnly(o.last_period_to)}</span> : <span className="text-ink-muted">Belum ada setoran</span>}
                        </div>
                        <div className="flex flex-wrap gap-1.5 lg:justify-end">
                          {can(P.settlementCreate) && !!o.cars_count && <Button size="sm" variant="secondary" onClick={() => setSettleFor({ open: true, ownerId: o.id })}>Buat setoran</Button>}
                          {o.can.update && <button type="button" className="rounded-lg p-2 text-ink-muted hover:bg-canvas hover:text-ink" aria-label={`Ubah ${o.name}`} title="Ubah" onClick={() => setForm({ open: true, owner: o })}><Pencil className="size-4" /></button>}
                          {o.can.delete && <button type="button" className="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label={`Hapus ${o.name}`} title="Hapus" onClick={() => setDeleting(o)}><Trash2 className="size-4" /></button>}
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
          </div>
        </>
      ) : (
        <>
          <div className="mb-3">
            <FilterChips<string> label="Status setoran" value={status} onChange={(v) => setParam({ status: v, page: 1 })}
              options={[{ value: '', label: 'Semua' }, { value: 'belum_dibayar', label: 'Belum dibayar' }, { value: 'dibayar', label: 'Sudah dibayar' }]} />
          </div>
          <div className="card overflow-hidden">
            {settlements.isLoading ? <div className="space-y-3 p-5">{Array.from({ length: 3 }, (_, i) => <Skeleton key={i} className="h-14" />)}</div>
              : settlements.isError ? <ErrorState error={settlements.error} onRetry={() => settlements.refetch()} />
                : !settlements.data?.data.length ? (
                  <EmptyState icon={<Banknote className="size-7" />} title={status ? 'Tidak ada setoran dengan status ini' : 'Belum ada setoran'}
                    description="Buat setoran per periode (misalnya bulanan) untuk menghitung bagian pemilik."
                    action={!status && can(P.settlementCreate) ? <Button onClick={() => setSettleFor({ open: true, ownerId: null })}><Banknote className="size-4" /> Buat setoran</Button> : undefined} />
                ) : (
                  <div className={cn(settlements.isPlaceholderData && 'opacity-60')}><SettlementRows rows={settlements.data.data} onOpen={setOpenSettlement} /></div>
                )}
            {settlements.data && settlements.data.data.length > 0 && <div className="border-t border-line px-4 pb-4"><Pagination meta={settlements.data.meta} onPage={(p) => setParam({ page: p })} /></div>}
          </div>
        </>
      )}

      <OwnerFormModal open={form.open} owner={form.owner} onClose={() => setForm({ open: false, owner: null })} />
      <OwnerDetailModal id={detail} onClose={() => setDetail(null)}
        onEdit={(o) => setForm({ open: true, owner: o })}
        onSettle={(o) => setSettleFor({ open: true, ownerId: o.id })}
        onOpenSettlement={setOpenSettlement} />
      <NewSettlementModal open={settleFor.open} ownerId={settleFor.ownerId} onClose={() => setSettleFor({ open: false, ownerId: null })}
        onCreated={(s) => { setDetail(null); setOpenSettlement(s.id) }} />
      <SettlementDetailModal id={openSettlement} onClose={() => setOpenSettlement(null)} />
      <ConfirmDialog open={!!deleting} title="Hapus pemilik?" confirmLabel="Hapus" loading={del.isPending}
        description={deleting && <>Pemilik <b>{deleting.name}</b> akan dihapus. Hanya bisa bila tidak punya mobil dan riwayat setoran; selain itu, nonaktifkan saja.</>}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && del.mutate(deleting.id, {
          onSuccess: (r) => { toast(r.message); setDeleting(null) },
          onError: (e) => { toast(errorMessage(e), 'error'); setDeleting(null) },
        })} />
    </>
  )
}
