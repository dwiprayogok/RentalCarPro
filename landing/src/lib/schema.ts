import type { CarDetail, SiteSettings } from './types'
import { SITE_URL } from './site'

/** schema.org AutoRental (turunan LocalBusiness). */
export function businessSchema(s: SiteSettings) {
  return {
    '@context': 'https://schema.org',
    '@type': 'AutoRental',
    '@id': `${SITE_URL}/#bisnis`,
    name: s.business.name,
    url: SITE_URL,
    description: s.hero.subtitle ?? undefined,
    telephone: s.business.whatsapp ?? undefined,
    address: s.business.address
      ? { '@type': 'PostalAddress', streetAddress: s.business.address, addressLocality: s.business.city ?? undefined, addressCountry: 'ID' }
      : undefined,
    areaServed: s.business.city ?? undefined,
    hasMap: s.business.maps_url ?? undefined,
    priceRange: 'Rp',
    currenciesAccepted: 'IDR',
    paymentAccepted: 'Tunai, Transfer, QRIS',
  }
}

export function carSchema(car: CarDetail, s: SiteSettings) {
  const url = `${SITE_URL}/armada/${car.slug}`
  return {
    '@context': 'https://schema.org',
    '@type': ['Product', 'Car'],
    name: car.name,
    url,
    brand: { '@type': 'Brand', name: car.brand },
    model: car.model,
    vehicleModelDate: String(car.year),
    vehicleTransmission: car.transmission,
    seatingCapacity: car.seats,
    fuelType: car.fuel_type ?? undefined,
    color: car.color ?? undefined,
    description: car.description ?? undefined,
    image: car.photos.length ? car.photos.map((p) => p.url) : undefined,
    offers: [
      { kind: 'Lepas kunci', price: car.rate_self_drive },
      ...(car.rate_with_driver ? [{ kind: 'Dengan driver', price: car.rate_with_driver }] : []),
    ].map((o) => ({
      '@type': 'Offer',
      name: `Sewa ${o.kind}`,
      price: o.price,
      priceCurrency: 'IDR',
      url,
      availability: car.availability.status === 'perawatan' ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/InStock',
      priceSpecification: { '@type': 'UnitPriceSpecification', price: o.price, priceCurrency: 'IDR', unitCode: 'DAY', referenceQuantity: { '@type': 'QuantitativeValue', value: 1, unitCode: 'DAY' } },
      seller: { '@id': `${SITE_URL}/#bisnis`, name: s.business.name },
    })),
  }
}
