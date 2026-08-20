import { useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'

import { ROUTES } from '@/constants/routes.constants'
import { cn } from '@/lib/utils'
import type { SoaStoreTotals } from '@/lib/api'

export type SoaResultState = {
  billingStart: string
  billingEnd: string
  net_remittance: number
  total_cogs: number
  total_dsFee: number
  stores: Record<string, SoaStoreTotals>
}

/** Format a money amount as Philippine pesos. */
function formatMoney(value: number) {
  return value.toLocaleString('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

/** Format a YYYY-MM-DD date for display. */
function formatDate(value: string) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

/** Return true when location state has SOA result fields. */
function isSoaResultState(value: unknown): value is SoaResultState {
  if (!value || typeof value !== 'object') {
    return false
  }

  const state = value as Partial<SoaResultState>

  return (
    typeof state.billingStart === 'string' &&
    typeof state.billingEnd === 'string' &&
    typeof state.net_remittance === 'number' &&
    typeof state.total_cogs === 'number' &&
    typeof state.total_dsFee === 'number' &&
    typeof state.stores === 'object' &&
    state.stores !== null
  )
}

/** Result page for a computed SOA. */
export default function SoaResultPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const result = isSoaResultState(location.state) ? location.state : null
  const stores = result ? Object.values(result.stores) : []
  const [query, setQuery] = useState('')

  if (!result) {
    return <Navigate to={ROUTES.upload} replace />
  }

  const period = `${formatDate(result.billingStart)} – ${formatDate(result.billingEnd)}`
  const filteredStores = stores.filter((store) =>
    store.store_name.toLowerCase().includes(query.trim().toLowerCase()),
  )

  return (
    <div
      className={cn(
        'flex h-full min-h-0 flex-1 flex-col overflow-hidden',
        'bg-background px-5 py-4 text-foreground md:px-8',
      )}
    >
      <div className="flex shrink-0 items-start justify-between gap-4">
        <div className="flex min-w-0 items-start gap-3">
          <button
            type="button"
            aria-label="Back"
            className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
            onClick={() => navigate(-1)}
          >
            <svg
              viewBox="0 0 16 16"
              fill="none"
              aria-hidden="true"
              className="h-4 w-4"
            >
              <path
                d="M10 3.5 5.5 8 10 12.5"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </button>
          <div className="min-w-0">
            <h2 className="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
              Statement of Account
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Meta Resellers
            </p>
          </div>
        </div>
      </div>

      <div className="mt-6 grid shrink-0 grid-cols-2 gap-6 border-b border-border pb-5">
        <div>
          <p className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
            Billing Period
          </p>
          <p className="mt-2 text-sm text-foreground">{period}</p>
        </div>
        <div>
          <p className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
            Stores
          </p>
          <p className="mt-2 text-sm text-foreground">{stores.length}</p>
        </div>
      </div>

      <div className="grid shrink-0 grid-cols-1 gap-x-8 gap-y-5 border-b border-border py-5 sm:grid-cols-3">
        <div>
          <p className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
            J&T Net Remittance
          </p>
          <p className="mt-1 text-lg font-semibold tabular-nums tracking-tight text-foreground">
            {formatMoney(result.net_remittance)}
          </p>
        </div>
        <div>
          <p className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
            Total COGS
          </p>
          <p className="mt-1 text-lg font-semibold tabular-nums tracking-tight text-foreground">
            {formatMoney(result.total_cogs)}
          </p>
        </div>
        <div>
          <p className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
            Total DS Fee
          </p>
          <p className="mt-1 text-lg font-semibold tabular-nums tracking-tight text-foreground">
            {formatMoney(result.total_dsFee)}
          </p>
        </div>
      </div>

      <div className="mt-5 flex shrink-0 items-center">
        <label className="relative w-full max-w-md">
          <svg
            viewBox="0 0 16 16"
            fill="none"
            aria-hidden="true"
            className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
          >
            <circle cx="7" cy="7" r="4.5" stroke="currentColor" strokeWidth="1.5" />
            <path
              d="M10.5 10.5 14 14"
              stroke="currentColor"
              strokeWidth="1.5"
              strokeLinecap="round"
            />
          </svg>
          <input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Search store..."
            className="h-9 w-full rounded-md border border-input bg-background pr-3 pl-9 text-sm text-foreground placeholder:text-muted-foreground outline-none focus:border-ring"
          />
        </label>
      </div>

      <div className="mt-4 min-h-0 flex-1 basis-0 overflow-auto rounded-md border border-border custom-scrollbar">
        <table className="w-full min-w-[720px] border-collapse text-left text-sm">
          <thead>
            <tr className="bg-muted text-[11px] font-medium uppercase tracking-[0.08em] text-muted-foreground">
              <th className="sticky top-0 z-10 w-12 bg-muted px-4 py-2 font-medium">
                #
              </th>
              <th className="sticky top-0 z-10 bg-muted px-4 py-2 font-medium">
                Store
              </th>
              <th className="sticky top-0 z-10 bg-muted px-4 py-2 text-right font-medium">
                J&T Net Remittance
              </th>
              <th className="sticky top-0 z-10 bg-muted px-4 py-2 text-right font-medium">
                Total COGS
              </th>
              <th className="sticky top-0 z-10 bg-muted px-4 py-2 text-right font-medium">
                Total DS Fee
              </th>
            </tr>
          </thead>
          <tbody>
            {filteredStores.map((store, index) => (
              <tr
                key={store.store_name}
                className="border-t border-border hover:bg-muted/50"
              >
                <td className="px-4 py-2.5 text-muted-foreground">{index + 1}</td>
                <td className="px-4 py-2.5 font-medium text-foreground">
                  {store.store_name}
                </td>
                <td className="px-4 py-2.5 text-right tabular-nums text-foreground">
                  {formatMoney(store.net_remittance)}
                </td>
                <td className="px-4 py-2.5 text-right tabular-nums text-foreground">
                  {formatMoney(store.total_cogs)}
                </td>
                <td className="px-4 py-2.5 text-right tabular-nums text-foreground">
                  {formatMoney(store.total_dsFee)}
                </td>
              </tr>
            ))}
            {filteredStores.length === 0 ? (
              <tr>
                <td
                  colSpan={5}
                  className="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                  No stores found.
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}

