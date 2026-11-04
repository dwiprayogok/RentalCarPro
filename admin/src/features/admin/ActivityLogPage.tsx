import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { ArrowRight, History } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { PageHeader } from '@/components/layout/PageHeader'
import { Avatar } from '@/components/ui/Avatar'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Feedback'
import { Field, Input, Select } from '@/components/ui/Field'
import { Pagination } from '@/components/ui/Pagination'
import { SearchInput } from '@/components/ui/SearchInput'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { api } from '@/lib/api'
import { cn } from '@/lib/cn'
import { dateTimeWib, initialsOf } from '@/lib/format'
import type { PaginationMeta } from '@/lib/types'
import { useDebounce } from '@/lib/useDebounce'

interface Entry {
  id: number
  at: string
  module: string
  module_label: string
  event: string | null
  event_label: string
  description: string
  causer: { id: number; name: string } | null
  subject: { type: string; id: number | null; label: string; link: string | null } | null
  changes: { field: string; label: string; old: string | null; new: string | null }[]
}
interface Opt { value: string; label: string }
type Meta = PaginationMeta & { modules: Opt[]; events: Opt[]; causers: { id: number; name: string }[] }

export function ActivityLogPage() {
  const [params, setParams] = useSearchParams()
  const f = {
    module: params.get('module') ?? '', event: params.get('event') ?? '', causer_id: params.get('user') ?? '',
    from: params.get('from') ?? '', to: params.get('to') ?? '', page: Number(params.get('page') ?? 1),
  }
  const [search, setSearch] = useState(params.get('q') ?? '')
  const q = useDebounce(search.trim())
  const set = (k: string, v: string | number | null) => {
    const p = new URLSearchParams(params)
    if (v === null || v === '' || (k === 'page' && v === 1)) p.delete(k); else p.set(k, String(v))
    if (k !== 'page') p.delete('page')
    setParams(p, { replace: true })
  }

  const query = useQuery({
    queryKey: ['activity', f, q],
    queryFn: async () => (await api.get<{ data: Entry[]; meta: Meta }>('/activity-log', {
      params: { module: f.module || undefined, event: f.event || undefined, causer_id: f.causer_id || undefined, from: f.from || undefined, to: f.to || undefined, search: q || undefined, page: f.page },
    })).data,
    placeholderData: keepPreviousData,
  })
  const meta = query.data?.meta

  return (
    <>
      <PageHeader eyebrow="Sistem" title="Log Aktivitas" subtitle="Siapa mengubah apa dan kapan: tarif, denda, status risiko, pembayaran, pengaturan, dan pengguna." />

      <div className="mb-5 grid gap-3 rounded-2xl border border-line bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div className="sm:col-span-2 lg:col-span-6"><SearchInput label="Cari aktivitas" placeholder="Cari deskripsi, nota, atau nilai…" value={search} onChange={(v) => { setSearch(v); set('page', 1) }} /></div>
        <Field label="Modul">{(a) => <Select {...a} value={f.module} onChange={(e) => set('module', e.target.value)}><option value="">Semua modul</option>{meta?.modules.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}</Select>}</Field>
        <Field label="Aksi">{(a) => <Select {...a} value={f.event} onChange={(e) => set('event', e.target.value)}><option value="">Semua aksi</option>{meta?.events.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}</Select>}</Field>
        <Field label="Pengguna" className="lg:col-span-2">{(a) => <Select {...a} value={f.causer_id} onChange={(e) => set('user', e.target.value)}><option value="">Semua pengguna</option>{meta?.causers.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</Select>}</Field>
        <Field label="Dari">{(a) => <Input {...a} type="date" value={f.from} onChange={(e) => set('from', e.target.value)} />}</Field>
        <Field label="Sampai">{(a) => <Input {...a} type="date" value={f.to} min={f.from} onChange={(e) => set('to', e.target.value)} />}</Field>
      </div>

      <div className="card overflow-hidden">
        {query.isLoading ? <div className="space-y-3 p-5">{Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-16" />)}</div>
          : query.isError ? <ErrorState error={query.error} onRetry={() => query.refetch()} />
            : !query.data?.data.length ? <EmptyState icon={<History className="size-7" />} title="Tidak ada aktivitas" description="Belum ada aktivitas yang cocok dengan filter." />
              : (
                <ol className={cn('divide-y divide-line', query.isPlaceholderData && 'opacity-60')}>
                  {query.data.data.map((e) => (
                    <li key={e.id} className="grid gap-3 px-5 py-4 md:grid-cols-[180px_1fr]">
                      <div className="flex items-start gap-2.5 md:block">
                        <Avatar initials={e.causer ? initialsOf(e.causer.name) : 'SY'} className="size-8 md:mb-1.5" />
                        <div>
                          <p className="text-sm font-semibold text-ink">{e.causer?.name ?? 'Sistem'}</p>
                          <p className="text-2xs text-ink-muted">{dateTimeWib(e.at)}</p>
                        </div>
                      </div>
                      <div className="min-w-0">
                        <p className="flex flex-wrap items-center gap-2 text-sm">
                          <Badge tone={e.event === 'deleted' ? 'red' : e.event === 'created' ? 'green' : 'blue'} dot={false}>{e.event_label}</Badge>
                          <span className="text-ink-muted">{e.module_label}</span>
                          {e.subject && (e.subject.link
                            ? <Link to={e.subject.link} className="font-semibold text-brand-700 hover:underline">{e.subject.label}</Link>
                            : <span className="font-semibold text-ink">{e.subject.label}</span>)}
                        </p>
                        {e.changes.length > 0 ? (
                          <ul className="mt-2 space-y-1 rounded-xl bg-canvas px-3 py-2 text-xs">
                            {e.changes.slice(0, 8).map((c) => (
                              <li key={c.field} className="flex flex-wrap items-center gap-1.5">
                                <span className="font-semibold text-ink-soft">{c.label}:</span>
                                {c.old !== null && <span className="text-ink-muted line-through decoration-ink-muted/40">{c.old}</span>}
                                {c.old !== null && <ArrowRight className="size-3 text-ink-muted" aria-label="menjadi" />}
                                <span className="font-medium text-ink">{c.new ?? '(kosong)'}</span>
                              </li>
                            ))}
                          </ul>
                        ) : <p className="mt-1 text-xs text-ink-muted">{e.description}</p>}
                      </div>
                    </li>
                  ))}
                </ol>
              )}
        {query.data && query.data.data.length > 0 && <div className="border-t border-line px-4 pb-4"><Pagination meta={query.data.meta} onPage={(p) => set('page', p)} /></div>}
      </div>
    </>
  )
}
