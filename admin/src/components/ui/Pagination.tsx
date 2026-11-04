import type { PaginationMeta } from '@/lib/types'
import { Button } from './Button'

export function Pagination({ meta, onPage }: { meta: PaginationMeta; onPage: (page: number) => void }) {
  const { total, current_page: page, last_page: last } = meta
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-1 pt-4 text-xs text-ink-muted">
      <span>
        {total} data · Halaman {total ? page : 0} / {total ? last : 0}
      </span>
      <div className="flex gap-2">
        <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => onPage(page - 1)}>Sebelumnya</Button>
        <Button size="sm" variant="secondary" disabled={page >= last} onClick={() => onPage(page + 1)}>Berikutnya</Button>
      </div>
    </div>
  )
}
