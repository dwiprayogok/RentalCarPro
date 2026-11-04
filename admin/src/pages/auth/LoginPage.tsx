import { zodResolver } from '@hookform/resolvers/zod'
import { Eye, EyeOff } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { z } from 'zod'
import { useLogin } from '@/auth/useAuth'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Feedback'
import { Field, Input } from '@/components/ui/Field'
import { errorMessage, errorStatus } from '@/lib/api'
import { applyServerErrors } from '@/lib/form'
import { AuthShell } from './AuthShell'

const schema = z.object({
  email: z.string().trim().min(1, 'Email wajib diisi.').email('Format email tidak valid.'),
  password: z.string().min(1, 'Kata sandi wajib diisi.'),
  remember: z.boolean(),
})
type Values = z.infer<typeof schema>

export function LoginPage() {
  const login = useLogin()
  const [showPassword, setShowPassword] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const { register, handleSubmit, setError, formState: { errors } } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '', remember: true },
  })

  const onSubmit = handleSubmit((values) => {
    setFormError(null)
    login.mutate(values, {
      // Pengalihan setelah login ditangani GuestOnly (membaca ?next=).
      onError: (error) => {
        const orphan = applyServerErrors(error, setError, ['email', 'password'])
        if (errorStatus(error) !== 422 || orphan) setFormError(orphan ?? errorMessage(error))
      },
    })
  })

  return (
    <AuthShell title="Masuk ke RentalPro" subtitle="Gunakan email dan kata sandi akun Anda.">
      <form onSubmit={onSubmit} noValidate className="space-y-4">
        {formError && <Alert>{formError}</Alert>}

        <Field label="Email" error={errors.email?.message}>
          {(a) => <Input {...a} {...register('email')} type="email" autoComplete="email" placeholder="nama@usaha.com" invalid={!!errors.email} />}
        </Field>

        <Field label="Kata sandi" error={errors.password?.message}>
          {(a) => (
            <div className="relative">
              <Input {...a} {...register('password')} type={showPassword ? 'text' : 'password'} autoComplete="current-password" invalid={!!errors.password} className="pr-10" />
              <button
                type="button"
                onClick={() => setShowPassword((v) => !v)}
                className="absolute top-1/2 right-2 -translate-y-1/2 rounded p-1.5 text-ink-muted hover:text-ink"
                aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
              >
                {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
              </button>
            </div>
          )}
        </Field>

        <div className="flex items-center justify-between">
          <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-soft">
            <input type="checkbox" {...register('remember')} className="size-4 rounded border-line-strong accent-brand-500" />
            Ingat saya
          </label>
          <Link to="/lupa-password" className="text-sm font-semibold text-brand-600 hover:underline">Lupa kata sandi?</Link>
        </div>

        <Button type="submit" className="w-full" loading={login.isPending}>Masuk</Button>
      </form>
    </AuthShell>
  )
}
