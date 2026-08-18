import { DatePickerInput } from '@/components/ui/DatePickerInput'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'

interface DateRangePickerInputsProps {
  dateFrom: string
  dateTo: string
  onDateFromChange: (value: string) => void
  onDateToChange: (value: string) => void
  className?: string
}

/** Grouped start/end date inputs. */
export function DateRangePickerInputs({
  dateFrom,
  dateTo,
  onDateFromChange,
  onDateToChange,
  className,
}: DateRangePickerInputsProps) {
  return (
    <div className={cn('grid grid-cols-1 gap-3 sm:grid-cols-2', className)}>
      <div className="space-y-1.5">
        <Label htmlFor="billing-start">Start</Label>
        <DatePickerInput
          id="billing-start"
          aria-label="Billing start"
          value={dateFrom}
          max={dateTo || undefined}
          onChange={(event) => onDateFromChange(event.target.value)}
        />
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="billing-end">End</Label>
        <DatePickerInput
          id="billing-end"
          aria-label="Billing end"
          value={dateTo}
          min={dateFrom || undefined}
          onChange={(event) => onDateToChange(event.target.value)}
        />
      </div>
    </div>
  )
}
