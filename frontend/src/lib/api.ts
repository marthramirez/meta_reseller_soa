const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

export type HealthResponse = {
  status: string
  app: string
}

export type ComputeSoaResponse = {
  status: string
  soa_id: number
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
  sellerName: string
  storeName: string
  files: File[]
}): Promise<ComputeSoaResponse> {
  const body = new FormData()
  body.append('billing_start', input.billingStart)
  body.append('billing_end', input.billingEnd)
  body.append('seller_name', input.sellerName)
  body.append('store_name', input.storeName)
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

