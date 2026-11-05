import { z } from 'zod'

const currentYear = new Date().getFullYear()
const plateRegex = /^[A-Z]{1,2} \d{1,4}( [A-Z]{1,3})?$/

const money = (label: string) =>
  z.number({ error: `${label} wajib diisi.` }).int().min(1, `${label} wajib diisi.`).max(1_000_000_000, `${label} terlalu besar.`)

const optionalMoney = z.number().int().min(1).max(1_000_000_000).nullable()

export const carSchema = z
  .object({
    brand: z.string().trim().min(1, 'Merk wajib diisi.').max(60),
    model: z.string().trim().min(1, 'Tipe wajib diisi.').max(80),
    plate_number: z
      .string()
      .transform((v) => v.trim().toUpperCase().replace(/\s+/g, ' '))
      .pipe(z.string().min(1, 'Plat nomor wajib diisi.').regex(plateRegex, 'Format plat nomor tidak valid. Contoh: F 1576 FGH.')),
    transmission: z.enum(['matic', 'manual']),
    year: z.number({ error: 'Tahun wajib diisi.' }).int().min(1990, 'Tahun minimal 1990.').max(currentYear + 1, `Tahun maksimal ${currentYear + 1}.`),
    color: z.string().trim().max(40),
    seats: z.number({ error: 'Isi jumlah kursi.' }).int().min(2, 'Minimal 2 kursi.').max(20, 'Maksimal 20 kursi.'),
    fuel_type: z.string().trim().max(30),
    rate_self_drive: money('Tarif lepas kunci'),
    rate_with_driver: money('Tarif dengan driver'),
    weekend_rate_self_drive: optionalMoney,
    weekend_rate_with_driver: optionalMoney,
    default_deposit: z.number().int().min(0).max(1_000_000_000).nullable(),
    odometer: z.number({ error: 'Odometer wajib diisi.' }).int().min(0, 'Odometer tidak boleh negatif.').max(5_000_000),
    status: z.enum(['tersedia', 'disewa', 'servis', 'nonaktif']),
    tax_due_date: z.string(),
    stnk_due_date: z.string(),
    next_service_date: z.string(),
    next_service_odometer: z.number().int().min(0).nullable(),
    slug: z.string().trim().max(120).regex(/^([a-z0-9]+(-[a-z0-9]+)*)?$/, 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.'),
    is_published: z.boolean(),
    description: z.string().trim().max(2000),
    features_text: z.string().max(800),
    notes: z.string().trim().max(2000),
    owner_id: z.string(),
  })
  .refine((v) => v.rate_with_driver >= v.rate_self_drive, {
    path: ['rate_with_driver'],
    message: 'Tarif dengan driver adalah total per hari termasuk driver, jadi tidak boleh lebih kecil dari tarif lepas kunci.',
  })

export type CarFormInput = z.input<typeof carSchema>
export type CarFormValues = z.output<typeof carSchema>
