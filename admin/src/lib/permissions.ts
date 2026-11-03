/** Sama dengan App\Support\Permissions di backend. */
export const P = {
  dashboardView: 'dashboard.view',
  rentalView: 'rental.view',
  carView: 'car.view',
  carCreate: 'car.create',
  carUpdate: 'car.update',
  carDelete: 'car.delete',
  carPublish: 'car.publish',
  customerView: 'customer.view',
  customerCreate: 'customer.create',
  customerUpdate: 'customer.update',
  customerDelete: 'customer.delete',
  customerChangeRisk: 'customer.change_risk',
  customerViewDocuments: 'customer.view_documents',
  reportView: 'report.view',
  reportOperational: 'report.operational',
} as const

export const ROLE_LABELS: Record<string, string> = { owner: 'Pemilik', admin: 'Admin', staff: 'Staf' }
