import { z } from 'zod'
import { parseWibInput } from '@/lib/datetime'

export const newRentalSchema = z
  .object({
    customer_id: z.number({ error: 'Pilih pelanggan.' }),
    customer_blacklisted: z.boolean(),
    car_id: z.number({ error: 'Pilih mobil.' }),
    service_type: z.enum(['lepas_kunci', 'dengan_driver']),
    driver_id: z.number().nullable(),
    start_now: z.boolean(),
    start_at: z.string(),
    planned_end_at: z.string().min(1, 'Isi jam kembali rencana.'),
    deposit_required: z.number().int().min(0).nullable(),
    discount: z.number().int().min(0).nullable(),
    discount_reason: z.string().trim().max(255),
    blacklist_override_reason: z.string().trim().max(500),
    notes: z.string().trim().max(2000),
    dp_amount: z.number().int().min(0).nullable(),
    dp_method: z.enum(['tunai', 'transfer', 'qris']),
    dp_reference: z.string().trim().max(100),
    deposit_paid: z.boolean(),
    deposit_method: z.enum(['tunai', 'transfer', 'qris']),
    deposit_reference: z.string().trim().max(100),
  })
  .superRefine((v, ctx) => {
    const add = (path: string, message: string) => ctx.addIssue({ code: 'custom', path: [path], message })
    if (v.service_type === 'dengan_driver' && !v.driver_id) add('driver_id', 'Pilih driver untuk layanan Dengan Driver.')
    if (!v.start_now && !v.start_at) add('start_at', 'Isi waktu mulai, atau pilih "Mulai sekarang".')
    if (v.planned_end_at) {
      const start = v.start_now ? new Date() : v.start_at ? parseWibInput(v.start_at) : null
      if (start && parseWibInput(v.planned_end_at) <= start) add('planned_end_at', 'Jam kembali harus setelah waktu mulai sewa.')
    }
    if ((v.discount ?? 0) > 0 && !v.discount_reason) add('discount_reason', 'Alasan diskon wajib diisi.')
    if (v.customer_blacklisted && v.blacklist_override_reason.length < 10) add('blacklist_override_reason', 'Alasan pengecualian minimal 10 karakter.')
    if ((v.dp_amount ?? 0) > 0 && v.dp_method !== 'tunai' && !v.dp_reference) add('dp_reference', 'Isi nomor referensi untuk Transfer/QRIS.')
    if (v.deposit_paid && v.deposit_method !== 'tunai' && !v.deposit_reference) add('deposit_reference', 'Isi nomor referensi untuk Transfer/QRIS.')
  })

export type NewRentalInput = z.input<typeof newRentalSchema>
export type NewRentalValues = z.output<typeof newRentalSchema>
