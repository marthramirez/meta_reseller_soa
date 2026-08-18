import { useState, type FormEvent } from 'react'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { DateRangePickerInputs } from '@/components/ui/DateRangePickerInputs'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { computeSoa } from '@/lib/api'
import { cn } from '@/lib/utils'

const ACCEPTED_TYPES = '.csv,.xlsx'

/** Return true when the file is csv or xlsx. */
function isAcceptedFile(file: File) {
  const name = file.name.toLowerCase()
  return name.endsWith('.csv') || name.endsWith('.xlsx')
}

/** Format bytes as a short file-size label. */
function formatFileSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

/** CSV or XLSX file-type mark. */
function FileTypeLogo({ name }: { name: string }) {
  const isCsv = name.toLowerCase().endsWith('.csv')
  const label = isCsv ? 'CSV' : 'XLSX'
  const color = isCsv ? 'bg-emerald-600' : 'bg-green-700'

  return (
    <div
      className={cn(
        'flex h-10 w-8 items-end justify-center rounded-sm pb-1 text-[8px] font-bold text-white',
        color,
      )}
    >
      {label}
    </div>
  )
}

/** Upload form for Meta order export files. */
export function OrderExportUploadForm() {
  const [billingStart, setBillingStart] = useState('')
  const [billingEnd, setBillingEnd] = useState('')
  const [sellerName, setSellerName] = useState('')
  const [storeName, setStoreName] = useState('')
  const [files, setFiles] = useState<File[]>([])
  const [isDragging, setIsDragging] = useState(false)
  const [isSaving, setIsSaving] = useState(false)

  /** Add dropped or picked files to the preview list. */
  function addFiles(incoming: FileList | File[]) {
    const next = Array.from(incoming).filter(isAcceptedFile)
    setFiles((current) => {
      const names = new Set(current.map((file) => file.name))
      return [...current, ...next.filter((file) => !names.has(file.name))]
    })
  }

  /** Remove one file from the preview list. */
  function removeFile(name: string) {
    setFiles((current) => current.filter((file) => file.name !== name))
  }

  /** Upload files and save order lines. */
  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setIsSaving(true)

    try {
      await computeSoa({
        billingStart,
        billingEnd,
        sellerName,
        storeName,
        files,
      })
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <Card className="w-full">
      <CardHeader className="py-2 mb-2">
        <CardTitle>Statement of Account</CardTitle>
      </CardHeader>
      <CardContent className="pb-3">
        <form className="space-y-3" onSubmit={handleSubmit}>
          <fieldset className="space-y-2">
            <legend className="text-sm font-medium leading-none">
              Billing Period
            </legend>
            <DateRangePickerInputs
              dateFrom={billingStart}
              dateTo={billingEnd}
              onDateFromChange={setBillingStart}
              onDateToChange={setBillingEnd}
            />
          </fieldset>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="seller-name">Full Name</Label>
              <Input
                id="seller-name"
                value={sellerName}
                onChange={(event) => setSellerName(event.target.value)}
                placeholder="Seller full name"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="store-name">Store Name</Label>
              <Input
                id="store-name"
                value={storeName}
                onChange={(event) => setStoreName(event.target.value)}
                placeholder="Store name"
              />
            </div>
          </div>

          <div className="space-y-2">
            <Label>Order Files</Label>
            <label
              htmlFor="order-files"
              className={cn(
                'flex h-36 cursor-pointer items-center rounded-md border border-dashed border-input p-3 transition-colors',
                isDragging
                  ? 'border-foreground/40 bg-muted/50'
                  : 'bg-transparent',
              )}
              onDragOver={(event) => {
                event.preventDefault()
                setIsDragging(true)
              }}
              onDragLeave={() => setIsDragging(false)}
              onDrop={(event) => {
                event.preventDefault()
                setIsDragging(false)
                addFiles(event.dataTransfer.files)
              }}
            >
              <input
                id="order-files"
                type="file"
                accept={ACCEPTED_TYPES}
                multiple
                className="sr-only"
                onChange={(event) => {
                  if (event.target.files) addFiles(event.target.files)
                  event.target.value = ''
                }}
              />

              {files.length === 0 ? (
                <span className="flex h-full w-full flex-col items-center justify-center text-center">
                  <p className="text-sm font-medium text-muted-foreground">
                    Drop order files here
                  </p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    or click to choose file
                  </p>
                </span>
              ) : (
                <ul className="flex h-full items-center gap-3">
                  {files.map((file) => (
                    <li
                      key={file.name}
                      className="group relative h-full w-36 rounded-md border border-border bg-card p-3"
                    >
                      <button
                        type="button"
                        className="absolute top-1 right-1 hidden h-5 w-5 items-center justify-center rounded-sm text-muted-foreground hover:bg-muted hover:text-foreground group-hover:flex"
                        aria-label={`Remove ${file.name}`}
                        onClick={(event) => {
                          event.preventDefault()
                          event.stopPropagation()
                          removeFile(file.name)
                        }}
                      >
                        <span className="text-sm leading-none">×</span>
                      </button>
                      <div className="flex h-full flex-col items-center justify-center gap-2">
                        <FileTypeLogo name={file.name} />
                        <p className="w-full truncate text-center text-xs text-foreground">
                          {file.name}
                        </p>
                        <p className="text-[10px] text-muted-foreground">
                          {formatFileSize(file.size)}
                        </p>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </label>
          </div>

          <div className="flex justify-end">
            <Button type="submit" variant="dark" disabled={isSaving}>
              {isSaving ? 'Saving…' : 'Compute'}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
