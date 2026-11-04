import type { ChecklistItem } from './types'

export const DEFAULT_CHECKLIST = [
  'Body & cat', 'Kaca & spion', 'Lampu', 'Ban & velg', 'Ban serep & dongkrak', 'Interior bersih', 'AC dingin', 'STNK', 'Kunci & remote',
]

export const FUEL_LEVELS = [
  { value: 0, label: 'Kosong' }, { value: 25, label: '¼' }, { value: 50, label: '½' }, { value: 75, label: '¾' }, { value: 100, label: 'Penuh' },
]

export interface HandoverValue {
  odometer: number | null
  fuel_level: number
  checklist: ChecklistItem[]
  notes: string
  photos: File[]
}

export function emptyHandover(odometer: number | null): HandoverValue {
  return { odometer, fuel_level: 100, checklist: DEFAULT_CHECKLIST.map((item) => ({ item, ok: true, note: '' })), notes: '', photos: [] }
}
