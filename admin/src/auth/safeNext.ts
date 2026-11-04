/** Tujuan setelah login dari ?next=, hanya path internal (cegah open redirect). */
export function safeNext(next: string | null): string {
  return next && next.startsWith('/') && !next.startsWith('//') ? next : '/'
}
