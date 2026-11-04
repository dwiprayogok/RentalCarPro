import { useEffect, useState } from 'react'
import { Outlet, useLocation } from 'react-router'
import { cn } from '@/lib/cn'
import { Sidebar } from './Sidebar'
import { Topbar } from './Topbar'

export function AppLayout() {
  const [open, setOpen] = useState(false)
  const { pathname } = useLocation()

  useEffect(() => setOpen(false), [pathname])

  return (
    <div className="min-h-dvh lg:pl-64">
      {/* Sidebar desktop */}
      <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 lg:block">
        <Sidebar />
      </aside>

      {/* Drawer mobile/tablet */}
      <div className={cn('fixed inset-0 z-50 lg:hidden', open ? 'pointer-events-auto' : 'pointer-events-none')} aria-hidden={!open}>
        <div className={cn('absolute inset-0 bg-sidebar/50 transition-opacity', open ? 'opacity-100' : 'opacity-0')} onClick={() => setOpen(false)} />
        <aside className={cn('absolute inset-y-0 left-0 w-72 max-w-[85vw] transition-transform', open ? 'translate-x-0' : '-translate-x-full')}>
          <Sidebar onNavigate={() => setOpen(false)} />
        </aside>
      </div>

      <div className="flex min-h-dvh flex-col">
        <Topbar onMenu={() => setOpen(true)} />
        <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8">
          <Outlet />
        </main>
        <footer className="flex flex-wrap justify-between gap-2 px-4 pb-6 text-2xs text-ink-muted sm:px-6 lg:px-8">
          <span>© {new Date().getFullYear()} RentalPro · Setiap perjalanan, lebih tertata.</span>
          <span>Waktu Indonesia Barat (WIB)</span>
        </footer>
      </div>
    </div>
  )
}
