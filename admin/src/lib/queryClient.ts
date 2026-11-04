import { QueryClient } from '@tanstack/react-query'
import { errorStatus } from './api'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: false,
      retry: (count, error) => {
        const status = errorStatus(error)
        if (status && status >= 400 && status < 500) return false
        return count < 2
      },
    },
  },
})
