import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import {
  Copy,
  ExternalLink,
  ArrowRight,
  ChevronDown,
  StickyNote,
  AlertTriangle,
  Check,
} from "lucide-react";
import { toast } from "sonner";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Calendar } from "@/components/ui/calendar";
import { FU_META, FU_ORDER, formatDate, type FuStatus, type Shipment } from "@/lib/posindo";

const NIPOS_STYLE: Record<string, string> = {
  DELIVERED: "bg-sky-100 text-sky-800 border-sky-200",
  RETURN: "bg-orange-100 text-orange-800 border-orange-200",
  "IN LOCATION": "bg-violet-100 text-violet-800 border-violet-200",
  RUNSHEET: "bg-slate-100 text-slate-700 border-slate-200",
  "OUT FOR DELIVERY": "bg-emerald-100 text-emerald-800 border-emerald-200",
};

interface Props {
  rows: Shipment[];
  startIndex: number;
  selected: Set<string>;
  allSelected: boolean;
  onToggle: (id: string) => void;
  onToggleAll: () => void;
  onStatus: (ids: string[], fu: FuStatus, escalationDate?: string) => void;
  onNote: (id: string, note: string) => void;
}

export function DataTable({
  rows,
  startIndex,
  selected,
  allSelected,
  onToggle,
  onToggleAll,
  onStatus,
  onNote,
}: Props) {
  const [noteFor, setNoteFor] = useState<string | null>(null);
  const [noteDraft, setNoteDraft] = useState("");
  const [escalateFor, setEscalateFor] = useState<string | null>(null);
  const [escDate, setEscDate] = useState<Date | undefined>(new Date());

  const copy = (resi: string) => {
    void navigator.clipboard?.writeText(resi);
    toast.success("No. Resi disalin", { description: resi });
  };

  return (
    <>
      <div className="pos-scroll overflow-x-auto rounded-xl border border-border bg-card">
        <table className="w-full min-w-[1400px] border-collapse text-[13px]">
          <thead className="sticky top-0 z-10 bg-[#1E40AF] text-white">
            <tr className="[&>th]:whitespace-nowrap [&>th]:px-3 [&>th]:py-2.5 [&>th]:text-left [&>th]:font-semibold [&>th]:tracking-wide">
              <th className="w-10">
                <Checkbox
                  checked={allSelected}
                  onCheckedChange={onToggleAll}
                  className="border-white/60 data-[state=checked]:bg-[#F97316] data-[state=checked]:border-[#F97316]"
                />
              </th>
              <th className="w-12">No</th>
              <th>Seller / Mitra</th>
              <th>No. Resi</th>
              <th>Tgl Kirim</th>
              <th>Asal &amp; Tujuan</th>
              <th className="min-w-[260px]">Penerima &amp; Keterangan (K)</th>
              <th>Status NIPOS (L)</th>
              <th>SLA (M)</th>
              <th className="min-w-[190px]">Status Follow-Up CS</th>
            </tr>
          </thead>
          <tbody>
            <AnimatePresence initial={false}>
              {rows.map((row, i) => {
                const meta = FU_META[row.fu] || FU_META["PUTIH"];
                const dark = row.fu === "BIRU_TUA";
                const overdue = row.sla > 3 && row.nipos !== "DELIVERED";
                return (
                  <motion.tr
                    key={row.id}
                    layout
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.15 }}
                    style={{ backgroundColor: meta.bg, color: dark ? "#FFFFFF" : "#111827" }}
                    className="border-b border-border/70 align-top"
                  >
                    <td className="px-3 py-2">
                      <Checkbox
                        checked={selected.has(row.id)}
                        onCheckedChange={() => onToggle(row.id)}
                      />
                    </td>
                    <td className="px-3 py-2 tabular-nums opacity-70">{startIndex + i + 1}</td>
                    <td className="px-3 py-2">
                      <span
                        className="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold bg-white/50"
                        style={{
                          borderColor: dark ? "rgba(255,255,255,.4)" : "rgba(30,64,175,.25)",
                          color: dark ? "#FFFFFF" : "#1E40AF",
                        }}
                      >
                        {row.seller}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-1">
                        <a
                          href={`https://nipos.posindonesia.co.id/track/${row.resi}`}
                          target="_blank"
                          rel="noreferrer"
                          className="font-mono text-xs font-bold underline-offset-2 hover:underline"
                        >
                          {row.resi}
                        </a>
                        <button
                          onClick={() => copy(row.resi)}
                          className="rounded p-1 opacity-60 transition hover:opacity-100 cursor-pointer"
                          aria-label="Salin resi"
                        >
                          <Copy className="h-3.5 w-3.5" />
                        </button>
                        <ExternalLink className="h-3 w-3 opacity-40" />
                      </div>
                    </td>
                    <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                      {formatDate(row.tanggalKirim)}
                    </td>
                    <td className="px-3 py-2 whitespace-nowrap">
                      <span className="inline-flex items-center gap-1.5 text-xs font-medium">
                        Cilacap <ArrowRight className="h-3 w-3 opacity-60" /> {row.tujuan}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <div className="font-semibold">{row.penerima}</div>
                      <div className="text-[11px] opacity-75">
                        {row.telepon} &middot; {row.alamat}
                      </div>
                      <div className="text-[11px] italic opacity-70">{row.keterangan}</div>
                      {row.note && (
                        <div className="mt-1 inline-flex items-center gap-1 rounded bg-[#E5E7EB] px-1.5 py-0.5 text-[11px] font-medium text-[#374151]">
                          <StickyNote className="h-3 w-3" /> {row.note}
                        </div>
                      )}
                      {row.escalationDate && (
                        <div className="mt-1 text-[11px] font-semibold">
                          Eskalasi Pos Pusat: {formatDate(row.escalationDate)}
                        </div>
                      )}
                    </td>
                    <td className="px-3 py-2">
                      <span
                        className={`inline-flex rounded border px-2 py-0.5 text-[11px] font-bold ${NIPOS_STYLE[row.nipos] || "bg-slate-100 text-slate-700"}`}
                      >
                        {row.nipos}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <span
                        className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-bold ${
                          overdue ? "bg-red-100 text-red-700 border border-red-300" : "bg-black/5"
                        } ${dark && !overdue ? "bg-white/15 text-white" : ""}`}
                      >
                        {overdue && <AlertTriangle className="h-3.5 w-3.5 text-red-600" />}
                        {row.sla} Hari
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-1">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <button
                              className="inline-flex items-center gap-1.5 rounded-md border border-black/10 px-2.5 py-1 text-[11px] font-bold shadow-sm transition hover:brightness-95 cursor-pointer"
                              style={{ backgroundColor: meta.bg, color: meta.fg }}
                            >
                              {meta.label}
                              <ChevronDown className="h-3 w-3" />
                            </button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" className="w-64 p-1.5 bg-white border border-slate-200 shadow-xl rounded-xl">
                            {FU_ORDER.map((k) => (
                              <DropdownMenuItem
                                key={k}
                                onClick={() => {
                                  if (k === "BIRU_TUA") {
                                    setEscalateFor(row.id);
                                    setEscDate(new Date());
                                  } else onStatus([row.id], k);
                                }}
                                className="gap-2.5 px-3 py-2 text-xs font-semibold rounded-lg hover:bg-slate-50 cursor-pointer flex items-center"
                              >
                                <span
                                  className="h-4 w-4 rounded-md border border-black/10 shrink-0"
                                  style={{ backgroundColor: FU_META[k].bg }}
                                />
                                <span className="font-bold text-slate-800">
                                  {FU_META[k].short} — {FU_META[k].label}
                                </span>
                                {row.fu === k && <Check className="ml-auto h-4 w-4 text-slate-900" />}
                              </DropdownMenuItem>
                            ))}
                            <DropdownMenuSeparator className="my-1.5 bg-slate-200" />
                            <DropdownMenuItem
                              className="gap-2.5 px-3 py-2 text-xs font-semibold rounded-lg hover:bg-slate-50 cursor-pointer flex items-center text-slate-800"
                              onClick={() => {
                                setNoteFor(row.id);
                                setNoteDraft(row.note ?? "");
                              }}
                            >
                              <StickyNote className="h-4 w-4 text-slate-600 shrink-0" />
                              <span className="font-bold">NOTED — Catatan khusus</span>
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                      {noteFor === row.id && (
                        <div className="mt-1.5 flex items-center gap-1">
                          <Input
                            autoFocus
                            value={noteDraft}
                            onChange={(e) => setNoteDraft(e.target.value)}
                            placeholder="Catatan khusus..."
                            className="h-7 bg-background text-xs"
                            onKeyDown={(e) => {
                              if (e.key === "Enter") {
                                onNote(row.id, noteDraft);
                                setNoteFor(null);
                              }
                            }}
                          />
                          <Button
                            size="sm"
                            className="h-7 px-2 text-[11px]"
                            onClick={() => {
                              onNote(row.id, noteDraft);
                              setNoteFor(null);
                            }}
                          >
                            OK
                          </Button>
                        </div>
                      )}
                    </td>
                  </motion.tr>
                );
              })}
            </AnimatePresence>
            {rows.length === 0 && (
              <tr>
                <td colSpan={10} className="px-4 py-16 text-center text-sm text-muted-foreground">
                  Tidak ada resi yang cocok dengan filter saat ini.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <Dialog open={!!escalateFor} onOpenChange={(o) => !o && setEscalateFor(null)}>
        <DialogContent className="max-w-fit">
          <DialogHeader>
            <DialogTitle>Tanggal Eskalasi ke Pos Pusat</DialogTitle>
          </DialogHeader>
          <Calendar mode="single" selected={escDate} onSelect={setEscDate} className="rounded-md" />
          <DialogFooter>
            <Button
              onClick={() => {
                if (escalateFor)
                  onStatus(
                    [escalateFor],
                    "BIRU_TUA",
                    (escDate ?? new Date()).toISOString().slice(0, 10),
                  );
                setEscalateFor(null);
                toast.success("Status FU POS tersimpan");
              }}
            >
              Simpan Eskalasi
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
