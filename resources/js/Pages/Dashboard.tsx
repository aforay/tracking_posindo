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
  ArrowUpDown,
  Bot,
  Loader2,
  AlertTriangle,
} from "lucide-react";
import { toast } from "sonner";
import { Toaster } from "@/components/ui/sonner";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { TopBar } from "@/components/posindo/TopBar";
import { DataTable } from "@/components/posindo/DataTable";
import { ExportPanel } from "@/components/posindo/ExportPanel";
import { WhatsAppFollowUpModal } from "@/components/posindo/WhatsAppFollowUpModal";
import { PostOfficesManagerModal } from "@/components/posindo/PostOfficesManagerModal";
import {
  FU_META,
  FU_ORDER,
  getSellerFuMeta,
  getSellerFuOrder,
  MONTHS,
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
  auth?: {
    user?: {
      id: number;
      name: string;
      email: string;
      role: string;
      is_admin: boolean;
    } | null;
  };
  shipments?: Shipment[] | PaginatedData<Shipment>;
  postOffices?: PostOffice[];
  filters?: {
    seller?: string;
    month?: string | number;
    color?: string;
    search?: string;
    kategori?: string;
    sort?: string;
    direction?: string;
    overdue?: boolean | string | number;
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
    overdue?: number;
  };
  monthCounts?: number[];
  monthPendingCounts?: number[];
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

export default function Dashboard() {
  const pageProps = (usePage<PageProps>()?.props || {}) as PageProps;
  const currentUser = pageProps?.auth?.user || null;
  const isAdmin = currentUser ? (currentUser.is_admin ?? currentUser.role === "admin") : true;

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
    if (s && s !== "ALL" && s !== "all" && s !== "Semua Seller") {
      return s.toUpperCase().includes("ZAHERBA") ? "Mitra Zaherba" : "Mitra Aliqa";
    }
    if (typeof window !== "undefined") {
      const saved = localStorage.getItem("posindo_active_seller");
      if (saved && (saved.includes("Zaherba") || saved.includes("Aliqa"))) {
        return saved.includes("Zaherba") ? "Mitra Zaherba" : "Mitra Aliqa";
      }
    }
    return "Mitra Aliqa";
  });

  const [isOverdue, setIsOverdue] = useState<boolean>(() => {
    const ov = pageProps?.filters?.overdue;
    return ov === true || ov === "1" || ov === "true" || ov === 1;
  });

  useEffect(() => {
    const ov = pageProps?.filters?.overdue;
    setIsOverdue(ov === true || ov === "1" || ov === "true" || ov === 1);
  }, [pageProps?.filters?.overdue]);

  const handleSellerChange = (newSeller: string) => {
    const nextSeller = newSeller.toUpperCase().includes("ZAHERBA") ? "Mitra Zaherba" : "Mitra Aliqa";
    if (typeof window !== "undefined") {
      localStorage.setItem("posindo_active_seller", nextSeller);
    }
    setSeller(nextSeller);
    router.get(
      "/shipments",
      { seller: nextSeller, month, color: colorFilter, search: query, sort, direction, overdue: isOverdue ? 1 : undefined },
      { preserveState: true, preserveScroll: true }
    );
  };

  useEffect(() => {
    if (typeof window !== "undefined" && seller) {
      localStorage.setItem("posindo_active_seller", seller);
    }
  }, [seller]);

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
  const [sort, setSort] = useState<string>(() => pageProps?.filters?.sort || "sheet");
  const [direction, setDirection] = useState<"asc" | "desc">(() => (pageProps?.filters?.direction as "asc" | "desc") || "asc");
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [exportOpen, setExportOpen] = useState(false);

  const handleSortChange = (newSort: string) => {
    const newDir = sort === newSort && direction === "asc" ? "desc" : "asc";
    setSort(newSort);
    setDirection(newDir);
    router.get(
      "/shipments",
      { seller, month, color: colorFilter, search: query, sort: newSort, direction: newDir, overdue: isOverdue ? 1 : undefined },
      { preserveState: true, preserveScroll: true }
    );
  };

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
    () => (rows || []).filter((r) => !seller || r?.seller === seller),
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

  const [isTrackingSelected, setIsTrackingSelected] = useState(false);
  const trackSelected = async () => {
    const ids = Array.from(selected);
    if (ids.length === 0) return;

    try {
      setIsTrackingSelected(true);
      const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
        (document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)![1]) : "");

      const res = await fetch("/bot/start-tracking", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-XSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({
          shipment_ids: ids,
        }),
      });

      const data = await res.json();
      if (data.success) {
        toast.success(`Berhasil melacak ${data.processed_count || ids.length} resi NIPOS terpilih!`);
        router.reload({ preserveScroll: true });
        setSelected(new Set());
      } else {
        toast.error(data.message || "Gagal melacak resi terpilih");
      }
    } catch (err: any) {
      toast.error("Terjadi kesalahan: " + (err?.message || err));
    } finally {
      setIsTrackingSelected(false);
    }
  };

  const isAliqa = typeof seller === "string" && seller.toUpperCase().includes("ALIQA");
  const currentFuMeta = useMemo(() => getSellerFuMeta(seller), [seller]);
  const currentFuOrder = useMemo(() => getSellerFuOrder(seller), [seller]);

  const cards = useMemo(() => {
    if (isAliqa) {
      // 6 Kotak Khusus Mitra Aliqa sesuai catatan resmi:
      // Total Kiriman, Paket Sukses (Hijau Toska), Paket Retur (Merah), Sudah di FU (Kuning), BLM di FU (Putih), ON FU POS (Biru Tua)
      return [
        {
          label: "Total Kiriman",
          value: kpi.total,
          icon: Package,
          bg: "#F3F4F6",
          fg: "#374151",
          sub: "seluruh resi aliqa",
          colorKey: null,
        },
        {
          label: "Paket Sukses",
          value: kpi.sukses,
          icon: CheckCircle2,
          bg: "#38D9A9", // Hijau Toska
          fg: "#000000",
          sub: "DELIVERED",
          colorKey: "BIRU" as FuStatus,
        },
        {
          label: "Paket Retur",
          value: kpi.retur,
          icon: RotateCcw,
          bg: "#E8A29A", // Merah
          fg: "#000000",
          sub: "RETURN / GAGAL SERAH",
          colorKey: "ORANGE" as FuStatus,
        },
        {
          label: "Sudah di FU",
          value: kpi.sudahFu,
          icon: BellRing,
          bg: "#FFFF00", // Kuning
          fg: "#000000",
          sub: "SUDAH DI FU",
          colorKey: "KUNING" as FuStatus,
        },
        {
          label: "BLM di FU",
          value: kpi.belum,
          icon: Clock,
          bg: "#FFFFFF", // Putih
          fg: "#1E293B",
          sub: "BLM DI FU",
          colorKey: "PUTIH" as FuStatus,
        },
        {
          label: "ON FU POS",
          value: kpi.fuPos,
          icon: Send,
          bg: "#1C4587", // Biru Tua
          fg: "#FFFFFF",
          sub: "ESKALASI KC / KCU",
          colorKey: "BIRU_TUA" as FuStatus,
        },
      ];
    }

    // Default / Mitra Zaherba (7 kotak tetap utuh tidak diubah sama sekali)
    return [
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
  }, [isAliqa, kpi]);

  return (
    <div className="min-h-screen bg-background text-foreground">
      <Toaster position="top-right" richColors />
      <header className="sticky top-0 z-30 bg-white shadow-xs">
        {/* 1. TopBar Utama (Logo & Tombol Aksi di Atas) */}
        <TopBar
          seller={seller}
          sellersList={pageProps?.sellersList || []}
          onSeller={handleSellerChange}
          total={totalCount}
          month={month}
          monthPendingCounts={pageProps?.monthPendingCounts}
          trackingProgress={pageProps?.trackingProgress}
          googleSheetUrl={pageProps?.googleSheetUrl}
          googleSheetId={pageProps?.googleSheetId}
          googleSheetWebhookUrl={pageProps?.googleSheetWebhookUrl}
          onOpenPostOffices={() => setPostOfficesModalOpen(true)}
          currentUser={currentUser}
        />

        {/* 2. Bar Navigasi 12 Bulan (Di Bawah TopBar) */}
        <div className="border-b border-slate-200 bg-slate-50 px-4 py-2">
          <div className="flex w-full items-center gap-1.5 justify-between">
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
                      { seller, month: t.monthNum === "all" ? "ALL" : t.monthNum, color: colorFilter, search: query, sort, direction, overdue: isOverdue ? 1 : undefined },
                      { preserveState: true, preserveScroll: true }
                    );
                  }}
                  className={`relative flex items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold transition cursor-pointer whitespace-nowrap text-center ${
                    isAll ? "flex-[1.25]" : "flex-1"
                  } ${
                    active
                      ? "bg-[#1E40AF] text-white shadow-xs font-bold ring-1 ring-blue-700"
                      : "text-slate-600 bg-white hover:bg-slate-100 hover:text-slate-900 border border-slate-200"
                  }`}
                >
                  <span>{t.label}</span>
                  <span className={`text-[10px] rounded-md px-1.5 py-0.2 ${active ? "bg-white/20 text-white font-bold" : "bg-slate-100 text-slate-500 font-medium"}`}>
                    {nf(t.n)}
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      </header>

      <main className="space-y-4 p-5">
        {/* Overdue SLA Proactive Alert Banner */}
        {Number(pageProps?.stats?.overdue || 0) > 0 && !isOverdue && (
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-2xs">
            <div className="flex items-center gap-2.5">
              <div className="w-8 h-8 rounded-lg bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                <AlertTriangle className="w-4 h-4" />
              </div>
              <div>
                <h4 className="text-xs font-bold text-amber-950">
                  Peringatan SLA: Ada {nf(pageProps?.stats?.overdue || 0)} Paket Macet &gt; 4 Hari Belum Selesai!
                </h4>
                <p className="text-[11px] text-amber-800">
                  Paket belum berstatus final (Sukses/Retur) dan telah melebihi estimasi SLA antaran. Segera koordinasi atau eskalasi ke Kantor Pos tujuan.
                </p>
              </div>
            </div>
            <Button
              size="sm"
              onClick={() => {
                setIsOverdue(true);
                router.get("/shipments", { seller, month, color: colorFilter, search: query, sort, direction, overdue: 1 }, { preserveState: true, preserveScroll: true });
              }}
              className="bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shrink-0 cursor-pointer"
            >
              Filter {nf(pageProps?.stats?.overdue || 0)} Paket Macet &rarr;
            </Button>
          </div>
        )}

        {isOverdue && (
          <div className="bg-rose-50 border border-rose-200 rounded-xl p-3 flex items-center justify-between shadow-2xs">
            <div className="flex items-center gap-2 text-xs font-bold text-rose-950">
              <AlertTriangle className="w-4 h-4 text-rose-600 shrink-0" />
              <span>Menampilkan filter paket macet (&gt;4 hari lewat SLA).</span>
            </div>
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                setIsOverdue(false);
                router.get("/shipments", { seller, month, color: colorFilter, search: query, sort, direction }, { preserveState: true, preserveScroll: true });
              }}
              className="text-xs font-semibold text-rose-800 border-rose-300 hover:bg-rose-100 cursor-pointer"
            >
              Tampilkan Semua Paket
            </Button>
          </div>
        )}

        <section className={`grid gap-3 grid-cols-2 sm:grid-cols-3 ${isAliqa ? "lg:grid-cols-3 xl:grid-cols-6" : "lg:grid-cols-4 xl:grid-cols-7"}`}>
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
                    router.get("/shipments", { seller, month, search: query, sort, direction, overdue: isOverdue ? 1 : undefined }, { preserveState: true, preserveScroll: true });
                  } else if (c.colorKey) {
                    const nextColor = colorFilter === c.colorKey ? null : c.colorKey;
                    setColorFilter(nextColor);
                    router.get("/shipments", { seller, month, color: nextColor, search: query, sort, direction, overdue: isOverdue ? 1 : undefined }, { preserveState: true, preserveScroll: true });
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

        {/* Baris Filter Status CS & Ekspor */}
        <section className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/90 bg-white p-3.5 shadow-xs">
          <div className="flex flex-wrap items-center gap-2">
            <span className="mr-1 text-[11px] font-extrabold tracking-wider uppercase text-slate-500">
              Filter Status CS
            </span>
            {currentFuOrder.map((k) => {
              const active = colorFilter === k;
              return (
                <button
                  key={k}
                  onClick={() => {
                    const nextColor = active ? null : k;
                    setColorFilter(nextColor);
                    router.get(
                      "/shipments",
                      { seller, month, color: nextColor, search: query, sort, direction, overdue: isOverdue ? 1 : undefined },
                      { preserveState: true, preserveScroll: true }
                    );
                  }}
                  style={{ backgroundColor: currentFuMeta[k].bg, color: currentFuMeta[k].fg }}
                  className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[11px] font-extrabold transition-all transform cursor-pointer ${
                    active
                      ? "ring-2 ring-[#1E40AF] ring-offset-1 border-[#1E40AF] shadow-md scale-105"
                      : "border-black/20 hover:scale-102 hover:brightness-95 opacity-90 hover:opacity-100 shadow-2xs"
                  }`}
                >
                  {active && <Check className="h-3 w-3 text-current stroke-[3]" />}
                  {currentFuMeta[k].label}
                </button>
              );
            })}

            {/* Divider */}
            <div className="h-5 w-px bg-slate-200 mx-1 hidden sm:block" />

            {/* Tombol Filter Cepat "Lewat SLA / Macet > 4 Hari" */}
            <button
              type="button"
              onClick={() => {
                const nextOverdue = !isOverdue;
                setIsOverdue(nextOverdue);
                router.get(
                  "/shipments",
                  {
                    seller,
                    month,
                    color: colorFilter,
                    search: query,
                    sort,
                    direction,
                    overdue: nextOverdue ? 1 : undefined,
                  },
                  { preserveState: true, preserveScroll: true }
                );
              }}
              className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[11px] font-extrabold transition-all transform cursor-pointer ${
                isOverdue
                  ? "bg-rose-600 text-white border-rose-700 shadow-md ring-2 ring-rose-400 ring-offset-1 scale-105"
                  : "bg-amber-50 text-amber-900 border-amber-300/80 hover:bg-amber-100 hover:border-amber-400 hover:scale-102 shadow-2xs"
              }`}
              title="Tampilkan kiriman belum selesai (bukan Sukses/Retur) dengan tanggal kirim >= 4 hari lalu"
            >
              <AlertTriangle className={`h-3.5 w-3.5 shrink-0 ${isOverdue ? "text-white" : "text-amber-600"}`} />
              <span>Lewat SLA / Macet &gt; 4 Hari</span>
              {typeof pageProps?.stats?.overdue === "number" && (
                <span
                  className={`ml-1 rounded-full px-2 py-0.2 text-[10px] font-black ${
                    isOverdue ? "bg-white text-rose-700" : "bg-amber-200 text-amber-950"
                  }`}
                >
                  {nf(pageProps.stats.overdue)}
                </span>
              )}
            </button>

            {(colorFilter || isOverdue) && (
              <button
                onClick={() => {
                  setColorFilter(null);
                  setIsOverdue(false);
                  router.get(
                    "/shipments",
                    { seller, month, search: query, sort, direction },
                    { preserveState: true, preserveScroll: true }
                  );
                }}
                className="ml-1 inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-300/70 px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-200 transition cursor-pointer"
              >
                <X className="h-3 w-3" /> Reset Filter
              </button>
            )}
          </div>

          {isAdmin && (
            <div className="flex items-center">
              <Button
                onClick={() => setExportOpen(true)}
                className="gap-2 bg-[#1E40AF] text-white hover:bg-blue-900 cursor-pointer font-bold shadow-sm text-xs px-4 h-9 rounded-xl transition-all"
              >
                <FileSpreadsheet className="h-4 w-4 text-[#F97316]" />
                Export Laporan Seller {String(seller).replace("Mitra ", "")} (.XLSX)
              </Button>
            </div>
          )}
        </section>

        {/* Baris Pencarian, Mitra, & Sorting */}
        <section className="grid grid-cols-1 md:grid-cols-12 gap-3 rounded-2xl border border-slate-200/90 bg-white p-3.5 shadow-xs items-center">
          <div className="relative md:col-span-7 flex items-center gap-1.5">
            <div className="relative flex-1">
              <Search className="absolute top-3 left-3.5 h-4 w-4 text-slate-400 pointer-events-none" />
              <Input
                value={query}
                onChange={(e) => {
                  setQuery(e.target.value);
                }}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    router.get("/shipments", { seller, month, color: colorFilter, search: query, sort, direction, overdue: isOverdue ? 1 : undefined }, { preserveState: true, preserveScroll: true });
                  }
                }}
                placeholder="Cari nama penerima, nomor resi, no. HP, alamat tujuan... (tekan Enter)"
                className="h-10 pl-10 pr-9 text-xs bg-slate-50/50 border-slate-200 focus:bg-white focus:ring-1 focus:ring-blue-600 rounded-xl"
              />
              {query && (
                <button
                  type="button"
                  onClick={() => {
                    setQuery("");
                    router.get("/shipments", { seller, month, color: colorFilter, search: "", sort, direction, overdue: isOverdue ? 1 : undefined }, { preserveState: true, preserveScroll: true });
                  }}
                  className="absolute right-2.5 top-2.5 p-1 text-slate-400 hover:text-slate-600 rounded-md transition cursor-pointer"
                  title="Hapus pencarian"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              )}
            </div>
            <Button
              type="button"
              onClick={() => {
                router.get("/shipments", { seller, month, color: colorFilter, search: query, sort, direction, overdue: isOverdue ? 1 : undefined }, { preserveState: true, preserveScroll: true });
              }}
              className="h-10 px-4 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-2xs shrink-0 cursor-pointer"
            >
              Cari
            </Button>
          </div>
          <div className="md:col-span-3">
            <Select value={seller} onValueChange={handleSellerChange}>
              <SelectTrigger className="h-10 text-xs bg-white border border-slate-200 font-semibold shadow-2xs rounded-xl focus:ring-1 focus:ring-blue-600 cursor-pointer">
                <SelectValue>{seller}</SelectValue>
              </SelectTrigger>
              <SelectContent className="bg-white border border-slate-200 shadow-xl rounded-xl">
                <SelectItem value="Mitra Aliqa" className="font-semibold cursor-pointer text-xs">Mitra Aliqa</SelectItem>
                <SelectItem value="Mitra Zaherba" className="font-semibold cursor-pointer text-xs">Mitra Zaherba</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="md:col-span-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                if (sort === "sheet") {
                  handleSortChange("nama");
                } else {
                  handleSortChange("sheet");
                }
              }}
              className="w-full h-10 text-xs font-bold flex items-center justify-between border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-800 cursor-pointer shadow-2xs rounded-xl"
              title="Klik untuk mengubah urutan data (Default: Sesuai Spreadsheet)"
            >
              <span className="truncate">
                {sort === "sheet"
                  ? "Sesuai Sheet"
                  : sort === "nama"
                  ? direction === "asc"
                    ? "Nama A → Z"
                    : "Nama Z → A"
                  : sort}
              </span>
              <ArrowUpDown className="h-3.5 w-3.5 ml-1 text-blue-600 shrink-0" />
            </Button>
          </div>
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
              {!isAliqa && (
                <Button size="sm" variant="secondary" className="cursor-pointer bg-emerald-100 text-emerald-800 hover:bg-emerald-200 border border-emerald-300 font-bold text-xs" onClick={() => bulk("HIJAU")}>
                  Mark as FU 2 Kali
                </Button>
              )}
              <Button size="sm" variant="secondary" className="cursor-pointer bg-[#1E40AF] text-white hover:bg-blue-900 border border-blue-900 font-bold text-xs" onClick={() => bulk("BIRU_TUA")}>
                {isAliqa ? "Mark as ON FU POS" : "Mark as FU POS"}
              </Button>
              <Button size="sm" variant="secondary" className="cursor-pointer bg-orange-100 text-orange-800 hover:bg-orange-200 border border-orange-300 font-bold text-xs" onClick={() => bulk("ORANGE")}>
                Mark as Retur
              </Button>
              <Button size="sm" variant="secondary" className="cursor-pointer bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-bold text-xs" onClick={() => bulk("PUTIH")}>
                Mark as Belum FU
              </Button>
              <Button
                size="sm"
                variant="secondary"
                disabled={isTrackingSelected}
                className="cursor-pointer bg-indigo-600 text-white hover:bg-indigo-700 border border-indigo-700 font-bold text-xs gap-1"
                onClick={trackSelected}
              >
                {isTrackingSelected ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Bot className="h-3.5 w-3.5" />}
                Lacak NIPOS Terpilih
              </Button>
              {isAdmin ? (
                <>
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
                </>
              ) : null}
              <button
                className={`text-xs text-muted-foreground hover:text-foreground cursor-pointer font-medium ${!isAdmin ? "ml-auto" : ""}`}
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
          seller={seller}
          onSort={handleSortChange}
          sortField={sort}
          sortDirection={direction}
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
                router.get("/shipments", { seller, month, color: colorFilter, search: query, sort, direction, overdue: isOverdue ? 1 : undefined, page: currentPage - 1 }, { preserveState: true, preserveScroll: true });
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
                router.get("/shipments", { seller, month, color: colorFilter, search: query, sort, direction, overdue: isOverdue ? 1 : undefined, page: currentPage + 1 }, { preserveState: true, preserveScroll: true });
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
        seller={seller}
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
