import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation, useSearchParams } from 'react-router'
import { FullPageLoader } from '@/components/ui/Feedback'
import { ForbiddenState, ErrorState } from '@/components/ui/States'
import { safeNext } from './safeNext'
import { useCan, useMe } from './useAuth'

/** Halaman yang butuh login. Mengarahkan ke /login dengan ?next= untuk kembali setelah masuk. */
export function RequireAuth() {
  const { data: user, isLoading, isError, refetch } = useMe()
  const location = useLocation()

  if (isLoading) return <FullPageLoader />
  if (isError) return <div className="p-6"><ErrorState onRetry={() => refetch()} /></div>
  if (!user) {
    const next = location.pathname + location.search
    return <Navigate to={`/login${next !== '/' ? `?next=${encodeURIComponent(next)}` : ''}`} replace />
  }
  return <Outlet />
}

/** Halaman login/lupa password: pengguna yang sudah masuk diarahkan ke dashboard. */
export function GuestOnly() {
  const { data: user, isLoading } = useMe()
  const [params] = useSearchParams()
  if (isLoading) return <FullPageLoader />
  if (user) return <Navigate to={safeNext(params.get('next'))} replace />
  return <Outlet />
}

/** Route guard berbasis permission (cukup salah satu). */
export function RequirePermission({ anyOf, children }: { anyOf: string[]; children: ReactNode }) {
  const can = useCan()
  if (!can(...anyOf)) return <ForbiddenState />
  return <>{children}</>
}
