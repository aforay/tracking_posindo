import { useState, useEffect, useRef } from "react";
import { router } from "@inertiajs/react";
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
  History,
  Loader2,
  UploadCloud,
  Send,
  User,
} from "lucide-react";
import { toast } from "sonner";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ShipmentTimelineModal } from "./ShipmentTimelineModal";
import { ShipmentLogsModal } from "./ShipmentLogsModal";
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
  resolveDestinationOffice,
  isAddressText,
  generatePostOfficeWaMessage,
  type FuStatus,
  type Shipment,
  type PostOffice,
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
  onOpenWhatsApp?: (shipment: Shipment, target?: "POST_OFFICE" | "CUSTOMER") => void;
  seller?: string;
  onSort?: (field: string) => void;
  sortField?: string;
  sortDirection?: "asc" | "desc";
  postOffices?: PostOffice[];
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
  postOffices = [],
}: Props) {
  const fuMeta = getSellerFuMeta(seller);
  const fuOrder = getSellerFuOrder(seller);
  const [noteFor, setNoteFor] = useState<string | null>(null);
  const [noteDraft, setNoteDraft] = useState("");
  const [escalateFor, setEscalateFor] = useState<string | null>(null);
  const [escDate, setEscDate] = useState<Date | undefined>(new Date());
  const [loadingTrackId, setLoadingTrackId] = useState<string | null>(null);
  const [timelineShipment, setTimelineShipment] = useState<Shipment | null>(null);
  const [logShipment, setLogShipment] = useState<Shipment | null>(null);
  const [resolvedOffices, setResolvedOffices] = useState<Record<string, string>>({});
  const resolvingRef = useRef<Set<string>>(new Set());

  // Automatically resolve missing KC / KCU from NIPOS in the background for visible rows without blocking UI
  useEffect(() => {
    const generic = ['KC TUJUAN', 'KC POS TUJUAN', 'KANTOR POS TUJUAN', 'KC POS PENGANTARAN', 'KC PENGANTARAN', 'POS PENGANTARAN', 'KC POS INDONESIA', 'POS INDONESIA', 'SEDANG MEMBACA NIPOS...', 'SEDANG MEMBACA NIPOS'];
    const missingResis = rows
      .filter((r) => {
        const existing = resolvedOffices[r.resi] || resolvedOffices[r.id] || r.kantorTujuan;
        return (
          r.resi &&
          (!existing ||
           generic.includes(existing.trim().toUpperCase()) ||
           isAddressText(existing) ||
           existing.toUpperCase().includes('KCP') ||
           /\bDC\b/i.test(existing) ||
           /\b\d{5}B\d\b/i.test(existing) ||
           /^KC\s+(?:HINAI|SECANGGANG|KALIORANG|MUARASABAK)/i.test(existing)) &&
          !resolvingRef.current.has(r.resi)
        );
      })
      .map((r) => r.resi)
      .slice(0, 40);

    if (missingResis.length === 0) return;

    missingResis.forEach((r) => resolvingRef.current.add(r));

    const csrfToken =
      document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
      (document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
        ? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)![1])
        : "");

    fetch('/shipments/resolve-kantor', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-XSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify({ resis: missingResis }),
    })
      .then((res) => res.json())
      .then((res) => {
        if (res.success && res.offices) {
          setResolvedOffices((prev) => ({ ...prev, ...res.offices }));
        }
      })
      .catch((err) => {
        console.warn('Failed to resolve missing KC from NIPOS:', err);
      });
  }, [rows]);

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

  const [pushingResiId, setPushingResiId] = useState<string | null>(null);

  const handleSinglePush = async (row: Shipment) => {
    try {
      setPushingResiId(row.id);
      const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
        (document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)![1]) : "");

      const res = await fetch(`/shipments/${row.id}/push-sheet`, {
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
        toast.success(`Resi ${row.resi} berhasil di-push ke Google Sheets!`, {
          description: "Status dan warna di spreadsheet berhasil diperbarui secara instan.",
        });
      } else {
        toast.error(data.message || "Gagal push resi ke Google Sheets");
      }
    } catch (err: any) {
      toast.error("Terjadi kesalahan saat push resi: " + (err?.message || err));
    } finally {
      setPushingResiId(null);
    }
  };

  const copy = (resi: string) => {
    void navigator.clipboard?.writeText(resi);
    toast.success("No. Resi disalin", { description: resi });
  };

  return (
    <>
      <div className="pos-scroll overflow-x-auto rounded-xl border border-border bg-card shadow-2xs">
        <table className="w-full min-w-[1080px] border-collapse text-xs">
          <thead className="sticky top-0 z-10 bg-[#1E40AF] text-white">
            <tr className="[&>th]:px-2 [&>th]:py-2 [&>th]:text-left [&>th]:font-semibold [&>th]:tracking-wide text-xs">
              <th className="w-8 min-w-[32px] text-center whitespace-nowrap">
                <Checkbox
                  checked={allSelected}
                  onCheckedChange={onToggleAll}
                  className="border-white/60 data-[state=checked]:bg-[#F97316] data-[state=checked]:border-[#F97316]"
                />
              </th>
              <th className="w-9 min-w-[36px] text-center whitespace-nowrap">No</th>
              <th className="w-28 min-w-[95px] whitespace-nowrap">
                <button
                  type="button"
                  onClick={() => onSort?.("cs")}
                  className="inline-flex items-center gap-1 hover:text-amber-200 transition cursor-pointer font-bold"
                  title="Klik untuk mengurutkan berdasarkan nama CS"
                >
                  <span>CS</span>
                  <ArrowUpDown className="h-3 w-3 opacity-80" />
                </button>
              </th>
              <th className="w-40 min-w-[150px] whitespace-nowrap">
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
              <th className="w-20 min-w-[75px] text-center whitespace-nowrap">
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
              <th className="w-44 min-w-[160px] max-w-[190px] whitespace-normal">Kantor Pos (KC / KCU)</th>
              <th className="min-w-[180px] max-w-[240px] xl:max-w-[380px] 2xl:max-w-[480px] whitespace-normal">
                Penerima &amp; Alamat (K)
              </th>
              <th className="w-36 min-w-[130px] max-w-[170px] whitespace-nowrap">Status NIPOS (L)</th>
              <th className="w-16 min-w-[65px] text-center whitespace-nowrap">SLA (M)</th>
              <th className="w-48 min-w-[170px] whitespace-nowrap sticky right-0 z-20 bg-[#1E40AF] text-white text-center">
                Aksi
              </th>
            </tr>
          </thead>
          <tbody>
            {(Array.isArray(rows) ? rows : []).map((row, i) => {
              if (!row) return null;
              const meta = fuMeta[row.fu] || fuMeta["PUTIH"];
              const isFuPos = row.fu === "BIRU_TUA";
              const dark = !isFuPos && row.fu === "ORANGE";
              const rowBg = isFuPos ? "transparent" : meta.bg;
              const isChecked = Boolean(selected && typeof selected.has === "function" && selected.has(row.id));
              const niposUpper = (row.nipos || "").toUpperCase();
              const isRetur = row.fu === "ORANGE" || niposUpper.includes("RETURN") || niposUpper.includes("RETUR") || row.statusKategori === "RETUR";
              const isDelivered = !isRetur && (
                row.fu === "BIRU" ||
                row.statusKategori === "SUKSES" ||
                (niposUpper.includes("DELIVERED") && !niposUpper.includes("FAILED")) ||
                niposUpper.includes("DITERIMA") ||
                niposUpper.includes("ARRIVEDUNPAID")
              );
              const isFinal = isDelivered || isRetur;
              const slaNum = typeof row.sla === "number" ? row.sla : parseInt(String(row.sla || "0"), 10);
              const isOverdue = !isDelivered && !isNaN(slaNum) && slaNum < 0;

              return (
                <tr
                  key={row.id || `row-${i}`}
                  style={{ backgroundColor: rowBg, color: dark ? "#FFFFFF" : "#111827" }}
                  className={`border-b border-border/70 align-top hover:opacity-95 ${isFuPos ? "bg-blue-50/20" : ""}`}
                >
                  <td className="px-2 py-1.5 text-center">
                    <Checkbox
                      checked={isChecked}
                      onCheckedChange={() => row.id && onToggle(row.id)}
                    />
                  </td>
                  <td className="px-2 py-1.5 text-center tabular-nums text-xs opacity-70">{startIndex + i + 1}</td>
                  <td className="px-2 py-1.5 whitespace-nowrap">
                    {row.namaCs ? (
                      <span
                        className="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold border shadow-2xs backdrop-blur-xs"
                        style={{
                          backgroundColor: dark ? "rgba(255,255,255,0.2)" : "rgba(238,242,255,0.9)",
                          borderColor: dark ? "rgba(255,255,255,0.35)" : "rgba(199,210,254,0.85)",
                          color: dark ? "#FFFFFF" : "#3730A3",
                        }}
                        title={`Customer Service (Spreadsheet): ${row.namaCs}`}
                      >
                        <User className="h-2.5 w-2.5 shrink-0 opacity-80 text-indigo-500" />
                        <span className="truncate max-w-[105px]">{row.namaCs}</span>
                      </span>
                    ) : (
                      <span className="text-[11px] opacity-40 italic">-</span>
                    )}
                  </td>
                  <td className="px-2 py-1.5">
                    <div className="flex flex-col gap-1 min-w-[145px]">
                      {(() => {
                        const encryptedResi = encodeURIComponent(btoa(row.resi));
                        const detailUrl = `https://pid.posindonesia.co.id/lacak/admin/detail_lacak_banyak.php?id=${encryptedResi}`;
                        
                        if (isFuPos) {
                          return (
                            <>
                              {/* Badge FU POS (Clean, sharp, exact styling) */}
                              <div className="inline-flex items-center gap-1 w-fit bg-[#1E40AF] hover:bg-blue-900 text-white px-2 py-0.5 rounded shadow-2xs border border-blue-400/40 transition">
                                <button
                                  type="button"
                                  onClick={() => setTimelineShipment(row)}
                                  className="font-mono text-xs font-bold hover:underline cursor-pointer text-left text-white tracking-tight"
                                  title="Klik untuk melihat Detail Timeline Pelacakan Resi (FU POS / Eskalasi KC)"
                                >
                                  {row.resi}
                                </button>
                                <span className="text-[8.5px] font-black bg-white/20 text-white px-1 py-0.2 rounded leading-tight tracking-wider uppercase">
                                  FU POS
                                </span>
                                <button
                                  type="button"
                                  onClick={() => copy(row.resi)}
                                  className="rounded p-0.5 hover:bg-white/20 transition cursor-pointer text-white/90 hover:text-white"
                                  title="Salin No. Resi"
                                >
                                  <Copy className="h-3 w-3" />
                                </button>
                                <a
                                  href={detailUrl}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="rounded p-0.5 hover:bg-white/20 transition cursor-pointer text-white/90 hover:text-white"
                                  title="Buka Detail Lacak NIPOS di Tab Baru"
                                >
                                  <ExternalLink className="h-3 w-3" />
                                </a>
                              </div>

                              {/* Tombol Push Satuan Real-Time */}
                              <button
                                type="button"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleSinglePush(row);
                                }}
                                disabled={pushingResiId === row.id}
                                className="w-fit inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-600 hover:text-white border border-emerald-300 transition-all cursor-pointer disabled:opacity-50"
                                title="Push resi ini langsung ke Google Sheets (Satuan / Real-Time)"
                              >
                                {pushingResiId === row.id ? (
                                  <>
                                    <Loader2 className="h-2.5 w-2.5 animate-spin text-emerald-600" />
                                    <span>Mengirim...</span>
                                  </>
                                ) : (
                                  <>
                                    <UploadCloud className="h-3 w-3" />
                                    <span>Push Sheets</span>
                                  </>
                                )}
                              </button>
                            </>
                          );
                        }

                        return (
                          <div className="flex items-center gap-1">
                            <button
                              type="button"
                              onClick={() => setTimelineShipment(row)}
                              className={`font-mono text-xs font-bold underline-offset-2 hover:underline cursor-pointer text-left ${dark ? "text-white" : "text-[#1E40AF]"}`}
                              title="Klik untuk melihat Detail Timeline Pelacakan Resi"
                            >
                              {row.resi}
                            </button>
                            <button
                              type="button"
                              onClick={() => copy(row.resi)}
                              className={`rounded p-0.5 opacity-60 transition hover:opacity-100 cursor-pointer ${dark ? "text-white hover:bg-white/20" : "text-slate-600 hover:bg-slate-100"}`}
                              title="Salin no. resi"
                            >
                              <Copy className="h-3 w-3" />
                            </button>
                            <a
                              href={detailUrl}
                              target="_blank"
                              rel="noopener noreferrer"
                              className={`rounded p-0.5 opacity-60 transition hover:opacity-100 cursor-pointer ${dark ? "text-white hover:bg-white/20" : "text-slate-600 hover:text-blue-600 hover:bg-slate-100"}`}
                              title="Buka Detail Lacak NIPOS di Tab Baru"
                            >
                              <ExternalLink className="h-3 w-3" />
                            </a>
                          </div>
                        );
                      })()}
                    </div>
                  </td>
                  <td className="px-1.5 py-1.5 whitespace-nowrap tabular-nums text-center text-xs">
                    {formatDate(row.tanggalKirim)}
                  </td>
                  <td className="px-2 py-1.5 w-44 min-w-[160px] max-w-[190px] whitespace-normal">
                    {(() => {
                      const rawKC = (resolvedOffices[row.resi] || resolvedOffices[row.id] || row.kantorTujuan || "").trim().replace(/\bMPS\b/gi, "SPP");
                      const isGenericKC = !rawKC || ['KC TUJUAN', 'KC', 'KANTOR POS TUJUAN', 'KC POS PENGANTARAN', 'KC PENGANTARAN', 'POS PENGANTARAN', 'KC POS INDONESIA', 'POS INDONESIA', 'SEDANG MEMBACA NIPOS...', 'SEDANG MEMBACA NIPOS'].includes(rawKC.toUpperCase()) || isAddressText(rawKC);
                      const isKcpOrDc = /\bKCP\b/i.test(rawKC) || /\bDC\b/i.test(rawKC) || /\b\d{5}B\d\b/i.test(rawKC) || /^KC\s+(?:KEC\b|KECAMATAN\b|HINAI|SECANGGANG|KALIORANG|MUARASABAK)/i.test(rawKC);

                      let displayKC = "";
                      let isResolving = false;
                      const resolved = resolveDestinationOffice({ ...row, kantorTujuan: isGenericKC ? "" : rawKC });
                      const isBroadHub = /^(PEKANBARU|MEDAN|SEMARANG|SURABAYA|BANDUNG|MAKASSAR|PALEMBANG|PADANG)\b/i.test(rawKC);

                      if (resolved && isBroadHub && resolved !== rawKC && !isAddressText(resolved)) {
                        displayKC = resolved;
                      } else if (!isGenericKC && !isKcpOrDc && !isAddressText(rawKC)) {
                        displayKC = rawKC;
                      } else {
                        if (resolved && !['KC TUJUAN', 'KC POS TUJUAN', 'KANTOR POS TUJUAN', 'KC PENGANTARAN'].includes(resolved.toUpperCase()) && !/\b(KCP|DC)\b/i.test(resolved) && !/^KC\s+(?:KEC\b|KECAMATAN\b)/i.test(resolved) && !isAddressText(resolved)) {
                          displayKC = resolved;
                        } else {
                          displayKC = "Sedang Membaca NIPOS...";
                          isResolving = true;
                        }
                      }

                      const cityRegency = extractCityRegency(row.alamat || "", isKcpOrDc ? "" : displayKC);

                      return (
                        <div className="space-y-1">
                          {/* 1. Nama Kantor Pos Resmi dari NIPOS */}
                          <div className="flex items-start gap-1 font-bold text-xs leading-tight" style={{ color: dark ? "#93C5FD" : "#1E40AF" }}>
                            {isResolving ? (
                              <>
                                <Loader2 className="h-3 w-3 shrink-0 text-blue-500 animate-spin mt-0.5" />
                                <span className="italic text-blue-500 font-medium animate-pulse" title="Mengambil nama KC resmi dari API NIPOS...">
                                  {displayKC}
                                </span>
                              </>
                            ) : (
                              <>
                                <Building2 className="h-3.5 w-3.5 shrink-0 text-blue-600 mt-0.5" />
                                <span className="break-words" title={`Kantor Pos Tujuan (NIPOS): ${displayKC}`}>
                                  {displayKC}
                                </span>
                              </>
                            )}
                          </div>

                          {/* 2. Kota / Wilayah Tujuan */}
                          {cityRegency && cityRegency !== "-" && (!displayKC || !displayKC.toUpperCase().includes(cityRegency.toUpperCase())) ? (
                            <div className="flex items-center gap-1 text-[10.5px] font-medium opacity-80">
                              <MapPin className="h-2.5 w-2.5 shrink-0 text-rose-500" />
                              <span className="truncate" title={`Wilayah Tujuan: ${cityRegency}`}>{cityRegency}</span>
                            </div>
                          ) : null}

                          {/* 3. Tombol Chat KC / WA KC (Hanya tampil untuk paket belum final & kantor valid) */}
                          {(() => {
                            if (isFinal || isResolving || displayKC === "Sedang Membaca NIPOS..." || isAddressText(displayKC)) return null;

                            const matchedOffice = postOffices.find(po => {
                              const poName = (po.name || "").toLowerCase();
                              const poCity = (po.city || "").toLowerCase();
                              const targetName = (displayKC || "").toLowerCase();
                              if (targetName) {
                                if (poName === targetName || poName.includes(targetName) || targetName.includes(poName)) {
                                  return true;
                                }
                                const cleanTarget = targetName.replace(/^(kcu|kc|spp|kantor\s+pos)\s+/i, "").replace(/\s+\d{5}.*$/, "").trim();
                                const cleanPo = poName.replace(/^(kcu|kc|spp|kantor\s+pos)\s+/i, "").replace(/\s+\d{5}.*$/, "").trim();
                                if (cleanTarget && cleanPo && (cleanPo === cleanTarget || cleanPo.includes(cleanTarget) || cleanTarget.includes(cleanPo))) {
                                  return true;
                                }
                              }
                              if (cityRegency && cityRegency !== "-" && poCity) {
                                const cleanCity = cityRegency.replace(/^(kota|kab\.?)\s+/i, "").trim().toLowerCase();
                                if (cleanCity && poCity.includes(cleanCity)) {
                                  return true;
                                }
                              }
                              return false;
                            });

                            let phoneNo = "";
                            let telegramHandle = "";
                            if (matchedOffice) {
                              phoneNo = (matchedOffice.phone_wa && !matchedOffice.phone_wa.startsWith('@')) ? matchedOffice.phone_wa : "";
                              telegramHandle = matchedOffice.telegram_handle || (matchedOffice.phone_wa?.startsWith('@') ? matchedOffice.phone_wa : "");
                            } else {
                              phoneNo = (row.kantorPosPhone && !row.kantorPosPhone.startsWith('@')) ? row.kantorPosPhone : "";
                              telegramHandle = row.kantorPosTelegram || (row.kantorPosPhone?.startsWith('@') ? row.kantorPosPhone : "");
                            }

                            const cleanTg = telegramHandle ? telegramHandle.replace(/^@/, '').trim() : '';
                            const hasTelegram = Boolean(cleanTg);
                            const cleanKcPhone = phoneNo ? phoneNo.replace(/[^0-9]/g, "").replace(/^0/, "62") : "";
                            const hasPhone = Boolean(cleanKcPhone && cleanKcPhone.length >= 8);
                            const directWaText = generatePostOfficeWaMessage(row, displayKC, "", "ANTAR_ULANG");
                            const directWaUrl = hasPhone ? `https://wa.me/${cleanKcPhone}?text=${encodeURIComponent(directWaText)}` : "";

                            return (
                              <div className="flex items-center gap-1.5 flex-wrap pt-0.5">
                                {/* Tombol WhatsApp KC */}
                                <button
                                  type="button"
                                  onClick={() => onOpenWhatsApp?.(row, "POST_OFFICE")}
                                  style={{
                                    backgroundColor: hasPhone ? "#059669" : "#ECFDF5",
                                    color: hasPhone ? "#FFFFFF" : "#065F46",
                                    borderColor: hasPhone ? "#047857" : "#059669",
                                    borderWidth: "1px",
                                    borderStyle: "solid",
                                  }}
                                  className="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10.5px] font-bold shadow-2xs transition cursor-pointer hover:opacity-90"
                                  title={hasPhone ? `Buka Dialog Follow-Up WhatsApp ke KC ${displayKC} (${phoneNo})` : `Follow-up WhatsApp ke KC Pos`}
                                >
                                  <MessageSquare className="h-3 w-3 shrink-0" style={{ color: hasPhone ? "#FFFFFF" : "#059669" }} />
                                  <span style={{ color: hasPhone ? "#FFFFFF" : "#065F46" }}>WA KC</span>
                                </button>

                                {/* Link Langsung Buka Chat WhatsApp */}
                                {hasPhone && (
                                  <a
                                    href={directWaUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style={{ color: "#047857" }}
                                    className="inline-flex items-center gap-0.5 text-[9.5px] font-mono font-bold hover:underline transition"
                                    title={`Klik untuk Langsung Buka Chat WhatsApp Web / App ke ${displayKC} (${phoneNo}) - PIC: ${row.kantorPosPic || matchedOffice?.pic_name || "-"}`}
                                    onClick={(e) => e.stopPropagation()}
                                  >
                                    <Phone className="h-2.5 w-2.5" style={{ color: "#059669" }} />
                                    <span>{phoneNo.substring(0, 11)}...</span>
                                  </a>
                                )}

                                {/* Telegram Button */}
                                {hasTelegram && (
                                  <button
                                    type="button"
                                    onClick={() => onOpenWhatsApp?.(row, "POST_OFFICE")}
                                    style={{
                                      backgroundColor: "#229ED9",
                                      color: "#FFFFFF",
                                      borderColor: "#1E88E5",
                                      borderWidth: "1px",
                                      borderStyle: "solid",
                                    }}
                                    className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10.5px] font-bold shadow-2xs transition cursor-pointer hover:opacity-90"
                                    title={`Buka Chat Telegram (@${cleanTg})`}
                                  >
                                    <Send className="h-3 w-3" style={{ color: "#FFFFFF" }} />
                                    <span>TG</span>
                                  </button>
                                )}

                                {hasTelegram && (
                                  <a
                                    href={`https://t.me/${cleanTg}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style={{ color: "#229ED9" }}
                                    className="inline-flex items-center gap-0.5 text-[9.5px] font-mono hover:underline font-bold"
                                    title={`Buka Telegram @${cleanTg}`}
                                    onClick={(e) => e.stopPropagation()}
                                  >
                                    <Send className="h-2.5 w-2.5" style={{ color: "#229ED9" }} />
                                    @{cleanTg}
                                  </a>
                                )}
                              </div>
                            );
                          })()}
                        </div>
                      );
                    })()}
                  </td>
                  <td className="px-2 py-1.5 min-w-[180px] max-w-[240px] xl:max-w-[380px] 2xl:max-w-[480px] whitespace-normal break-words">
                    {(() => {
                      const rawPenerima = (row.penerima || "").trim();
                      const isResiValue = !rawPenerima || rawPenerima === "-" || rawPenerima === row.resi || /^(BAC\d|P26\d|P\d{7})/i.test(rawPenerima);
                      const displayPenerima = isResiValue ? "Nama Penerima" : rawPenerima;
                      return (
                        <div className="font-semibold text-xs leading-tight text-slate-900" style={{ color: dark ? "#FFFFFF" : undefined }}>
                          {displayPenerima}
                        </div>
                      );
                    })()}
                    <div className="mt-0.5 flex items-center gap-1.5 text-[10.5px]">
                      <span className="font-mono font-semibold opacity-90">{row.telepon || "-"}</span>
                    </div>
                    <div className="text-[10.5px] opacity-75 break-words line-clamp-2 leading-snug mt-0.5">
                      {row.alamat}
                    </div>
                    {row.keterangan && (
                      <div className="text-[10px] italic opacity-70 break-words line-clamp-1 leading-snug">{row.keterangan}</div>
                    )}
                    {row.note && (
                      <div className="mt-0.5 inline-flex items-center gap-1 rounded bg-[#E5E7EB] px-1 py-0.2 text-[10px] font-medium text-[#374151]">
                        <StickyNote className="h-2.5 w-2.5" /> {row.note}
                      </div>
                    )}
                    {row.escalationDate && (
                      <div className="mt-0.5 inline-flex items-center gap-1 rounded bg-blue-100/90 text-blue-900 border border-blue-300 px-1 py-0.2 text-[9.5px] font-bold">
                        Eskalasi: {formatDate(row.escalationDate)}
                      </div>
                    )}
                    {row.lastTrackedAt && (
                      <div className="mt-0.5 flex items-center gap-1 text-[9.5px] font-medium text-slate-500" title={`Terakhir diverifikasi oleh Bot NIPOS: ${row.lastTrackedAt}`}>
                        <Bot className="h-2.5 w-2.5 text-blue-600 shrink-0" />
                        <span>NIPOS: {row.lastTrackedAt}</span>
                      </div>
                    )}
                  </td>
                  <td className="px-2 py-1.5 w-36 min-w-[130px] max-w-[170px] whitespace-normal">
                    <div className="flex items-start gap-1">
                      <div
                        className={`flex-1 rounded p-1 text-[10.5px] font-medium leading-snug break-words ${
                          isRetur
                            ? "bg-amber-50 text-amber-950 border border-amber-300"
                            : isDelivered
                            ? "bg-emerald-50 text-emerald-950 border border-emerald-300"
                            : "bg-slate-50 text-slate-800 border border-slate-200"
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
                        className="shrink-0 p-1 mt-0.5 rounded border border-slate-200 bg-white hover:bg-blue-50 text-slate-500 hover:text-blue-600 transition-colors disabled:opacity-50 cursor-pointer shadow-2xs"
                      >
                        <RefreshCw className={`h-3 w-3 ${loadingTrackId === row.id ? "animate-spin text-blue-600" : ""}`} />
                      </button>
                    </div>
                  </td>
                  <td className="px-1.5 py-1.5 whitespace-nowrap text-center">
                    <span
                      className={`inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10.5px] font-bold ${
                        isOverdue ? "bg-red-100 text-red-700 border border-red-300" : "bg-black/5"
                      } ${dark && !isOverdue ? "bg-white/15 text-white" : ""}`}
                    >
                      {isOverdue && <AlertTriangle className="h-3 w-3 text-red-600 shrink-0" />}
                      {isOverdue
                        ? `Over ${Math.abs(slaNum)}H`
                        : `${Math.abs(slaNum)} Hari`}
                    </span>
                  </td>
                  <td
                    className="px-2 py-1.5 w-48 min-w-[170px] whitespace-nowrap sticky right-0 z-10"
                    style={{ backgroundColor: rowBg, color: dark ? "#FFFFFF" : "#111827" }}
                  >
                    <div className="flex items-center gap-1.5 whitespace-nowrap">
                      {/* Status FU dibuat FIX (Badge statis/read-only, tidak bisa diubah manual) */}
                      <span
                        className="inline-flex items-center px-2 py-0.5 text-[10.5px] font-bold rounded border border-black/10 select-none shadow-2xs cursor-default"
                        style={{ backgroundColor: meta.bg, color: meta.fg }}
                        title={`Status: ${meta.label} (Fix / Otomatis)`}
                      >
                        {meta.label}
                      </span>

                      <button
                        type="button"
                        onClick={() => setLogShipment(row)}
                        className="p-1 rounded border border-black/10 bg-white/70 hover:bg-white text-slate-700 transition cursor-pointer shadow-2xs"
                        title="Lihat Riwayat Log Aktivitas & Catatan CS"
                      >
                        <History className="h-3.5 w-3.5" />
                      </button>
                      <button
                        type="button"
                        onClick={() => handleSinglePush(row)}
                        disabled={pushingResiId === row.id}
                        className="p-1 rounded border border-black/10 bg-white/70 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 transition cursor-pointer shadow-2xs disabled:opacity-50"
                        title="Push Resi ini Langsung ke Google Sheets"
                      >
                        {pushingResiId === row.id ? (
                          <Loader2 className="h-3.5 w-3.5 animate-spin text-emerald-600" />
                        ) : (
                          <UploadCloud className="h-3.5 w-3.5" />
                        )}
                      </button>
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
                </tr>
                );
              })}
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

      <ShipmentTimelineModal
        isOpen={!!timelineShipment}
        onClose={() => setTimelineShipment(null)}
        shipment={timelineShipment}
        onOpenWhatsApp={onOpenWhatsApp}
      />

      <ShipmentLogsModal
        isOpen={!!logShipment}
        onClose={() => setLogShipment(null)}
        shipment={logShipment}
      />
    </>
  );
}
