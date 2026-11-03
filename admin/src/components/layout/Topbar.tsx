import { useIsFetching, useQueryClient } from '@tanstack/react-query'
import { Menu, RefreshCw } from 'lucide-react'
import { Link, useLocation } from 'react-router'
import { useMe } from '@/auth/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import { initialsOf } from '@/lib/format'
import { cn } from '@/lib/cn'
import { pageTitles } from './nav'

export function Topbar({ onMenu }: { onMenu: () => void }) {
  const { pathname } = useLocation()
  const { data: user } = useMe()
  const qc = useQueryClient()
  const fetching = useIsFetching() > 0
  const section = '/' + (pathname.split('/')[1] ?? '')
  const title = pageTitles[section] ?? 'Halaman'

  return (
    <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-white/90 px-4 backdrop-blur sm:px-6">
      <button type="button" onClick={onMenu} className="-ml-1 rounded-lg p-2 text-ink-soft hover:bg-canvas lg:hidden" aria-label="Buka menu">
        <Menu className="size-5" />
      </button>

      <nav aria-label="Breadcrumb" className="min-w-0 flex-1">
        <ol className="flex items-center gap-2 text-xs">
          <li><Link to="/" className="text-ink-muted hover:text-ink">Workspace</Link></li>
          <li aria-hidden className="text-ink-muted">/</li>
          <li aria-current="page" className="truncate font-semibold text-brand-600">{title}</li>
        </ol>
      </nav>

      <button
        type="button"
        onClick={() => qc.invalidateQueries({ predicate: (q) => q.queryKey[0] !== 'auth' })}
        className="rounded-lg p-2 text-ink-soft hover:bg-canvas"
        aria-label="Muat ulang data"
        title="Muat ulang data"
      >
        <RefreshCw className={cn('size-[18px]', fetching && 'animate-spin text-brand-500')} />
      </button>
      {user && <Avatar initials={initialsOf(user.name)} className="size-9" />}
    </header>
  )
}
