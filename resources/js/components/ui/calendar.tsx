import * as React from "react";
import { cn } from "@/lib/utils";

export interface CalendarProps {
  mode?: "single";
  selected?: Date;
  onSelect?: (date: Date | undefined) => void;
  className?: string;
}

export function Calendar({ selected, onSelect, className }: CalendarProps) {
  const [val, setVal] = React.useState(
    selected ? selected.toISOString().slice(0, 10) : new Date().toISOString().slice(0, 10)
  );

  return (
    <div className={cn("p-3 bg-card border rounded-md shadow-sm", className)}>
      <label className="block text-xs font-semibold mb-1 text-muted-foreground">Pilih Tanggal Eskalasi:</label>
      <input
        type="date"
        value={val}
        onChange={(e) => {
          setVal(e.target.value);
          if (onSelect) {
            onSelect(e.target.value ? new Date(e.target.value) : undefined);
          }
        }}
        className="w-full px-3 py-1.5 text-xs border rounded-md bg-background focus:ring-2 focus:ring-primary outline-none"
      />
    </div>
  );
}
