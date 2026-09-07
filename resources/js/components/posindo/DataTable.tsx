import { useState } from "react";
import { router } from "@inertiajs/react";
import { motion, AnimatePresence } from "framer-motion";
import {
  Copy,
  ExternalLink,
  ChevronDown,
  StickyNote,
  AlertTriangle,
  Check,
  MessageSquare,
  Building2,
  Phone,
  MapPin,
  CheckCircle2,
  ArrowUpDown,
  RefreshCw,
  Bot,
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
import {
  FU_META,
  FU_ORDER,
  getSellerFuMeta,
  getSellerFuOrder,
  formatDate,
  extractCityRegency,
  type FuStatus,
  type Shipment,
} from "@/lib/posindo";

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
  onOpenWhatsApp?: (shipment: Shipment) => void;
  seller?: string;
  onSort?: (field: string) => void;
  sortField?: string;
  sortDirection?: "asc" | "desc";
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
  onOpenWhatsApp,
  seller,
  onSort,
  sortField,
  sortDirection,
}: Props) {
  const fuMeta = getSellerFuMeta(seller);
  const fuOrder = getSellerFuOrder(seller);
  const [noteFor, setNoteFor] = useState<string | null>(null);
  const [noteDraft, setNoteDraft] = useState("");
  const [escalateFor, setEscalateFor] = useState<string | null>(null);
  const [escDate, setEscDate] = useState<Date | undefined>(new Date());
  const [loadingTrackId, setLoadingTrackId] = useState<string | null>(null);

  const handleSingleTrack = async (id: string) => {
    try {
      setLoadingTrackId(id);
      const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
        (document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)![1]) : "");

      const res = await fetch(`/shipments/${id}/track`, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-XSRF-TOKEN": csrfToken,
        },
      });
      const data = await res.json();
      if (data.success) {
        toast.success("Status NIPOS berhasil diperbarui!", {
          description: data.shipment?.status_pos || "Data terbaru berhasil diambil.",
        });
        router.reload({ preserveScroll: true });
      } else {
        toast.error(data.message || "Gagal memperbarui status NIPOS");
      }
    } catch (err: any) {
      toast.error("Terjadi kesalahan saat melacak resi: " + (err?.message || err));
    } finally {
      setLoadingTrackId(null);
    }
  };

  const copy = (resi: string) => {
    void navigator.clipboard?.writeText(resi);
    toast.success("No. Resi disalin", { description: resi });
  };

  return (
    <>
      <div className="pos-scroll overflow-x-auto rounded-xl border border-border bg-card">
        <table className="w-full min-w-[1550px] border-collapse text-[13px]">
          <thead className="sticky top-0 z-10 bg-[#1E40AF] text-white">
            <tr className="[&>th]:px-3 [&>th]:py-2.5 [&>th]:text-left [&>th]:font-semibold [&>th]:tracking-wide">
              <th className="w-10 whitespace-nowrap">
                <Checkbox
                  checked={allSelected}
                  onCheckedChange={onToggleAll}
                  className="border-white/60 data-[state=checked]:bg-[#F97316] data-[state=checked]:border-[#F97316]"
                />
              </th>
              <th className="w-12 whitespace-nowrap">No</th>
              <th className="w-32 whitespace-nowrap">Seller / Mitra</th>
              <th className="w-44 whitespace-nowrap">
                <button
                  type="button"
                  onClick={() => onSort?.("resi")}
                  className="inline-flex items-center gap-1 hover:text-amber-200 transition cursor-pointer font-bold"
                  title="Klik untuk mengurutkan berdasarkan nomor resi"
                >
                  <span>No. Resi</span>
                  <ArrowUpDown className="h-3 w-3 opacity-80" />
                </button>
              </th>
              <th className="w-28 whitespace-nowrap">
                <button
                  type="button"
                  onClick={() => onSort?.("tanggal")}
                  className="inline-flex items-center gap-1 hover:text-amber-200 transition cursor-pointer font-bold"
                  title="Klik untuk mengurutkan berdasarkan tanggal kirim"
                >
                  <span>Tgl Kirim</span>
                  <ArrowUpDown className="h-3 w-3 opacity-80" />
                </button>
              </th>
              <th className="min-w-[200px] max-w-[260px] whitespace-normal">Tujuan Kirim &amp; KC</th>
              <th className="min-w-[250px] max-w-[320px] whitespace-normal">
                Penerima &amp; Keterangan (K)
              </th>
              <th className="w-36 whitespace-nowrap">Status NIPOS (L)</th>
              <th className="w-20 whitespace-nowrap">SLA (M)</th>
              <th className="min-w-[180px] whitespace-nowrap">Status Follow-Up CS</th>
            </tr>
          </thead>
          <tbody>
            <AnimatePresence initial={false}>
              {(Array.isArray(rows) ? rows : []).map((row, i) => {
                if (!row) return null;
                const meta = fuMeta[row.fu] || fuMeta["PUTIH"];
                const dark = row.fu === "BIRU_TUA";
                const isChecked = Boolean(selected && typeof selected.has === "function" && selected.has(row.id));
                const niposUpper = (row.nipos || "").toUpperCase();
                const isRetur = row.fu === "ORANGE" || niposUpper.includes("RETURN") || niposUpper === "DELIVERED (RETURN DELIVERY)";
                const isDelivered = !isRetur && (row.fu === "BIRU" || niposUpper === "DELIVERED");
                const isFinal = isDelivered || isRetur;
                const slaNum = typeof row.sla === "number" ? row.sla : parseInt(String(row.sla || "0"), 10);
                const isOverdue = isNaN(slaNum) ? false : (slaNum < 0 || Math.abs(slaNum) > 4);

                return (
                  <motion.tr
                    key={row.id || `row-${i}`}
                    layout
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.15 }}
                    style={{ backgroundColor: meta.bg, color: dark ? "#FFFFFF" : "#111827" }}
                    className="border-b border-border/70 align-top transition-colors duration-200"
                  >
                    <td className="px-3 py-2">
                      <Checkbox
                        checked={isChecked}
                        onCheckedChange={() => row.id && onToggle(row.id)}
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
                        {(() => {
                          const encryptedResi = encodeURIComponent(btoa(row.resi));
                          const detailUrl = `https://pid.posindonesia.co.id/lacak/admin/detail_lacak_banyak.php?id=${encryptedResi}`;
                          return (
                            <>
                              <a
                                href={detailUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-mono text-xs font-bold underline-offset-2 hover:underline text-[#1E40AF]"
                                title="Buka Detail Lacak NIPOS"
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
                              <a
                                href={detailUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="rounded p-0.5 opacity-60 transition hover:opacity-100 cursor-pointer text-[#1E40AF]"
                                title="Buka Detail Lacak NIPOS di Tab Baru"
                              >
                                <ExternalLink className="h-3.5 w-3.5" />
                              </a>
                            </>
                          );
                        })()}
                      </div>
                    </td>
                    <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                      {formatDate(row.tanggalKirim)}
                    </td>
                    <td className="px-3 py-2 min-w-[200px] max-w-[260px] whitespace-normal">
                      {(() => {
                        const cityRegency = extractCityRegency(row.alamat || row.tujuan, row.kantorTujuan);
                        const cleanCityName = cityRegency.replace(/^(Kota|Kab\.?|Kec\.?)\s+/i, "").trim();
                        const rawKC = row.kantorTujuan ? row.kantorTujuan.trim() : "";
                        const isGenericKC = !rawKC || ['KC TUJUAN', 'KC', 'KC POS PENGANTARAN', 'KC PENGANTARAN', 'POS PENGANTARAN'].includes(rawKC.toUpperCase());
                        const displayKC = isGenericKC
                          ? (cleanCityName && cleanCityName !== "-" ? `KC ${cleanCityName.toUpperCase()}` : "KC PENGANTARAN")
                          : rawKC;

                        return (
                          <div className="space-y-1.5">
                            {/* 1. Kota / Kabupaten Tujuan */}
                            <div className="flex items-center gap-1.5">
                              <MapPin className="h-3.5 w-3.5 shrink-0 text-rose-500" />
                              <span className="font-bold text-xs leading-snug break-words" title={row.alamat || row.tujuan}>
                                {cityRegency}
                              </span>
                            </div>

                            {/* 2. Tulisan KC di bawahnya */}
                            <div className="flex items-center gap-1 text-[11px] font-semibold" style={{ color: dark ? "#FFFFFF" : "#1E40AF" }}>
                              <Building2 className="h-3 w-3 shrink-0 text-blue-600" />
                              <span className="truncate" title={displayKC}>
                                {displayKC}
                              </span>
                            </div>

                            {/* 3. Tombol Chat KC jika non-final, atau status Paket Sukses/Retur jika final */}
                            {isFinal ? (
                              <div className="text-[10px] font-bold">
                                {isDelivered ? (
                                  <span className="inline-flex items-center gap-1 text-emerald-900 bg-emerald-100/90 rounded px-1.5 py-0.5 border border-emerald-300">
                                    ✓ Paket Sukses (Final)
                                  </span>
                                ) : (
                                  <span className="inline-flex items-center gap-1 text-amber-950 bg-amber-100/90 rounded px-1.5 py-0.5 border border-amber-300">
                                    ✓ Paket Retur (Final)
                                  </span>
                                )}
                              </div>
                            ) : (
                              <div className="flex items-center gap-1.5 flex-wrap pt-0.5">
                                <button
                                  type="button"
                                  onClick={() => onOpenWhatsApp?.(row)}
                                  className="inline-flex items-center gap-1 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-0.5 text-[11px] font-bold shadow-sm transition cursor-pointer"
                                  title="Buka Chat Follow-Up WhatsApp ke KC"
                                >
                                  <MessageSquare className="h-3 w-3" />
                                  <span>Chat KC</span>
                                </button>
                                {row.kantorPosPhone && (
                                  <span className="inline-flex items-center gap-0.5 text-[10px] font-mono opacity-80" title={`PIC: ${row.kantorPosPic || "-"}`}>
                                    <Phone className="h-2.5 w-2.5 text-emerald-600" />
                                    {row.kantorPosPhone.substring(0, 11)}...
                                  </span>
                                )}
                              </div>
                            )}
                          </div>
                        );
                      })()}
                    </td>
                    <td className="px-3 py-2 min-w-[260px] max-w-[320px] whitespace-normal break-words">
                      {(() => {
                        const rawPenerima = (row.penerima || "").trim();
                        const isResiValue = !rawPenerima || rawPenerima === "-" || rawPenerima === row.resi || /^(BAC\d|P26\d|P\d{7})/i.test(rawPenerima);
                        const displayPenerima = isResiValue ? "Nama Penerima" : rawPenerima;
                        return (
                          <div className="font-semibold text-sm leading-tight text-slate-900" style={{ color: dark ? "#FFFFFF" : undefined }}>
                            {displayPenerima}
                          </div>
                        );
                      })()}
                      <div className="text-[11px] opacity-75 break-words line-clamp-3 leading-snug">
                        {row.telepon} &middot; {row.alamat}
                      </div>
                      <div className="text-[11px] italic opacity-70 break-words line-clamp-2 leading-snug">{row.keterangan}</div>
                      {row.note && (
                        <div className="mt-1 inline-flex items-center gap-1 rounded bg-[#E5E7EB] px-1.5 py-0.5 text-[11px] font-medium text-[#374151]">
                          <StickyNote className="h-3 w-3" /> {row.note}
                        </div>
                      )}
                      {row.escalationDate && (
                        <div className="mt-1 inline-flex items-center gap-1 rounded bg-blue-100/90 text-blue-900 border border-blue-300 px-1.5 py-0.5 text-[10px] font-bold">
                          Eskalasi Pos Pusat: {formatDate(row.escalationDate)}
                        </div>
                      )}
                      {row.lastTrackedAt && (
                        <div className="mt-1 flex items-center gap-1 text-[10px] font-medium text-slate-500" title={`Terakhir diverifikasi oleh Bot NIPOS: ${row.lastTrackedAt}`}>
                          <Bot className="h-3 w-3 text-blue-600 shrink-0" />
                          <span>NIPOS: {row.lastTrackedAt}</span>
                        </div>
                      )}
                    </td>
                    <td className="px-3 py-2 min-w-[220px] max-w-[340px] whitespace-normal">
                      <div className="flex items-start gap-1.5">
                        <div
                          className={`flex-1 rounded-md border p-1.5 text-[11px] font-semibold leading-snug break-words ${
                            isRetur
                              ? "bg-amber-50 text-amber-950 border-amber-300"
                              : isDelivered
                              ? "bg-emerald-50 text-emerald-950 border-emerald-300"
                              : "bg-slate-50 text-slate-800 border-slate-200"
                          }`}
                          title={row.nipos}
                        >
                          {row.nipos || "-"}
                        </div>
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            handleSingleTrack(row.id);
                          }}
                          disabled={loadingTrackId === row.id}
                          title="Lacak NIPOS Sekarang"
                          className="shrink-0 p-1 mt-0.5 rounded border border-slate-200 bg-white hover:bg-blue-50 text-slate-500 hover:text-blue-600 transition-colors disabled:opacity-50 cursor-pointer shadow-xs"
                        >
                          <RefreshCw className={`h-3 w-3 ${loadingTrackId === row.id ? "animate-spin text-blue-600" : ""}`} />
                        </button>
                      </div>
                    </td>
                    <td className="px-3 py-2 whitespace-nowrap">
                      <span
                        className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-bold ${
                          isOverdue ? "bg-red-100 text-red-700 border border-red-300" : "bg-black/5"
                        } ${dark && !isOverdue ? "bg-white/15 text-white" : ""}`}
                      >
                        {isOverdue && <AlertTriangle className="h-3.5 w-3.5 text-red-600 shrink-0" />}
                        {isOverdue
                          ? `Over SLA ${Math.abs(slaNum)} Hari`
                          : `${Math.abs(slaNum)} Hari`}
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
                            {fuOrder.map((k) => (
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
                                  style={{ backgroundColor: fuMeta[k].bg }}
                                />
                                <span className="font-bold text-slate-800">
                                  {fuMeta[k].short} — {fuMeta[k].label}
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
            {(!rows || !Array.isArray(rows) || rows.length === 0) && (
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
        <DialogContent className="max-w-md bg-white border border-slate-200 shadow-2xl rounded-2xl p-6 text-slate-900">
          <DialogHeader>
            <DialogTitle className="text-lg font-bold text-slate-900">Tanggal Eskalasi ke Pos Pusat</DialogTitle>
          </DialogHeader>
          <div className="flex flex-col gap-2.5 py-3">
            <label className="text-xs font-semibold text-slate-700">Pilih Tanggal Eskalasi:</label>
            <Input
              type="date"
              value={escDate ? (escDate instanceof Date ? escDate.toISOString().slice(0, 10) : String(escDate).slice(0, 10)) : new Date().toISOString().slice(0, 10)}
              onChange={(e) => setEscDate(e.target.value ? new Date(e.target.value) : new Date())}
              className="bg-white border border-slate-300 text-slate-900 font-semibold text-sm h-10 px-3 rounded-lg shadow-sm focus:ring-2 focus:ring-[#1E40AF]"
            />
          </div>
          <DialogFooter className="flex flex-row justify-end gap-2 mt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setEscalateFor(null)}
              className="cursor-pointer border-slate-300 text-slate-700 hover:bg-slate-100 font-semibold px-4"
            >
              Batal
            </Button>
            <Button
              type="button"
              className="cursor-pointer bg-[#1E40AF] text-white hover:bg-blue-900 font-bold px-5"
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
