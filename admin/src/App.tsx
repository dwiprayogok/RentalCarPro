import { createBrowserRouter, RouterProvider } from 'react-router'
import { GuestOnly, RequireAuth, RequirePermission } from '@/auth/guards'
import { AppLayout } from '@/components/layout/AppLayout'
import { CarsPage } from '@/features/cars/CarsPage'
import { CustomersPage } from '@/features/customers/CustomersPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { RentalsPage } from '@/features/rentals/RentalsPage'
import { P } from '@/lib/permissions'
import { ForgotPasswordPage } from '@/pages/auth/ForgotPasswordPage'
import { LoginPage } from '@/pages/auth/LoginPage'
import { ResetPasswordPage } from '@/pages/auth/ResetPasswordPage'
import { ComingSoonPage } from '@/pages/ComingSoonPage'
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
          {
            path: 'laporan',
            element: (
              <RequirePermission anyOf={[P.reportView, P.reportOperational]}>
                <ComingSoonPage title="Laporan Pembayaran" subtitle="Pembayaran masuk, piutang, dan pendapatan." note="Laporan dan ekspor CSV dibangun setelah modul transaksi." />
              </RequirePermission>
            ),
          },
          { path: 'panduan', element: <ComingSoonPage title="Panduan Penggunaan" subtitle="Cara memakai RentalPro langkah demi langkah." note="Panduan akan disusun setelah alur transaksi selesai." /> },
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
])

export default function App() {
  return <RouterProvider router={router} />
}
