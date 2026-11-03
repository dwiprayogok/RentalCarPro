import { forwardRef } from 'react'
import { cn } from '@/lib/cn'
import { Input } from './Field'

interface MoneyInputProps {
  className?: string
  id?: string
  value: number | null | undefined
  onChange: (value: number | null) => void
  onBlur?: () => void
  placeholder?: string
  invalid?: boolean
  disabled?: boolean
  'aria-invalid'?: boolean
  'aria-describedby'?: string
}

const fmt = new Intl.NumberFormat('id-ID')

/** Input rupiah: menampilkan pemisah ribuan, menyimpan integer. */
export const MoneyInput = forwardRef<HTMLInputElement, MoneyInputProps>(function MoneyInput(
  { value, onChange, invalid, className, ...rest },
  ref,
) {
  return (
    <div className="relative">
      <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm font-medium text-ink-muted">Rp</span>
      <Input
        ref={ref}
        inputMode="numeric"
        autoComplete="off"
        invalid={invalid}
        className={cn('pl-10 tabular-nums', className)}
        value={value === null || value === undefined ? '' : fmt.format(value)}
        onChange={(e) => {
          const digits = e.target.value.replace(/\D/g, '').slice(0, 12)
          onChange(digits === '' ? null : Number(digits))
        }}
        {...rest}
      />
    </div>
  )
})
