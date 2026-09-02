import { useMemo, useState, useEffect } from "react";
import { usePage, router } from "@inertiajs/react";
import { motion, AnimatePresence } from "framer-motion";
import {
  Package,
  CheckCircle2,
  RotateCcw,
  Clock,
  BellRing,
  Repeat,
  Send,
  Search,
  FileSpreadsheet,
  FileDown,
  Trash2,
  ChevronLeft,
  ChevronRight,
  X,
  Check,
} from "lucide-react";
import { toast } from "sonner";
import { Toaster } from "@/components/ui/sonner";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Input } from "@/components/ui/input";
import { TopBar } from "@/components/posindo/TopBar";
import { DataTable } from "@/components/posindo/DataTable";
import { ExportPanel } from "@/components/posindo/ExportPanel";
import { WhatsAppFollowUpModal } from "@/components/posindo/WhatsAppFollowUpModal";
import { PostOfficesManagerModal } from "@/components/posindo/PostOfficesManagerModal";
import {
  FU_META,
  FU_ORDER,
  MONTHS,
  generateShipments,
  nf,
  type FuStatus,
  type Shipment,
  type PostOffice,
} from "@/lib/posindo";

export interface PaginatedData<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  links?: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface PageProps {
  shipments?: Shipment[] | PaginatedData<Shipment>;
  postOffices?: PostOffice[];
  filters?: {
    seller?: string;
    month?: string | number;
    color?: string;
    search?: string;
    kategori?: string;
  };
  stats?: {
    total?: number;
    sukses?: number;
    retur?: number;
    follow_up?: number;
    sudah_fu?: number;
    fu_2_kali?: number;
    fu_pos?: number;
    belum?: number;
    perluFu?: number;
  };
  monthCounts?: number[];
  yearTotal?: number;
  sellersList?: string[];
  googleSheetUrl?: string;
  googleSheetId?: string;
  googleSheetWebhookUrl?: string;
  trackingProgress?: {
    percentage: number;
    tracked: number;
    total: number;
    is_running: boolean;
  };
}

const DUMMY_SEED = generateShipments();

export default function Dashboard() {
  const pageProps = (usePage<PageProps>()?.props || {}) as PageProps;

  // 1. Extract shipmentList array safely from props.shipments or fallback
  const shipmentList = useMemo<Shipment[]>(() => {
    const s = pageProps?.shipments;
    if (!s) return [];
    if (Array.isArray(s)) return s;
    if (s && typeof s === "object" && "data" in s && Array.isArray((s as PaginatedData<Shipment>).data)) {
      return (s as PaginatedData<Shipment>).data || [];
    }
    return [];
  }, [pageProps?.shipments]);

  const [rows, setRows] = useState<Shipment[]>(shipmentList);
  const [postOffices, setPostOffices] = useState<PostOffice[]>(pageProps?.postOffices || []);
  const [waModalOpen, setWaModalOpen] = useState(false);
  const [selectedShipmentForWa, setSelectedShipmentForWa] = useState<Shipment | null>(null);
  const [postOfficesModalOpen, setPostOfficesModalOpen] = useState(false);

  useEffect(() => {
    if (pageProps?.postOffices && Array.isArray(pageProps.postOffices)) {
      setPostOffices(pageProps.postOffices);
    }
  }, [pageProps?.postOffices]);

  const [seller, setSeller] = useState(() => {
    const s = pageProps?.filters?.seller;
    if (!s || s === "ALL" || s === "all" || s === "Semua Seller") return "Semua Seller";
    return String(s);
  });
  const [month, setMonth] = useState<number | "all">(() => {
    const fMonth = pageProps?.filters?.month;
    if (!fMonth || fMonth === "ALL" || fMonth === "all") return "all";
    const n = Number(fMonth);
    return !isNaN(n) && n >= 1 && n <= 12 ? n : "all";
  });
  const [colorFilter, setColorFilter] = useState<FuStatus | null>(
    (pageProps?.filters?.color as FuStatus) || null
  );
  const [query, setQuery] = useState(pageProps?.filters?.search || "");
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [exportOpen, setExportOpen] = useState(false);

  useEffect(() => {
    setRows(shipmentList);
  }, [shipmentList]);

  // Extract pagination info safely
  const isPaginated = Boolean(
    pageProps?.shipments &&
      !Array.isArray(pageProps.shipments) &&
      typeof pageProps.shipments === "object" &&
      "data" in pageProps.shipments
  );
  const paginatedObj = isPaginated ? (pageProps.shipments as PaginatedData<Shipment>) : null;

  const totalCount = paginatedObj?.total ?? rows?.length ?? 0;
  const currentPage = paginatedObj?.current_page ?? 1;
  const lastPage = paginatedObj?.last_page ?? 1;
  const fromItem = paginatedObj?.from ?? ((rows?.length || 0) > 0 ? 1 : 0);
  const toItem = paginatedObj?.to ?? rows?.length ?? 0;

  const bySeller = useMemo(
    () => (!seller || seller === "Semua Seller" || seller === "ALL" ? (rows || []) : (rows || []).filter((r) => r?.seller === seller)),
    [rows, seller]
  );

  const monthCounts = useMemo(() => {
    if (pageProps?.monthCounts && Array.isArray(pageProps.monthCounts) && pageProps.monthCounts.length === 12) {
      return pageProps.monthCounts;
    }
    const c = new Array(12).fill(0) as number[];
    (bySeller || []).forEach((r) => {
      if (r?.tanggalKirim && typeof r.tanggalKirim === "string") {
        const idx = Number(r.tanggalKirim.slice(5, 7)) - 1;
        if (idx >= 0 && idx < 12) {
          c[idx] = (c[idx] ?? 0) + 1;
        }
      }
    });
    return c;
  }, [pageProps?.monthCounts, bySeller]);

  const kpi = useMemo(() => {
    if (pageProps?.stats) {
      return {
        total: pageProps.stats.total ?? 0,
        sukses: pageProps.stats.sukses ?? 0,
        retur: pageProps.stats.retur ?? 0,
        belum: pageProps.stats.belum ?? 0,
        sudahFu: pageProps.stats.sudah_fu ?? 0,
        fu2Kali: pageProps.stats.fu_2_kali ?? 0,
        fuPos: pageProps.stats.fu_pos ?? 0,
        perluFu: pageProps.stats.follow_up ?? pageProps.stats.perluFu ?? 0,
      };
    }
    const rList = rows || [];
    const c = (f: FuStatus) => rList.filter((r) => r?.fu === f).length;
    return {
      total: rList.length,
      sukses: c("BIRU"),
      retur: c("ORANGE"),
      belum: c("PUTIH"),
      sudahFu: c("KUNING"),
      fu2Kali: c("HIJAU"),
      fuPos: c("BIRU_TUA"),
      perluFu: c("KUNING") + c("HIJAU") + c("BIRU_TUA"),
    };
  }, [pageProps?.stats, rows]);

  // Send update-status request to Laravel backend via Inertia router
  const setStatus = (ids: string[], fu: FuStatus, escalationDate?: string) => {
    const set = new Set(ids);
    setRows((prev) =>
      prev.map((r) =>
        set.has(r.id) ? { ...r, fu, ...(escalationDate ? { escalationDate } : {}) } : r
      )
    );

    router.post(
      "/shipments/update-status",
      { ids, fu, escalationDate },
      {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`${ids.length} resi diperbarui → ${FU_META[fu]?.label || fu}`);
        },
        onError: () => {
          if (ids.length === 1) {
            router.post(`/shipments/${ids[0]}/update-color`, { color_code: fu, fu_pos_date: escalationDate });
          }
        },
      }
    );
  };

  const setNote = (id: string, note: string) => {
    setRows((prev) => prev.map((r) => (r.id === id ? { ...r, note } : r)));
    router.post(`/shipments/${id}/update-color`, { color_code: "NOTED", noted: note }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => toast.success("Catatan tersimpan"),
    });
  };

  const toggle = (id: string) =>
    setSelected((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  const allSelected = rows.length > 0 && rows.every((r) => selected.has(r.id));
  const toggleAll = () =>
    setSelected((prev) => {
      const next = new Set(prev);
      if (allSelected) rows.forEach((r) => next.delete(r.id));
      else rows.forEach((r) => next.add(r.id));
      return next;
    });

  const bulk = (fu: FuStatus) => {
    const ids = Array.from(selected);
    setStatus(ids, fu);
    setSelected(new Set());
  };

  const deleteSelected = () => {
    const ids = Array.from(selected);
    setRows((prev) => prev.filter((r) => !selected.has(r.id)));
    router.post(
      "/shipments/bulk-action",
      { ids, action: "DELETE" },
      {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`${ids.length} resi berhasil dihapus`);
          setSelected(new Set());
        },
      }
    );
  };

  const cards = [
    {
      label: "Total Kiriman",
      value: kpi.total,
      icon: Package,
      bg: "#F3F4F6",
      fg: "#374151",
      sub: "seluruh resi outgoing",
      colorKey: null,
    },
    {
      label: "Paket Sukses",
      value: kpi.sukses,
      icon: CheckCircle2,
      bg: "#46BDC6",
      fg: "#083344",
      sub: "DELIVERED",
      colorKey: "BIRU" as FuStatus,
    },
    {
      label: "Paket Retur",
      value: kpi.retur,
      icon: RotateCcw,
      bg: "#FBBC04",
      fg: "#451A03",
      sub: "RETURN / GAGAL SERAH",
      colorKey: "ORANGE" as FuStatus,
    },
    {
      label: "Belum di FU",
      value: kpi.belum,
      icon: Clock,
      bg: "#FFFFFF",
      fg: "#1E293B",
      sub: "ON PROCESS / RUNSHEET",
      colorKey: "PUTIH" as FuStatus,
    },
    {
      label: "Sudah di FU",
      value: kpi.sudahFu,
      icon: BellRing,
      bg: "#FFFF00",
      fg: "#422006",
      sub: "FOLLOW-UP CS 1X",
      colorKey: "KUNING" as FuStatus,
    },
    {
      label: "FU 2 Kali",
      value: kpi.fu2Kali,
      icon: Repeat,
      bg: "#93C47D",
      fg: "#14532D",
      sub: "FOLLOW-UP 2 KALI",
      colorKey: "HIJAU" as FuStatus,
    },
    {
      label: "FU POS",
      value: kpi.fuPos,
      icon: Send,
      bg: "#1C4587",
      fg: "#FFFFFF",
      sub: "ESKALASI POS PUSAT",
      colorKey: "BIRU_TUA" as FuStatus,
    },
  ];

  return (
    <div className="min-h-screen bg-background text-foreground">
      <Toaster position="top-right" richColors />
      <header className="sticky top-0 z-30 shadow-sm">
        {/* 1. TopBar Utama (Logo & Tombol Aksi di Atas) */}
        <TopBar
          seller={seller}
          sellersList={pageProps?.sellersList || []}
          onSeller={(s) => {
            setSeller(s);
            router.get("/shipments", { seller: s, month, color: colorFilter, search: query }, { preserveState: true, preserveScroll: true });
          }}
          total={totalCount}
          trackingProgress={pageProps?.trackingProgress}
          googleSheetUrl={pageProps?.googleSheetUrl}
          googleSheetId={pageProps?.googleSheetId}
          googleSheetWebhookUrl={pageProps?.googleSheetWebhookUrl}
          onOpenPostOffices={() => setPostOfficesModalOpen(true)}
        />

        {/* 2. Bar Navigasi 12 Bulan (Di Bawah TopBar) */}
        <div className="pos-scroll overflow-x-auto border-b border-border bg-card/95 px-4 py-1.5 backdrop-blur">
          <div className="flex w-full min-w-[920px] items-center gap-1.5 justify-between">
            {[{ label: "Semua (Setahun)", monthNum: "all" as const, n: pageProps?.yearTotal ?? totalCount }].concat(
              MONTHS.map((m, i) => ({ label: m, monthNum: (i + 1) as never, n: monthCounts[i] ?? 0 }))
            ).map((t) => {
              const active = month === t.monthNum;
              const isAll = t.monthNum === "all";
              return (
                <button
                  key={t.label}
                  onClick={() => {
                    setMonth(t.monthNum);
                    router.get(
                      "/shipments",
                      { seller, month: t.monthNum === "all" ? "ALL" : t.monthNum, color: colorFilter, search: query },
                      { preserveState: true, preserveScroll: true }
                    );
                  }}
                  className={`relative flex items-center justify-center gap-1 rounded-md px-2 py-1.5 text-xs font-semibold transition cursor-pointer whitespace-nowrap text-center ${
                    isAll ? "flex-[1.35]" : "flex-1"
                  } ${
                    active
                      ? "bg-[#1E40AF] text-white shadow-sm"
                      : "text-muted-foreground hover:bg-muted hover:text-foreground"
                  }`}
                >
                  <span>{t.label}</span>{" "}
                  <span className={`text-[11px] ${active ? "opacity-85 text-blue-100" : "opacity-65"}`}>
                    ({nf(t.n)})
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      </header>

      <main className="space-y-4 p-5">
        <section className="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
          {cards.map((c, i) => {
            const isSelected = c.colorKey !== null && colorFilter === c.colorKey;
            return (
              <motion.div
                key={c.label}
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.03 }}
                onClick={() => {
                  if (c.colorKey === null) {
                    setColorFilter(null);
                    router.get("/shipments", { seller, month, search: query }, { preserveState: true, preserveScroll: true });
                  } else if (c.colorKey) {
                    const nextColor = colorFilter === c.colorKey ? null : c.colorKey;
                    setColorFilter(nextColor);
                    router.get("/shipments", { seller, month, color: nextColor, search: query }, { preserveState: true, preserveScroll: true });
                  }
                }}
                style={{ backgroundColor: c.bg, color: c.fg }}
                className={`rounded-xl border p-3.5 shadow-sm transition-all cursor-pointer hover:shadow-md hover:scale-[1.02] ${
                  isSelected ? "ring-2 ring-[#1E40AF] ring-offset-2 border-[#1E40AF]" : "border-border"
                }`}
              >
                <div className="flex items-start justify-between">
                  <div>
                    <div className="text-[10.5px] font-bold tracking-wide uppercase opacity-85">
                      {c.label}
                    </div>
                    <div className="mt-1 text-2xl font-extrabold tabular-nums">{nf(c.value)}</div>
                    <div className="text-[10px] opacity-75 font-medium mt-0.5">
                      {c.sub} ·{" "}
                      {kpi.total ? ((c.value / kpi.total) * 100).toFixed(1) : "0.0"}%
                    </div>
                  </div>
                  <c.icon className="h-4.5 w-4.5 opacity-75 shrink-0 ml-1 mt-0.5" />
                </div>
              </motion.div>
            );
          })}
        </section>

        <section className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-3">
          <div className="flex flex-wrap items-center gap-1.5">
            <span className="mr-1 text-[11px] font-bold tracking-wide uppercase text-muted-foreground">
              Filter Status CS
            </span>
            {FU_ORDER.map((k) => {
              const active = colorFilter === k;
              return (
                <button
                  key={k}
                  onClick={() => {
                    const nextColor = active ? null : k;
                    setColorFilter(nextColor);
                    router.get(
                      "/shipments",
                      { seller, month, color: nextColor, search: query },
                      { preserveState: true, preserveScroll: true }
                    );
                  }}
                  style={{ backgroundColor: FU_META[k].bg, color: FU_META[k].fg }}
                  className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-extrabold transition-all transform cursor-pointer ${
                    active
                      ? "ring-2 ring-[#1E40AF] ring-offset-1 border-[#1E40AF] shadow-md scale-105"
                      : "border-black/20 hover:scale-102 hover:brightness-95 opacity-85 hover:opacity-100"
                  }`}
                >
                  {active && <Check className="h-3 w-3 text-current stroke-[3]" />}
                  {FU_META[k].label}
                </button>
              );
            })}
            {colorFilter && (
              <button
                onClick={() => {
                  setColorFilter(null);
                  router.get(
                    "/shipments",
                    { seller, month, search: query },
                    { preserveState: true, preserveScroll: true }
                  );
                }}
                className="ml-1 inline-flex items-center gap-1 rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-bold text-slate-700 hover:bg-slate-300 transition cursor-pointer"
              >
                <X className="h-3 w-3" /> Reset Filter
              </button>
            )}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button
              onClick={() => setExportOpen(true)}
              className="gap-2 bg-[#1E40AF] text-white hover:bg-blue-900 cursor-pointer font-bold shadow-sm text-xs px-4"
            >
              <FileSpreadsheet className="h-4 w-4 text-[#F97316]" />
              Export Laporan Seller {!seller || seller === "Semua Seller" || seller === "ALL" ? "ALL" : String(seller).replace("Mitra ", "")} (.XLSX)
            </Button>
          </div>
        </section>

        <section className="grid gap-3 rounded-xl border border-border bg-card p-3 md:grid-cols-[1fr_260px]">
          <div className="relative">
            <Search className="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" />
            <Textarea
              value={query}
              onChange={(e) => {
                setQuery(e.target.value);
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                  e.preventDefault();
                  router.get("/shipments", { seller, month, color: colorFilter, search: query }, { preserveState: true, preserveScroll: true });
                }
              }}
              placeholder="Cari multi-resi — tempel beberapa nomor resi dipisah koma atau baris baru, lalu tekan Enter..."
              className="min-h-[42px] resize-y pl-9 text-xs"
              rows={1}
            />
          </div>
          <Input
            value={seller === "Semua Seller" ? "" : seller}
            onChange={(e) => setSeller(e.target.value || "Semua Seller")}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                router.get("/shipments", { seller, month, color: colorFilter, search: query }, { preserveState: true, preserveScroll: true });
              }
            }}
            placeholder="Filter nama seller..."
            className="text-xs"
          />
        </section>

        <AnimatePresence>
          {selected.size > 0 && (
            <motion.div
              initial={{ opacity: 0, y: -6 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -6 }}
              className="flex flex-wrap items-center gap-2 rounded-xl border border-blue-300 bg-blue-50 p-3"
            >
              <span className="text-xs font-bold text-[#1E40AF] mr-1">{selected.size} resi dipilih</span>
              <Button size="sm" variant="secondary" className="cursor-pointer bg-sky-100 text-sky-800 hover:bg-sky-200 border border-sky-300 font-bold text-xs" onClick={() => bulk("BIRU")}>
                Mark as Sukses
              </Button>
              <Button size="sm" variant="secondary" className="cursor-pointer bg-yellow-100 text-yellow-800 hover:bg-yellow-200 border border-yellow-300 font-bold text-xs" onClick={() => bulk("KUNING")}>
                Mark as Sudah FU
              </Button>
              <Button size="sm" variant="secondary" className="cursor-pointer bg-emerald-100 text-emerald-800 hover:bg-emerald-200 border border-emerald-300 font-bold text-xs" onClick={() => bulk("HIJAU")}>
                Mark as FU 2 Kali
              </Button>
              <Button size="sm" variant="secondary" className="cursor-pointer bg-[#1E40AF] text-white hover:bg-blue-900 border border-blue-900 font-bold text-xs" onClick={() => bulk("BIRU_TUA")}>
                Mark as FU POS
              </Button>
              <Button size="sm" variant="secondary" className="cursor-pointer bg-orange-100 text-orange-800 hover:bg-orange-200 border border-orange-300 font-bold text-xs" onClick={() => bulk("ORANGE")}>
                Mark as Retur
              </Button>
              <Button size="sm" variant="secondary" className="cursor-pointer bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-bold text-xs" onClick={() => bulk("PUTIH")}>
                Mark as Belum FU
              </Button>
              <Button size="sm" variant="outline" className="cursor-pointer font-bold text-xs ml-auto" onClick={() => setExportOpen(true)}>
                Export Selected
              </Button>
              <Button
                size="sm"
                variant="destructive"
                className="gap-1 cursor-pointer font-bold text-xs"
                onClick={deleteSelected}
              >
                <Trash2 className="h-3.5 w-3.5" /> Delete Selected
              </Button>
              <button
                className="text-xs text-muted-foreground hover:text-foreground cursor-pointer font-medium"
                onClick={() => setSelected(new Set())}
              >
                Batalkan
              </button>
            </motion.div>
          )}
        </AnimatePresence>

        <DataTable
          rows={rows}
          startIndex={fromItem > 0 ? fromItem - 1 : 0}
          selected={selected}
          allSelected={allSelected}
          onToggle={toggle}
          onToggleAll={toggleAll}
          onStatus={setStatus}
          onNote={setNote}
          onOpenWhatsApp={(shipment) => {
            setSelectedShipmentForWa(shipment);
            setWaModalOpen(true);
          }}
        />

        <section className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card px-3 py-2">
          <div className="text-xs text-muted-foreground">
            Menampilkan{" "}
            <b className="text-foreground">
              {nf(fromItem)}–{nf(toItem)}
            </b>{" "}
            dari <b className="text-foreground">{nf(totalCount)}</b> resi
          </div>
          <div className="flex items-center gap-2">
            <Button
              size="icon"
              variant="outline"
              className="h-8 w-8 cursor-pointer"
              disabled={currentPage <= 1}
              onClick={() => {
                router.get("/shipments", { seller, month, color: colorFilter, search: query, page: currentPage - 1 }, { preserveState: true, preserveScroll: true });
              }}
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <span className="font-mono text-xs">
              Halaman {currentPage} dari {lastPage}
            </span>
            <Button
              size="icon"
              variant="outline"
              className="h-8 w-8 cursor-pointer"
              disabled={currentPage >= lastPage}
              onClick={() => {
                router.get("/shipments", { seller, month, color: colorFilter, search: query, page: currentPage + 1 }, { preserveState: true, preserveScroll: true });
              }}
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </section>
      </main>

      <ExportPanel
        open={exportOpen}
        onOpenChange={setExportOpen}
        rows={selected.size ? rows.filter((r) => selected.has(r.id)) : rows}
        seller={seller === "Semua Seller" ? "Mitra Aliqa (Semua Seller)" : seller}
      />

      <WhatsAppFollowUpModal
        isOpen={waModalOpen}
        onClose={() => {
          setWaModalOpen(false);
          setSelectedShipmentForWa(null);
        }}
        shipment={selectedShipmentForWa}
        postOffices={postOffices}
        onStatusUpdate={(ids, status) => setStatus(ids, status)}
      />

      <PostOfficesManagerModal
        isOpen={postOfficesModalOpen}
        onClose={() => setPostOfficesModalOpen(false)}
        postOffices={postOffices}
        onOfficesUpdated={(updated) => setPostOffices(updated)}
      />
    </div>
  );
}
