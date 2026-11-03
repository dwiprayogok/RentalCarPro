import { z } from 'zod'
import { isValidIndonesianPhone, normalizePhone } from '@/lib/phone'

const PHONE_MSG = 'Gunakan nomor HP Indonesia, mis. 0812xxxx atau +62812xxxx.'
const SELF_DRIVE_MSG = 'Wajib diisi untuk layanan Lepas Kunci.'

/** Validasi sisi klien yang sama dengan SaveCustomerRequest (server tetap memvalidasi ulang). */
export const makeCustomerSchema = (isEdit: boolean) =>
  z
    .object({
      name: z.string().trim().min(2, 'Nama minimal 2 karakter.').max(120),
      phone: z.string().trim().min(1, 'Nomor HP wajib diisi.').refine(isValidIndonesianPhone, PHONE_MSG),
      nik: z.string().transform((v) => v.replace(/[\s.-]/g, '')).refine(
        (v) => (isEdit && v === '') || /^\d{16}$/.test(v),
        'Nomor KTP harus 16 digit angka.',
      ),
      for_self_drive: z.boolean(),
      sim_number: z.string().transform((v) => v.replace(/[\s.-]/g, '').toUpperCase()),
      sim_type: z.string(),
      sim_expires_at: z.string(),
      address: z.string().trim().max(500),
      emergency_contact_name: z.string().trim().max(120),
      emergency_contact_phone: z.string().trim(),
      emergency_contact_relation: z.string().trim().max(40),
      risk_status: z.enum(['lancar', 'perhatian', 'blacklist']),
      risk_note: z.string().trim().max(1000),
      notes: z.string().trim().max(2000),
    })
    .superRefine((v, ctx) => {
      const add = (path: string, message: string) => ctx.addIssue({ code: 'custom', path: [path], message })

      if (v.for_self_drive) {
        if (!v.sim_number) add('sim_number', SELF_DRIVE_MSG)
        if (!v.sim_expires_at) add('sim_expires_at', SELF_DRIVE_MSG)
        if (!v.address) add('address', SELF_DRIVE_MSG)
        if (!v.emergency_contact_name) add('emergency_contact_name', SELF_DRIVE_MSG)
        if (!v.emergency_contact_phone) add('emergency_contact_phone', SELF_DRIVE_MSG)
      }
      if (v.sim_number && !/^[0-9A-Z]{10,20}$/.test(v.sim_number)) add('sim_number', 'Nomor SIM harus 10–20 karakter angka/huruf.')
      if (v.sim_expires_at) {
        const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date())
        if (v.sim_expires_at <= today) add('sim_expires_at', 'SIM sudah kedaluwarsa. Masa berlaku harus setelah hari ini.')
      }
      if (v.emergency_contact_phone) {
        if (!isValidIndonesianPhone(v.emergency_contact_phone)) add('emergency_contact_phone', PHONE_MSG)
        else if (normalizePhone(v.emergency_contact_phone) === normalizePhone(v.phone)) add('emergency_contact_phone', 'Nomor kontak darurat harus berbeda dari nomor pelanggan.')
      }
      if (v.risk_status !== 'lancar' && !v.risk_note) add('risk_note', 'Catatan risiko wajib diisi untuk status Perhatian atau Blacklist.')
    })

export type CustomerFormInput = z.input<ReturnType<typeof makeCustomerSchema>>
export type CustomerFormValues = z.output<ReturnType<typeof makeCustomerSchema>>
