import { useMemo, useState, useEffect } from "react";
import { usePage, router } from "@inertiajs/react";
import { motion, AnimatePresence } from "framer-motion";
import {
  Package,
  CheckCircle2,
  RotateCcw,
  Clock,
  BellRing,
  Search,
  FileSpreadsheet,
  FileDown,
  Trash2,
  ChevronLeft,
  ChevronRight,
  X,
} from "lucide-react";
import { toast } from "sonner";
import { Toaster } from "@/components/ui/sonner";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Input } from "@/components/ui/input";
import { TopBar } from "@/components/posindo/TopBar";
import { DataTable } from "@/components/posindo/DataTable";
import { ExportPanel } from "@/components/posindo/ExportPanel";
import {
  FU_META,
  FU_ORDER,
  MONTHS,
  generateShipments,
  nf,
  type FuStatus,
  type Shipment,
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
    belum?: number;
    perluFu?: number;
  };
  sellersList?: string[];
  googleSheetUrl?: string;
  googleSheetId?: string;
  trackingProgress?: {
    percentage: number;
    tracked: number;
    total: number;
    is_running: boolean;
  };
}

const DUMMY_SEED = generateShipments();

export default function Dashboard() {
  const { props } = usePage<PageProps>();

  // 1. Extract shipmentList array safely from props.shipments or fallback
  const shipmentList = useMemo<Shipment[]>(() => {
    if (!props.shipments) return [];
    if (Array.isArray(props.shipments)) return props.shipments;
    return Array.isArray((props.shipments as PaginatedData<Shipment>).data)
      ? (props.shipments as PaginatedData<Shipment>).data
      : [];
  }, [props.shipments]);

  const [rows, setRows] = useState<Shipment[]>(shipmentList);
  const [seller, setSeller] = useState(props.filters?.seller || "Semua Seller");
  const [month, setMonth] = useState<number | "all">(
    props.filters?.month !== undefined ? (props.filters.month === "ALL" ? "all" : Number(props.filters.month)) : "all"
  );
  const [colorFilter, setColorFilter] = useState<FuStatus | null>(
    (props.filters?.color as FuStatus) || null
  );
  const [query, setQuery] = useState(props.filters?.search || "");
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [exportOpen, setExportOpen] = useState(false);

  useEffect(() => {
    setRows(shipmentList);
  }, [shipmentList]);

  // Extract pagination info safely
  const isPaginated = props.shipments && !Array.isArray(props.shipments);
  const paginatedObj = isPaginated ? (props.shipments as PaginatedData<Shipment>) : null;

  const totalCount = paginatedObj?.total ?? rows.length;
  const currentPage = paginatedObj?.current_page ?? 1;
  const lastPage = paginatedObj?.last_page ?? 1;
  const fromItem = paginatedObj?.from ?? (rows.length > 0 ? 1 : 0);
  const toItem = paginatedObj?.to ?? rows.length;

  const bySeller = useMemo(
    () => (seller === "Semua Seller" ? rows : rows.filter((r) => r.seller === seller)),
    [rows, seller]
  );

  const monthCounts = useMemo(() => {
    const c = new Array(12).fill(0) as number[];
    bySeller.forEach((r) => {
      if (r.tanggalKirim) {
        const idx = Number(r.tanggalKirim.slice(5, 7)) - 1;
        if (idx >= 0 && idx < 12) {
          c[idx] = (c[idx] ?? 0) + 1;
        }
      }
    });
    return c;
  }, [bySeller]);

  const kpi = useMemo(() => {
    if (props.stats) {
      return {
        total: props.stats.total ?? 0,
        sukses: props.stats.sukses ?? 0,
        retur: props.stats.retur ?? 0,
        belum: props.stats.belum ?? (props.stats.total ?? 0) - (props.stats.sukses ?? 0) - (props.stats.retur ?? 0) - (props.stats.follow_up ?? 0),
        perluFu: props.stats.follow_up ?? props.stats.perluFu ?? 0,
      };
    }
    const c = (f: FuStatus) => rows.filter((r) => r.fu === f).length;
    return {
      total: rows.length,
      sukses: c("BIRU"),
      retur: c("ORANGE"),
      belum: c("PUTIH"),
      perluFu: c("KUNING") + c("HIJAU") + c("BIRU_TUA"),
    };
  }, [props.stats, rows]);

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
    },
    {
      label: "Paket Sukses",
      value: kpi.sukses,
      icon: CheckCircle2,
      bg: "#BAE6FD",
      fg: "#0369A1",
      sub: "DELIVERED",
    },
    {
      label: "Paket Retur",
      value: kpi.retur,
      icon: RotateCcw,
      bg: "#FED7AA",
      fg: "#C2410C",
      sub: "RETURN / GAGAL SERAH",
    },
    {
      label: "Belum di FU / In-Transit",
      value: kpi.belum,
      icon: Clock,
      bg: "#FFFFFF",
      fg: "#374151",
      sub: "ON PROCESS / RUNSHEET",
    },
    {
      label: "Perlu Follow-Up CS",
      value: kpi.perluFu,
      icon: BellRing,
      bg: "#FEF08A",
      fg: "#854D0E",
      sub: "SUDAH DI FU + 2 KALI + FU POS",
    },
  ];

  return (
    <div className="min-h-screen bg-background text-foreground">
      <Toaster position="top-right" richColors />
      <header className="sticky top-0 z-30 shadow-sm">
        <TopBar
          seller={seller}
          onSeller={(s) => {
            setSeller(s);
            router.get("/shipments", { seller: s, month, color: colorFilter, search: query }, { preserveState: true, preserveScroll: true });
          }}
          total={totalCount}
          trackingProgress={props.trackingProgress}
          googleSheetUrl={props.googleSheetUrl}
          googleSheetId={props.googleSheetId}
        />
        <div className="pos-scroll flex gap-1 overflow-x-auto border-b border-border bg-card/95 px-5 py-1.5 backdrop-blur">
          {[{ label: "Semua (Setahun)", idx: "all" as const, n: totalCount }].concat(
            MONTHS.map((m, i) => ({ label: m, idx: i as never, n: monthCounts[i] ?? 0 }))
          ).map((t) => {
            const active = month === t.idx;
            return (
              <button
                key={t.label}
                onClick={() => {
                  setMonth(t.idx);
                  router.get("/shipments", { seller, month: t.idx === "all" ? "ALL" : t.idx, color: colorFilter, search: query }, { preserveState: true, preserveScroll: true });
                }}
                className={`relative shrink-0 rounded-md px-3 py-1.5 text-xs font-semibold transition cursor-pointer ${
                  active
                    ? "bg-[#1E40AF] text-white"
                    : "text-muted-foreground hover:bg-muted"
                }`}
              >
                {t.label}{" "}
                <span className={active ? "opacity-80" : "opacity-60"}>({nf(t.n)})</span>
              </button>
            );
          })}
        </div>
      </header>

      <main className="space-y-4 p-5">
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          {cards.map((c, i) => (
            <motion.div
              key={c.label}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.04 }}
              style={{ backgroundColor: c.bg, color: c.fg }}
              className="rounded-xl border border-border p-4 shadow-sm"
            >
              <div className="flex items-start justify-between">
                <div>
                  <div className="text-[11px] font-bold tracking-wide uppercase opacity-80">
                    {c.label}
                  </div>
                  <div className="mt-1 text-2xl font-extrabold tabular-nums">{nf(c.value)}</div>
                  <div className="text-[11px] opacity-70">
                    {c.sub} ·{" "}
                    {kpi.total ? ((c.value / kpi.total) * 100).toFixed(1) : "0.0"}%
                  </div>
                </div>
                <c.icon className="h-5 w-5 opacity-70" />
              </div>
            </motion.div>
          ))}
        </section>

        <section className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-3">
          <div className="flex flex-wrap items-center gap-1.5">
            <span className="mr-1 text-[11px] font-bold tracking-wide uppercase text-muted-foreground">
              Filter Warna
            </span>
            {FU_ORDER.map((k) => (
              <button
                key={k}
                onClick={() => {
                  const nextColor = colorFilter === k ? null : k;
                  setColorFilter(nextColor);
                  router.get("/shipments", { seller, month, color: nextColor, search: query }, { preserveState: true, preserveScroll: true });
                }}
                style={{ backgroundColor: FU_META[k].bg, color: FU_META[k].fg }}
                className={`rounded-full border px-2.5 py-1 text-[11px] font-bold transition cursor-pointer ${
                  colorFilter === k
                    ? "border-[#1E40AF] ring-2 ring-[#1E40AF]/40"
                    : "border-black/10 hover:brightness-95"
                }`}
              >
                {FU_META[k].short}
              </button>
            ))}
            {colorFilter && (
              <button
                onClick={() => {
                  setColorFilter(null);
                  router.get("/shipments", { seller, month, search: query }, { preserveState: true, preserveScroll: true });
                }}
                className="ml-1 inline-flex items-center gap-1 text-[11px] font-semibold text-muted-foreground hover:text-foreground cursor-pointer"
              >
                <X className="h-3 w-3" /> reset
              </button>
            )}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button
              onClick={() => setExportOpen(true)}
              className="gap-2 bg-[#1E40AF] text-white hover:bg-blue-900 cursor-pointer"
            >
              <FileSpreadsheet className="h-4 w-4 text-[#F97316]" />
              Generate Laporan Seller{" "}
              {seller === "Semua Seller" ? "Aliqa" : seller.replace("Mitra ", "")} (.XLSX)
            </Button>
            <Button variant="outline" className="gap-2 cursor-pointer" onClick={() => setExportOpen(true)}>
              <FileDown className="h-4 w-4" /> Export Full Color-Coded Excel
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
              <span className="text-xs font-bold text-[#1E40AF]">{selected.size} resi dipilih</span>
              <Button size="sm" variant="secondary" onClick={() => bulk("BIRU")}>
                Mark as Sukses
              </Button>
              <Button size="sm" variant="secondary" onClick={() => bulk("KUNING")}>
                Mark as Follow-Up
              </Button>
              <Button size="sm" variant="secondary" onClick={() => bulk("ORANGE")}>
                Mark as Retur
              </Button>
              <Button size="sm" variant="outline" onClick={() => setExportOpen(true)}>
                Export Selected
              </Button>
              <Button
                size="sm"
                variant="destructive"
                className="gap-1"
                onClick={deleteSelected}
              >
                <Trash2 className="h-3.5 w-3.5" /> Delete Selected
              </Button>
              <button
                className="ml-auto text-xs text-muted-foreground hover:text-foreground cursor-pointer"
                onClick={() => setSelected(new Set())}
              >
                Batalkan pilihan
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
    </div>
  );
}
