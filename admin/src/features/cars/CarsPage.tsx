import { CarFront, Plus } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { useCan } from '@/auth/useAuth'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { FilterChips } from '@/components/ui/FilterChips'
import { Pagination } from '@/components/ui/Pagination'
import { SearchInput } from '@/components/ui/SearchInput'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { P } from '@/lib/permissions'
import type { Car, CarStatus } from '@/lib/types'
import { useDebounce } from '@/lib/useDebounce'
import { useCars } from './api'
import { CarCard, CarCardSkeleton } from './CarCard'
import { CarDetailModal } from './CarDetailModal'
import { CarFormModal } from './CarFormModal'

type StatusFilter = '' | CarStatus

export function CarsPage() {
  const can = useCan()
  const [params, setParams] = useSearchParams()
  const status = (params.get('status') ?? '') as StatusFilter
  const page = Number(params.get('page') ?? 1)
  const [search, setSearch] = useState(params.get('q') ?? '')
  const debounced = useDebounce(search.trim())

  const [form, setForm] = useState<{ open: boolean; car?: Car | null }>({ open: false })
  const [detailId, setDetailId] = useState<number | null>(null)

  const update = (next: Record<string, string | number | null>) => {
    const p = new URLSearchParams(params)
    for (const [k, v] of Object.entries(next)) {
      if (v === null || v === '' || (k === 'page' && v === 1)) p.delete(k)
      else p.set(k, String(v))
    }
    setParams(p, { replace: true })
  }

  const query = useCars({ search: debounced, status, page })
  const counts = query.data?.meta.counts
  const filtered = !!debounced || !!status

  return (
    <>
      <PageHeader
        title="Armada"
        subtitle="Semua kendaraan, tarif, dan status ketersediaannya."
        action={can(P.carCreate) && <Button onClick={() => setForm({ open: true, car: null })}><Plus className="size-4" /> Tambah Armada</Button>}
      />

      <div className="mb-5 flex flex-col gap-3">
        <SearchInput
          label="Cari armada"
          placeholder="Cari merk, tipe, atau plat nomor…"
          value={search}
          onChange={(v) => { setSearch(v); update({ q: v || null, page: 1 }) }}
        />
        <FilterChips<StatusFilter>
          label="Filter status armada"
          value={status}
          onChange={(v) => update({ status: v, page: 1 })}
          options={[
            { value: '', label: 'Semua armada', count: counts?.semua },
            { value: 'tersedia', label: 'Tersedia', count: counts?.tersedia },
            { value: 'disewa', label: 'Disewa', count: counts?.disewa },
            { value: 'servis', label: 'Servis', count: counts?.servis },
          ]}
        />
      </div>

      {query.isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
          {Array.from({ length: 6 }, (_, i) => <CarCardSkeleton key={i} />)}
        </div>
      ) : query.isError ? (
        <div className="card"><ErrorState error={query.error} onRetry={() => query.refetch()} /></div>
      ) : query.data && query.data.data.length === 0 ? (
        <div className="card">
          {filtered ? (
            <EmptyState
              icon={<CarFront className="size-7" />}
              title="Tidak ada armada yang cocok"
              description="Coba ubah kata kunci atau pilih filter status lain."
              action={<Button variant="secondary" onClick={() => { setSearch(''); update({ q: null, status: null, page: 1 }) }}>Reset filter</Button>}
            />
          ) : (
            <EmptyState
              icon={<CarFront className="size-7" />}
              title="Belum ada armada"
              description="Tambahkan mobil pertama Anda beserta tarif sewanya."
              action={can(P.carCreate) && <Button onClick={() => setForm({ open: true, car: null })}><Plus className="size-4" /> Tambah Armada</Button>}
            />
          )}
        </div>
      ) : query.data ? (
        <>
          <div className={`grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 ${query.isPlaceholderData ? 'opacity-60' : ''}`}>
            {query.data.data.map((car) => <CarCard key={car.id} car={car} onDetail={() => setDetailId(car.id)} />)}
          </div>
          <Pagination meta={query.data.meta} onPage={(p) => update({ page: p })} />
        </>
      ) : null}

      <CarDetailModal carId={detailId} onClose={() => setDetailId(null)} onEdit={(car) => { setDetailId(null); setForm({ open: true, car }) }} />
      <CarFormModal
        open={form.open}
        car={form.car}
        onClose={() => setForm({ open: false })}
        onSaved={(car) => { if (!form.car) setDetailId(car.id) }}
      />
    </>
  )
}
