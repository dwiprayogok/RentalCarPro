import { createBrowserRouter, RouterProvider } from 'react-router'
import { GuestOnly, RequireAuth, RequirePermission } from '@/auth/guards'
import { AppLayout } from '@/components/layout/AppLayout'
import { CarsPage } from '@/features/cars/CarsPage'
import { CustomersPage } from '@/features/customers/CustomersPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { RentalsPage } from '@/features/rentals/RentalsPage'
import { BookingRequestsPage } from '@/features/booking-requests/BookingRequestsPage'
import { ReportsPage } from '@/features/reports/ReportsPage'
import { GuidePage } from '@/pages/GuidePage'
import { ActivityLogPage } from '@/features/admin/ActivityLogPage'
import { LandingSettingsPage } from '@/features/admin/LandingSettingsPage'
import { SettingsPage } from '@/features/admin/SettingsPage'
import { UsersPage } from '@/features/admin/UsersPage'
import { P } from '@/lib/permissions'
import { ForgotPasswordPage } from '@/pages/auth/ForgotPasswordPage'
import { LoginPage } from '@/pages/auth/LoginPage'
import { ResetPasswordPage } from '@/pages/auth/ResetPasswordPage'
import { NotFoundPage } from '@/pages/NotFoundPage'

const router = createBrowserRouter([
  {
    element: <GuestOnly />,
    children: [
      { path: '/login', element: <LoginPage /> },
      { path: '/lupa-password', element: <ForgotPasswordPage /> },
    ],
  },
  // Dibuka dari tautan email; tetap bisa diakses walau sedang login.
  { path: '/reset-password', element: <ResetPasswordPage /> },
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true, element: <RequirePermission anyOf={[P.dashboardView]}><DashboardPage /></RequirePermission> },
          { path: 'transaksi', element: <RequirePermission anyOf={[P.rentalView]}><RentalsPage /></RequirePermission> },
          { path: 'armada', element: <RequirePermission anyOf={[P.carView]}><CarsPage /></RequirePermission> },
          { path: 'pelanggan', element: <RequirePermission anyOf={[P.customerView]}><CustomersPage /></RequirePermission> },
          { path: 'permintaan-booking', element: <RequirePermission anyOf={[P.bookingRequestView]}><BookingRequestsPage /></RequirePermission> },
          { path: 'laporan', element: <RequirePermission anyOf={[P.reportView]}><ReportsPage /></RequirePermission> },
          { path: 'panduan', element: <GuidePage /> },
          { path: 'pengaturan', element: <RequirePermission anyOf={[P.settingUpdate]}><SettingsPage /></RequirePermission> },
          { path: 'pengaturan-landing', element: <RequirePermission anyOf={[P.landingManage]}><LandingSettingsPage /></RequirePermission> },
          { path: 'pengguna', element: <RequirePermission anyOf={[P.userManage]}><UsersPage /></RequirePermission> },
          { path: 'log-aktivitas', element: <RequirePermission anyOf={[P.activityView]}><ActivityLogPage /></RequirePermission> },
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
])

export default function App() {
  return <RouterProvider router={router} />
}
