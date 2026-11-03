import { LogOut, ShieldCheck } from 'lucide-react'
import { NavLink, useNavigate } from 'react-router'
import { useCan, useLogout, useMe } from '@/auth/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import { initialsOf } from '@/lib/format'
import { cn } from '@/lib/cn'
import { ROLE_LABELS } from '@/lib/permissions'
import { Logo } from './Logo'
import { insightNav, workspaceNav, type NavItem } from './nav'

function NavSection({ title, items, onNavigate }: { title: string; items: NavItem[]; onNavigate?: () => void }) {
  const can = useCan()
  const visible = items.filter((i) => i.anyOf.length === 0 || can(...i.anyOf))
  if (!visible.length) return null
  return (
    <div>
      <p className="mb-2 px-3 text-2xs font-bold tracking-[0.16em] text-sidebar-muted uppercase">{title}</p>
      <ul className="space-y-1">
        {visible.map((item) => (
          <li key={item.to}>
            <NavLink
              to={item.to}
              end={item.end}
              onClick={onNavigate}
              className={({ isActive }) =>
                cn(
                  'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                  isActive ? 'bg-sidebar-active text-white' : 'text-sidebar-muted hover:bg-sidebar-hover hover:text-white',
                )
              }
            >
              {({ isActive }) => (
                <>
                  <item.icon className="size-[18px] shrink-0" />
                  <span className="flex-1">{item.label}</span>
                  {isActive && <span className="size-1.5 rounded-full bg-brand-300" aria-hidden />}
                </>
              )}
            </NavLink>
          </li>
        ))}
      </ul>
    </div>
  )
}

export function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const { data: user } = useMe()
  const logout = useLogout()
  const navigate = useNavigate()

  return (
    <div className="flex h-full flex-col bg-sidebar px-4 py-6 text-white">
      <div className="px-2"><Logo /></div>

      <nav className="mt-8 flex-1 space-y-6 overflow-y-auto" aria-label="Menu utama">
        <NavSection title="Workspace" items={workspaceNav} onNavigate={onNavigate} />
        <div className="mx-3 border-t border-white/10" />
        <NavSection title="Insight & Bantuan" items={insightNav} onNavigate={onNavigate} />
      </nav>

      <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4">
        <ShieldCheck className="size-5 text-brand-300" />
        <p className="mt-2 text-sm font-bold">Bisnis rapi, perjalanan lancar</p>
        <p className="mt-1 text-2xs leading-relaxed text-sidebar-muted">Data sewa, armada, dan pelanggan tersimpan aman di satu tempat.</p>
      </div>

      {user && (
        <div className="mt-4 flex items-center gap-3 rounded-2xl px-2 py-2">
          <Avatar initials={initialsOf(user.name)} className="size-9 bg-brand-300 text-sidebar ring-0" />
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-semibold">{user.name}</p>
            <p className="truncate text-2xs text-sidebar-muted">{user.roles.map((r) => ROLE_LABELS[r] ?? r).join(', ')}</p>
          </div>
          <button
            type="button"
            onClick={() => logout.mutate(undefined, { onSettled: () => navigate('/login', { replace: true }) })}
            className="rounded-lg p-2 text-sidebar-muted hover:bg-sidebar-hover hover:text-white"
            aria-label="Keluar"
            title="Keluar"
          >
            <LogOut className="size-4" />
          </button>
        </div>
      )}
    </div>
  )
}
