const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

export type HealthResponse = {
  status: string
  app: string
}

export type SoaStoreTotals = {
  store_name: string
  net_remittance: number
  total_cogs: number
  total_dsFee: number
}

export type ComputeSoaResponse = {
  status: string
  soa_id: number
  net_remittance: number
  total_cogs: number
  total_dsFee: number
  stores: Record<string, SoaStoreTotals>
}

export async function getHealth(signal?: AbortSignal): Promise<HealthResponse> {
  const response = await fetch(`${API_URL}/health`, {
    signal,
    credentials: 'include',
  })

  if (!response.ok) {
    throw new Error(`API responded with ${response.status}`)
  }

  return response.json() as Promise<HealthResponse>
}

/** Upload order files and save SOA order lines. */
export async function computeSoa(input: {
  billingStart: string
  billingEnd: string
  dropshippingFee: number
  files: File[]
}): Promise<ComputeSoaResponse> {
  const body = new FormData()
  body.append('billing_start', input.billingStart)
  body.append('billing_end', input.billingEnd)
  body.append('dropshipping_fee', String(input.dropshippingFee))
  input.files.forEach((file) => body.append('files[]', file))

  const response = await fetch(`${API_URL}/soa/compute`, {
    method: 'POST',
    credentials: 'include',
    body,
  })

  if (!response.ok) {
    throw new Error(`API responded with ${response.status}`)
  }

  return response.json() as Promise<ComputeSoaResponse>
}

