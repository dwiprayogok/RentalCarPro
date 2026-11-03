import { Plus, Users } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { useCan } from '@/auth/useAuth'
import { PageHeader } from '@/components/layout/PageHeader'
import { Avatar } from '@/components/ui/Avatar'
import { RiskBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Feedback'
import { FilterChips } from '@/components/ui/FilterChips'
import { Pagination } from '@/components/ui/Pagination'
import { SearchInput } from '@/components/ui/SearchInput'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { cn } from '@/lib/cn'
import { phone } from '@/lib/format'
import { P } from '@/lib/permissions'
import type { Customer, RiskStatus } from '@/lib/types'
import { useDebounce } from '@/lib/useDebounce'
import { useCustomers } from './api'
import { CustomerFormModal } from './CustomerFormModal'
import { CustomerHistoryModal } from './CustomerHistoryModal'

type RiskFilter = '' | RiskStatus

function Actions({ c, onHistory, onEdit }: { c: Customer; onHistory: () => void; onEdit: () => void }) {
  return (
    <div className="flex gap-2">
      <Button size="sm" variant="secondary" onClick={onHistory} aria-label={`Riwayat ${c.name}`}>Riwayat</Button>
      {c.can.update && <Button size="sm" variant="secondary" onClick={onEdit} aria-label={`Edit ${c.name}`}>Edit</Button>}
    </div>
  )
}

export function CustomersPage() {
  const can = useCan()
  const [params, setParams] = useSearchParams()
  const risk = (params.get('risk') ?? '') as RiskFilter
  const page = Number(params.get('page') ?? 1)
  const [search, setSearch] = useState(params.get('q') ?? '')
  const debounced = useDebounce(search.trim())
  const [form, setForm] = useState<{ open: boolean; customer?: Customer | null }>({ open: false })
  const [historyId, setHistoryId] = useState<number | null>(null)

  const update = (next: Record<string, string | number | null>) => {
    const p = new URLSearchParams(params)
    for (const [k, v] of Object.entries(next)) {
      if (v === null || v === '' || (k === 'page' && v === 1)) p.delete(k)
      else p.set(k, String(v))
    }
    setParams(p, { replace: true })
  }

  const query = useCustomers({ search: debounced, risk_status: risk, page })
  const counts = query.data?.meta.counts
  const filtered = !!debounced || !!risk
  const rows = query.data?.data ?? []

  return (
    <>
      <PageHeader
        title="Pelanggan"
        subtitle="Kenali pelanggan dan pantau riwayat perjalanannya."
        action={can(P.customerCreate) && <Button onClick={() => setForm({ open: true, customer: null })}><Plus className="size-4" /> Tambah Pelanggan</Button>}
      />

      <div className="mb-5 flex flex-col gap-3">
        <SearchInput label="Cari pelanggan" placeholder="Cari nama, nomor HP, atau KTP…" value={search} onChange={(v) => { setSearch(v); update({ q: v || null, page: 1 }) }} />
        <FilterChips<RiskFilter>
          label="Filter status risiko"
          value={risk}
          onChange={(v) => update({ risk: v, page: 1 })}
          options={[
            { value: '', label: 'Semua pelanggan', count: counts?.semua },
            { value: 'lancar', label: 'Lancar', count: counts?.lancar },
            { value: 'perhatian', label: 'Perhatian', count: counts?.perhatian },
            { value: 'blacklist', label: 'Blacklist', count: counts?.blacklist },
          ]}
        />
      </div>

      <div className="card overflow-hidden">
        {query.isLoading ? (
          <div className="divide-y divide-line">
            {Array.from({ length: 5 }, (_, i) => (
              <div key={i} className="flex items-center gap-4 px-5 py-4"><Skeleton className="size-8 rounded-full" /><Skeleton className="h-4 w-48" /><Skeleton className="ml-auto h-4 w-24" /></div>
            ))}
          </div>
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          filtered ? (
            <EmptyState
              icon={<Users className="size-7" />}
              title="Tidak ada pelanggan yang cocok"
              description="Coba kata kunci lain. Pencarian KTP memakai 16 digit lengkap atau 4 digit terakhir."
              action={<Button variant="secondary" onClick={() => { setSearch(''); update({ q: null, risk: null, page: 1 }) }}>Reset filter</Button>}
            />
          ) : (
            <EmptyState
              icon={<Users className="size-7" />}
              title="Belum ada pelanggan"
              description="Tambahkan pelanggan pertama beserta identitas KTP-nya."
              action={can(P.customerCreate) && <Button onClick={() => setForm({ open: true, customer: null })}><Plus className="size-4" /> Tambah Pelanggan</Button>}
            />
          )
        ) : (
          <div className={cn(query.isPlaceholderData && 'opacity-60')}>
            {/* Tabel (tablet & desktop) */}
            <table className="hidden w-full text-sm md:table">
              <thead>
                <tr className="border-b border-line text-left text-2xs font-bold tracking-[0.12em] text-ink-muted uppercase">
                  <th className="px-5 py-3">Pelanggan</th>
                  <th className="px-3 py-3">Nomor HP</th>
                  <th className="hidden px-3 py-3 lg:table-cell">Nomor KTP</th>
                  <th className="px-3 py-3">Riwayat</th>
                  <th className="px-3 py-3">Status Risiko</th>
                  <th className="px-5 py-3">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {rows.map((c) => (
                  <tr key={c.id} className="hover:bg-canvas/60">
                    <td className="px-5 py-3.5">
                      <div className="flex items-center gap-3">
                        <Avatar initials={c.initials} />
                        <span className="font-semibold text-ink">{c.name}</span>
                      </div>
                    </td>
                    <td className="px-3 py-3.5 text-ink-soft tabular-nums">{phone(c.phone)}</td>
                    <td className="hidden px-3 py-3.5 text-ink-soft tabular-nums lg:table-cell">{c.nik_masked}</td>
                    <td className="px-3 py-3.5 text-ink-soft">{c.rentals_count ?? 0} transaksi</td>
                    <td className="px-3 py-3.5"><RiskBadge status={c.risk_status} label={c.risk_status_label} /></td>
                    <td className="px-5 py-3.5"><Actions c={c} onHistory={() => setHistoryId(c.id)} onEdit={() => setForm({ open: true, customer: c })} /></td>
                  </tr>
                ))}
              </tbody>
            </table>

            {/* Daftar kartu (HP) */}
            <ul className="divide-y divide-line md:hidden">
              {rows.map((c) => (
                <li key={c.id} className="space-y-3 px-4 py-4">
                  <div className="flex items-start gap-3">
                    <Avatar initials={c.initials} />
                    <div className="min-w-0 flex-1">
                      <p className="font-semibold text-ink">{c.name}</p>
                      <p className="text-xs text-ink-muted tabular-nums">{phone(c.phone)}</p>
                      <p className="text-xs text-ink-muted tabular-nums">KTP {c.nik_masked} · {c.rentals_count ?? 0} transaksi</p>
                    </div>
                    <RiskBadge status={c.risk_status} label={c.risk_status_label} />
                  </div>
                  <Actions c={c} onHistory={() => setHistoryId(c.id)} onEdit={() => setForm({ open: true, customer: c })} />
                </li>
              ))}
            </ul>
          </div>
        )}

        {query.data && rows.length > 0 && (
          <div className="border-t border-line px-4 pb-4"><Pagination meta={query.data.meta} onPage={(p) => update({ page: p })} /></div>
        )}
      </div>

      <CustomerHistoryModal customerId={historyId} onClose={() => setHistoryId(null)} onEdit={(c) => { setHistoryId(null); setForm({ open: true, customer: c }) }} />
      <CustomerFormModal open={form.open} customer={form.customer} onClose={() => setForm({ open: false })} />
    </>
  )
}
