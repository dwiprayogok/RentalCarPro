import { FileText, Pencil, Plus, Receipt, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { PageHeader } from '@/components/layout/PageHeader'
import { useCan } from '@/auth/useAuth'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Skeleton } from '@/components/ui/Feedback'
import { Field, Input, Select } from '@/components/ui/Field'
import { Pagination } from '@/components/ui/Pagination'
import { SearchInput } from '@/components/ui/SearchInput'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { errorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import { todayWib } from '@/lib/datetime'
import { dateOnly, number, rupiah } from '@/lib/format'
import { P } from '@/lib/permissions'
import { useDebounce } from '@/lib/useDebounce'
import { useCarList, useDeleteExpense, useExpenses, type Expense } from './api'
import { ExpenseFormModal } from './ExpenseFormModal'

const monthStart = () => `${todayWib().slice(0, 8)}01`

export function ExpensesPage() {
  const toast = useToast()
  const can = useCan()
  const [params, setParams] = useSearchParams()
  const [search, setSearch] = useState(params.get('q') ?? '')
  const q = useDebounce(search.trim())
  const f = {
    from: params.get('from') ?? monthStart(),
    to: params.get('to') ?? todayWib(),
    car_id: params.get('mobil') ?? '',
    category: params.get('kategori') ?? '',
    search: q,
    page: Number(params.get('page') ?? 1),
  }
  const query = useExpenses(f)
  const cars = useCarList()
  const del = useDeleteExpense()
  const [editing, setEditing] = useState<Expense | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<Expense | null>(null)

  const setParam = (k: string, v: string | number | null) => {
    const p = new URLSearchParams(params)
    if (v === null || v === '' || (k === 'page' && v === 1)) p.delete(k); else p.set(k, String(v))
    if (k !== 'page') p.delete('page')
    setParams(p, { replace: true })
  }

  const meta = query.data?.meta
  const rows = query.data?.data ?? []
  const categories = meta?.categories ?? []
  const filtered = !!(f.car_id || f.category || q)

  return (
    <>
      <PageHeader
        title="Pengeluaran"
        subtitle="Catat BBM, cuci, parkir, gaji, sewa tempat, dan biaya operasional lain. Masuk ke laporan Laba per mobil."
        action={can(P.expenseCreate) && <Button onClick={() => setCreating(true)} disabled={!categories.length}><Plus className="size-4" /> Catat Pengeluaran</Button>}
      />

      <div className="mb-4 grid gap-3 rounded-2xl border border-line bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <Field label="Dari">{(a) => <Input {...a} type="date" value={f.from} max={f.to} onChange={(e) => setParam('from', e.target.value)} />}</Field>
        <Field label="Sampai">{(a) => <Input {...a} type="date" value={f.to} min={f.from} onChange={(e) => setParam('to', e.target.value)} />}</Field>
        <Field label="Mobil">
          {(a) => (
            <Select {...a} value={f.car_id} onChange={(e) => setParam('mobil', e.target.value)}>
              <option value="">Semua</option>
              <option value="umum">Umum (tanpa mobil)</option>
              {(cars.data ?? []).map((c) => <option key={c.id} value={c.id}>{c.name} · {c.plate_number}</option>)}
            </Select>
          )}
        </Field>
        <Field label="Kategori">
          {(a) => (
            <Select {...a} value={f.category} onChange={(e) => setParam('kategori', e.target.value)}>
              <option value="">Semua kategori</option>
              {categories.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
            </Select>
          )}
        </Field>
      </div>

      {meta && (
        <div className={cn('mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4', query.isPlaceholderData && 'opacity-60')}>
          <div className="card px-4 py-3">
            <p className="text-xs font-semibold text-ink-muted">Total pengeluaran</p>
            <p className="mt-0.5 text-xl font-extrabold tabular-nums">{rupiah(meta.summary.total)}</p>
            <p className="text-2xs text-ink-muted">{number(meta.summary.count)} catatan · {dateOnly(f.from)} – {dateOnly(f.to)}</p>
          </div>
          {meta.summary.by_category.slice(0, 3).map((c) => (
            <button key={c.category} type="button" onClick={() => setParam('kategori', f.category === c.category ? '' : c.category)}
              className={cn('card px-4 py-3 text-left transition hover:ring-brand-300', f.category === c.category && 'ring-2 ring-brand-500')}>
              <p className="text-xs font-semibold text-ink-muted">{c.label}</p>
              <p className="mt-0.5 text-xl font-extrabold tabular-nums">{rupiah(c.total)}</p>
              <p className="text-2xs text-ink-muted">{meta.summary.total > 0 ? `${Math.round((c.total / meta.summary.total) * 100)}% dari total` : ''}</p>
            </button>
          ))}
        </div>
      )}

      <div className="mb-3"><SearchInput label="Cari pengeluaran" placeholder="Cari keterangan atau toko…" value={search} onChange={(v) => { setSearch(v); setParam('page', 1) }} /></div>

      <div className="card overflow-hidden">
        {query.isLoading ? <div className="space-y-3 p-5">{Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-12" />)}</div>
          : query.isError ? <ErrorState error={query.error} onRetry={() => query.refetch()} />
            : rows.length === 0 ? (
              <EmptyState icon={<Receipt className="size-7" />} title={filtered ? 'Tidak ada yang cocok' : 'Belum ada pengeluaran di periode ini'}
                description={filtered ? 'Coba ubah filter atau kata kunci.' : 'Catat pengeluaran agar laba per mobil terhitung akurat.'}
                action={!filtered && can(P.expenseCreate) ? <Button onClick={() => setCreating(true)}><Plus className="size-4" /> Catat Pengeluaran</Button> : undefined} />
            ) : (
              <ul className={cn('divide-y divide-line', query.isPlaceholderData && 'opacity-60')}>
                {rows.map((e) => (
                  <li key={e.id} className="grid gap-2 px-5 py-3.5 sm:grid-cols-[96px_1fr_auto] sm:items-center sm:gap-4">
                    <p className="text-xs font-semibold text-ink-soft tabular-nums">{dateOnly(e.spent_at)}</p>
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="font-semibold text-ink">{e.title}</p>
                        <Badge tone={e.car ? 'teal' : 'gray'} dot={false}>{e.category_label}</Badge>
                      </div>
                      <p className="mt-0.5 truncate text-xs text-ink-muted">
                        {e.car ? `${e.car.name} · ${e.car.plate_number}` : 'Umum'}
                        {e.vendor && ` · ${e.vendor}`}{e.method_label && ` · ${e.method_label}`}{e.created_by && ` · dicatat ${e.created_by}`}
                      </p>
                    </div>
                    <div className="flex items-center justify-between gap-2 sm:justify-end">
                      <p className="text-base font-bold tabular-nums">{rupiah(e.amount)}</p>
                      <div className="flex items-center gap-0.5">
                        {e.proof_url && (
                          <a href={e.proof_url} target="_blank" rel="noreferrer" className="rounded-lg p-2 text-ink-muted hover:bg-canvas hover:text-ink" aria-label={`Lihat bukti ${e.title}`} title="Lihat bukti">
                            <FileText className="size-4" />
                          </a>
                        )}
                        {e.can.update && <button type="button" className="rounded-lg p-2 text-ink-muted hover:bg-canvas hover:text-ink" aria-label={`Ubah ${e.title}`} title="Ubah" onClick={() => setEditing(e)}><Pencil className="size-4" /></button>}
                        {e.can.delete && <button type="button" className="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label={`Hapus ${e.title}`} title="Hapus" onClick={() => setDeleting(e)}><Trash2 className="size-4" /></button>}
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
        {query.data && rows.length > 0 && <div className="border-t border-line px-4 pb-4"><Pagination meta={query.data.meta} onPage={(p) => setParam('page', p)} /></div>}
      </div>

      <ExpenseFormModal open={creating || !!editing} expense={editing} categories={categories} onClose={() => { setCreating(false); setEditing(null) }} />
      <ConfirmDialog
        open={!!deleting}
        title="Hapus pengeluaran?"
        description={deleting && <>“{deleting.title}” sebesar <b>{rupiah(deleting.amount)}</b> ({dateOnly(deleting.spent_at)}) akan dihapus dan tidak lagi dihitung di laporan. Tercatat di Log Aktivitas.</>}
        confirmLabel="Hapus"
        loading={del.isPending}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && del.mutate(deleting.id, {
          onSuccess: (r) => { toast(r.message); setDeleting(null) },
          onError: (e) => toast(errorMessage(e), 'error'),
        })}
      />
    </>
  )
}
