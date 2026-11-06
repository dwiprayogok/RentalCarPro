export const SITE_URL = (process.env.SITE_URL || 'http://localhost:3000').replace(/\/$/, '')
/** URL API untuk komponen client (browser). */
export const PUBLIC_API = (process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/public').replace(/\/$/, '')
