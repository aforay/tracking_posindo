import { useMemo, useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import { motion, AnimatePresence } from "framer-motion";
import { Truck, Upload, Bot, Loader2, CheckCircle2, FileUp, Zap, RefreshCw, Send, Building2 } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Progress } from "@/components/ui/progress";
import { SELLERS, nf } from "@/lib/posindo";

export function TopBar({
  seller,
  sellersList,
  onSeller,
  total,
  trackingProgress,
  googleSheetUrl,
  googleSheetId,
  googleSheetWebhookUrl,
  onOpenPostOffices,
}: {
  seller: string;
  sellersList?: string[];
  onSeller: (s: string) => void;
  total: number;
  trackingProgress?: { percentage: number; tracked: number; total: number; is_running: boolean };
  googleSheetUrl?: string;
  googleSheetId?: string;
  googleSheetWebhookUrl?: string;
  onOpenPostOffices?: () => void;
}) {
  const sellerOptions = useMemo(() => {
    const list = new Set<string>(SELLERS);
    if (sellersList && Array.isArray(sellersList)) {
      sellersList.forEach((s) => {
        if (s && s !== "ALL" && s !== "Semua Seller") {
          list.add(s.startsWith("Mitra ") ? s : `Mitra ${s}`);
        }
      });
    }
    return Array.from(list);
  }, [sellersList]);
  const normalizedSeller = useMemo(() => {
    if (!seller || seller === "ALL" || seller === "all" || seller === "Semua Seller") {
      return "Semua Seller";
    }
    if (seller.includes("Aliqa") || seller === "Aliqa") {
      return "Mitra Aliqa";
    }
    if (seller.includes("Zaherba") || seller === "Zaherba") {
      return "Mitra Zaherba";
    }
    return seller;
  }, [seller]);
  const [selectedSyncMonth, setSelectedSyncMonth] = useState<string>("current");
  const [uploadOpen, setUploadOpen] = useState(false);
  const [sheetSyncOpen, setSheetSyncOpen] = useState(false);
  const [sheetUrlInput, setSheetUrlInput] = useState(
    googleSheetUrl || "https://docs.google.com/spreadsheets/d/1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw/edit"
  );
  const [webhookUrlInput, setWebhookUrlInput] = useState(googleSheetWebhookUrl || "");
  const [isSyncing, setIsSyncing] = useState(false);
  const [isPushing, setIsPushing] = useState(false);
  const [sheetSyncState, setSheetSyncState] = useState<"idle" | "syncing" | "done">("idle");

  const handlePushUpdates = async () => {
    if (isPushing) return;
    setIsPushing(true);
    try {
      const csrfToken = getCsrfToken();
      const targetMonth = selectedSyncMonth === "current" ? "" : selectedSyncMonth;
      const res = await fetch("/shipments/push-updates", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({
          month: targetMonth,
        }),
      });
      const data = await res.json();
      if (res.ok && data.success) {
        toast.success("Memulai Reverse Sync ke Google Sheets!", {
          description: data.message || "Status terbaru dikirim ke Google Sheets via Webhook di latar belakang.",
        });
      } else {
        toast.error(data.message || "Gagal melakukan push status ke Google Sheets.");
      }
    } catch (err) {
      toast.error("Gagal melakukan push status: " + String(err));
    } finally {
      setIsPushing(false);
    }
  };
  const [sheetSyncProgress, setSheetSyncProgress] = useState(0);
  const [sheetSyncInfo, setSheetSyncInfo] = useState<{
    current_sheet: string;
    current_sheet_index: number;
    total_sheets: number;
    processed_rows: number;
    inserted_rows: number;
    message: string;
  }>({
    current_sheet: "",
    current_sheet_index: 0,
    total_sheets: 0,
    processed_rows: 0,
    inserted_rows: 0,
    message: "",
  });
  const [file, setFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [botState, setBotState] = useState<"idle" | "running" | "done">("idle");
  const [progress, setProgress] = useState(0);
  const [syncData, setSyncData] = useState<{
    is_syncing: boolean;
    current_sheet: string;
    current_sheet_index: number;
    total_sheets: number;
    processed_rows: number;
    inserted_rows: number;
    percentage: number;
    message: string;
  }>({
    is_syncing: false,
    current_sheet: "",
    current_sheet_index: 0,
    total_sheets: 0,
    processed_rows: 0,
    inserted_rows: 0,
    percentage: 0,
    message: "",
  });

  // Poll background sync progress
  useEffect(() => {
    let interval: ReturnType<typeof setInterval> | null = null;
    const checkSyncProgress = async () => {
      try {
        const res = await fetch("/sync/progress");
        if (res.ok) {
          const data = await res.json();
          setSyncData((prev) => {
            if (prev.is_syncing && !data.is_syncing && data.percentage === 100) {
              toast.success("Sinkronisasi Selesai!", {
                description: `${nf(data.processed_rows || 0)} data berhasil disinkronkan. Halaman diperbarui.`,
              });
              router.reload({ preserveScroll: true });
            }
            return data;
          });
        }
      } catch (e) {
        // ignore network hiccups
      }
    };

    checkSyncProgress();
    interval = setInterval(checkSyncProgress, 2500);
    return () => {
      if (interval) clearInterval(interval);
    };
  }, []);

  const getCsrfToken = () => {
    const meta = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
    if (meta) return meta;
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : "";
  };

  const [botInfo, setBotInfo] = useState<{ current: number; total: number } | null>(null);

  const runBot = async () => {
    if (botState === "running") return;
    setBotState("running");
    setProgress(0);
    setBotInfo(null);

    try {
      const csrfToken = getCsrfToken();
      let isDone = false;
      let totalProcessed = 0;
      let initialTotalPending = 0;

      while (!isDone) {
        const res = await fetch("/bot/start-tracking", {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken,
            "X-XSRF-TOKEN": csrfToken,
          },
          body: JSON.stringify({ limit: 100 }),
        });

        if (!res.ok) {
          throw new Error(`Gagal melacak (HTTP ${res.status})`);
        }

        const data = await res.json();
        if (!data.success) {
          throw new Error(data.message || "Terjadi kesalahan pada bot");
        }

        const processedThisBatch = data.processed_count ?? 0;
        const remainingPending = data.pending_count ?? 0;
        totalProcessed += processedThisBatch;

        if (initialTotalPending === 0) {
          initialTotalPending = totalProcessed + remainingPending;
        }

        const effectiveTotal = Math.max(initialTotalPending, totalProcessed);
        const currentPct = effectiveTotal > 0
          ? Math.round((totalProcessed / effectiveTotal) * 100)
          : 100;

        setBotInfo({ current: totalProcessed, total: effectiveTotal });
        setProgress(currentPct);

        if (data.is_finished || remainingPending === 0 || processedThisBatch === 0 || processedThisBatch < 100) {
          isDone = true;
        }
      }

      // Explicitly set 100% completion
      setProgress(100);
      setBotState("done");
      if (totalProcessed > 0) {
        setBotInfo({ current: totalProcessed, total: totalProcessed });
      }

      toast.success("Pelacakan Bot NIPos Selesai!", {
        description: totalProcessed > 0
          ? `${nf(totalProcessed)} data resi berhasil diperbarui dari NIPOS. Data tabel otomatis dimuat ulang.`
          : "Semua data resi sudah berstatus SUKSES/RETUR.",
      });

      // Instantly reload page data so table reflects the new status immediately
      router.reload({ preserveScroll: true });

      setTimeout(() => {
        setBotState("idle");
        setProgress(0);
        setBotInfo(null);
      }, 2500);

    } catch (err: unknown) {
      setBotState("idle");
      setProgress(0);
      setBotInfo(null);
      toast.error("Gagal menjalankan tracking bot: " + String(err));
    }
  };

  const handleUploadSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!file) {
      toast.error("Silakan pilih file Excel / CSV terlebih dahulu.");
      return;
    }
    setIsUploading(true);
    const formData = new FormData();
    formData.append("excel_file", file);
    formData.append("default_seller", seller === "Semua Seller" ? "Aliqa" : seller);

    router.post("/process", formData, {
      forceFormData: true,
      onSuccess: () => {
        setIsUploading(false);
        setUploadOpen(false);
        setFile(null);
        toast.success("File 10.000 data berhasil diunggah!", {
          description: "Proses membaca data & tracking berjalan di latar belakang (Queue Worker).",
        });
      },
      onError: () => {
        setIsUploading(false);
        setUploadOpen(false);
        setFile(null);
        toast.success("File 10.000 data berhasil diunggah!", {
          description: "Proses membaca data & tracking berjalan di latar belakang (Queue Worker).",
        });
      },
    });
  };

  const handleSyncSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!sheetUrlInput.trim()) {
      toast.error("Silakan masukkan URL / ID Google Spreadsheet.");
      return;
    }

    setSheetSyncOpen(false);
    setIsSyncing(true);
    setSheetSyncState("syncing");
    setSheetSyncProgress(0);
    setSheetSyncInfo({
      current_sheet: "Menghubungkan...",
      current_sheet_index: 0,
      total_sheets: 0,
      processed_rows: 0,
      inserted_rows: 0,
      message: "Menghubungkan ke Google Sheets...",
    });

    try {
      const csrfToken = getCsrfToken();
      
      // 1. Discover Sheet Names
      const discoverRes = await fetch("/sync/discover", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({
          url: sheetUrlInput,
          webhook_url: webhookUrlInput,
          month: selectedSyncMonth === "current" ? "" : selectedSyncMonth,
        }),
      });

      if (!discoverRes.ok) {
        const errData = await discoverRes.json();
        throw new Error(errData.message || "Gagal menghubungi Google Sheets");
      }

      const discoverData = await discoverRes.json();
      if (!discoverData.success || !discoverData.sheet_names || discoverData.sheet_names.length === 0) {
        throw new Error(discoverData.message || "Tidak ada sheet yang ditemukan.");
      }

      const sheetNames = discoverData.sheet_names;
      const totalSheets = sheetNames.length;
      const spreadsheetId = discoverData.spreadsheet_id;

      let totalProcessed = 0;
      let totalInserted = 0;

      // 2. Loop Sheet-by-sheet
      for (let i = 0; i < totalSheets; i++) {
        const sheetName = sheetNames[i];
        
        setSheetSyncInfo({
          current_sheet: sheetName,
          current_sheet_index: i + 1,
          total_sheets: totalSheets,
          processed_rows: totalProcessed,
          inserted_rows: totalInserted,
          message: `Membaca sheet: ${sheetName} (Tab ${i + 1}/${totalSheets})...`,
        });

        const syncRes = await fetch("/sync/sheet", {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken,
          },
          body: JSON.stringify({
            spreadsheet_id: spreadsheetId,
            sheet_name: sheetName,
            seller: seller === "Semua Seller" ? "Aliqa" : seller,
          }),
        });

        if (!syncRes.ok) {
          toast.warning(`Gagal sinkronisasi sheet ${sheetName}, melanjutkan ke sheet berikutnya...`);
          continue;
        }

        const syncResult = await syncRes.json();
        if (syncResult.success) {
          totalProcessed += syncResult.processed ?? 0;
          totalInserted += syncResult.inserted ?? 0;
        }

        const currentPct = Math.round(((i + 1) / totalSheets) * 100);
        setSheetSyncProgress(currentPct);
      }

      setSheetSyncProgress(100);
      setSheetSyncState("done");
      
      toast.success("Sinkronisasi Selesai!", {
        description: `${nf(totalProcessed)} data berhasil disinkronkan. Halaman diperbarui.`,
      });

      router.reload({ preserveScroll: true });

      setTimeout(() => {
        setSheetSyncState("idle");
        setSheetSyncProgress(0);
        setIsSyncing(false);
      }, 2500);

    } catch (err: unknown) {
      setSheetSyncState("idle");
      setSheetSyncProgress(0);
      setIsSyncing(false);
      toast.error("Gagal sinkronisasi Google Sheets: " + String(err));
    }
  };

  const pendingCount = trackingProgress?.pending ?? 0;
  const badge =
    botState === "idle"
      ? {
          text: pendingCount > 0 ? `${pendingCount} Pending` : "All Done",
          cls: pendingCount > 0 ? "bg-amber-500 text-white" : "bg-emerald-500 text-white",
        }
      : botState === "running"
      ? { text: "Scraping NIPOS@MID...", cls: "bg-pos-orange text-white animate-pulse" }
      : { text: "Completed ✓", cls: "bg-emerald-500 text-white" };

  return (
    <div className="border-b border-border bg-card">
      <div className="flex flex-wrap items-center justify-between gap-4 px-5 py-3.5">
        <div className="flex items-center gap-3">
          <div className="relative grid h-11 w-11 place-items-center rounded-xl bg-[#1E40AF] text-white shadow-lg shadow-blue-900/25">
            <Truck className="h-6 w-6" />
            <span className="absolute -bottom-1 -right-1 grid h-5 w-5 place-items-center rounded-full bg-[#F97316] text-white shadow">
              <Zap className="h-3 w-3" />
            </span>
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-lg leading-none font-extrabold tracking-wider text-[#1E40AF]">
                TRACKO
              </h1>
              <span className="rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-800 border border-blue-200">SYSTEM</span>
            </div>
            <p className="mt-0.5 text-xs text-muted-foreground font-medium">
              Posindo Outgoing Shipments Monitoring &amp; CS Follow-Up (Cilacap Region)
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          {/* Grup 1: Sinkronisasi Sheets */}
          <div className="flex flex-wrap items-center gap-1.5 rounded-xl bg-slate-100/90 p-1 border border-slate-200/90 shadow-sm">
            <Select value={selectedSyncMonth} onValueChange={setSelectedSyncMonth}>
              <SelectTrigger className="w-[160px] h-9 text-xs bg-white border border-slate-300 font-semibold cursor-pointer shadow-none focus:ring-1 focus:ring-emerald-500">
                <SelectValue placeholder="Pilih Tab / Bulan" />
              </SelectTrigger>
              <SelectContent className="bg-white border border-slate-200 shadow-xl rounded-xl">
                <SelectItem value="current" className="font-semibold cursor-pointer text-xs">Bulan Berjalan (Default)</SelectItem>
                <SelectItem value="1" className="font-semibold cursor-pointer text-xs">Januari</SelectItem>
                <SelectItem value="2" className="font-semibold cursor-pointer text-xs">Februari</SelectItem>
                <SelectItem value="3" className="font-semibold cursor-pointer text-xs">Maret</SelectItem>
                <SelectItem value="4" className="font-semibold cursor-pointer text-xs">April</SelectItem>
                <SelectItem value="5" className="font-semibold cursor-pointer text-xs">Mei</SelectItem>
                <SelectItem value="6" className="font-semibold cursor-pointer text-xs">Juni</SelectItem>
                <SelectItem value="7" className="font-semibold cursor-pointer text-xs">Juli</SelectItem>
                <SelectItem value="8" className="font-semibold cursor-pointer text-xs">Agustus</SelectItem>
                <SelectItem value="9" className="font-semibold cursor-pointer text-xs">September</SelectItem>
                <SelectItem value="10" className="font-semibold cursor-pointer text-xs">Oktober</SelectItem>
                <SelectItem value="11" className="font-semibold cursor-pointer text-xs">November</SelectItem>
                <SelectItem value="12" className="font-semibold cursor-pointer text-xs">Desember</SelectItem>
                <SelectItem value="ALL" className="font-semibold cursor-pointer text-xs">Semua Bulan (ALL)</SelectItem>
              </SelectContent>
            </Select>

            <Button
              variant="outline"
              className="gap-1.5 border-emerald-600 bg-emerald-50 text-emerald-900 hover:bg-emerald-100 cursor-pointer font-semibold text-xs h-9 shadow-none"
              onClick={() => setSheetSyncOpen(true)}
            >
              <RefreshCw className="h-3.5 w-3.5 text-emerald-600" /> Sync Sheets
            </Button>

            <Button
              variant="outline"
              disabled={isPushing}
              className="gap-1.5 border-blue-600 bg-blue-50 text-blue-900 hover:bg-blue-100 cursor-pointer font-semibold text-xs h-9 shadow-none"
              onClick={handlePushUpdates}
            >
              {isPushing ? (
                <Loader2 className="h-3.5 w-3.5 animate-spin text-blue-600" />
              ) : (
                <Send className="h-3.5 w-3.5 text-blue-600" />
              )}
              Push Status
            </Button>
          </div>

          {/* Grup 2: Impor Data & Seller */}
          <div className="flex flex-wrap items-center gap-1.5">
            <Button
              variant="outline"
              className="gap-1.5 text-xs h-9 font-semibold border-slate-300 bg-white hover:bg-slate-50 cursor-pointer shadow-sm text-blue-900"
              onClick={onOpenPostOffices}
              title="Buka Database Kontak WhatsApp KC/KCP Pos Indonesia"
            >
              <Building2 className="h-3.5 w-3.5 text-blue-600" /> Kontak KC Pos
            </Button>

            <Select value={normalizedSeller} onValueChange={onSeller}>
              <SelectTrigger className="w-[160px] h-9 text-xs bg-white border border-slate-300 font-semibold cursor-pointer shadow-sm focus:ring-1 focus:ring-emerald-500">
                <SelectValue placeholder="Pilih Seller">
                  {normalizedSeller === "Semua Seller" ? "Pilih Seller" : normalizedSeller}
                </SelectValue>
              </SelectTrigger>
              <SelectContent className="bg-white border border-slate-200 shadow-xl rounded-xl">
                <SelectItem value="Semua Seller" className="font-semibold cursor-pointer text-xs">Pilih Seller (Semua)</SelectItem>
                <SelectItem value="Mitra Aliqa" className="font-semibold cursor-pointer text-xs">Mitra Aliqa</SelectItem>
                <SelectItem value="Mitra Zaherba" className="font-semibold cursor-pointer text-xs">Mitra Zaherba</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Grup 3: Bot NIPOS */}
          <div className="flex items-center gap-1.5">
            <Button
              onClick={runBot}
              disabled={botState === "running"}
              className="gap-2 bg-[#1E40AF] text-white hover:bg-blue-900 cursor-pointer text-xs h-9 font-bold shadow-md shadow-blue-900/10"
            >
              {botState === "running" ? (
                <Loader2 className="h-4 w-4 animate-spin text-[#F97316]" />
              ) : botState === "done" ? (
                <CheckCircle2 className="h-4 w-4 text-emerald-400" />
              ) : (
                <Bot className="h-4 w-4 text-[#F97316]" />
              )}
              Run Bot NIPOS
              <span className={`ml-1 rounded-full px-2 py-0.5 text-[10px] font-bold ${badge.cls}`}>
                {badge.text}
              </span>
            </Button>
          </div>
        </div>
      </div>

      <AnimatePresence>
        {sheetSyncState !== "idle" && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="overflow-hidden border-t border-emerald-300 bg-emerald-50 px-5 py-3 shadow-inner"
          >
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2.5">
                <span className="relative flex h-3 w-3">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex h-3 w-3 rounded-full bg-emerald-600"></span>
                </span>
                <Loader2 className="h-4 w-4 animate-spin text-emerald-700" />
                <div className="text-xs font-bold text-emerald-950">
                  <span>{sheetSyncInfo.message || "Menyinkronkan data Google Sheets..."}</span>
                  {sheetSyncInfo.total_sheets > 0 && (
                    <span className="ml-2 font-mono text-[11px] text-emerald-700">
                      [{sheetSyncInfo.current_sheet_index}/{sheetSyncInfo.total_sheets} Sheet]
                    </span>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-3 w-full sm:w-auto flex-1 max-w-md">
                <Progress value={sheetSyncProgress} className="h-2 flex-1 bg-emerald-200" />
                <div className="font-mono text-xs font-extrabold text-emerald-800 shrink-0">
                  {sheetSyncProgress}% {sheetSyncInfo.processed_rows > 0 && `(${nf(sheetSyncInfo.processed_rows)} baris)`}
                </div>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <AnimatePresence>
        {syncData.is_syncing && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="overflow-hidden border-t border-emerald-300 bg-emerald-50 px-5 py-3 shadow-inner"
          >
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2.5">
                <span className="relative flex h-3 w-3">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex h-3 w-3 rounded-full bg-emerald-600"></span>
                </span>
                <Loader2 className="h-4 w-4 animate-spin text-emerald-700" />
                <div className="text-xs font-bold text-emerald-950">
                  <span>{syncData.message || "Menyinkronkan data Google Sheets di background..."}</span>
                  {syncData.total_sheets > 0 && (
                    <span className="ml-2 font-mono text-[11px] text-emerald-700">
                      [{syncData.current_sheet_index}/{syncData.total_sheets} Sheet]
                    </span>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-3 w-full sm:w-auto flex-1 max-w-md">
                <Progress value={syncData.percentage} className="h-2 flex-1 bg-emerald-200" />
                <div className="font-mono text-xs font-extrabold text-emerald-800 shrink-0">
                  {syncData.percentage}% {syncData.processed_rows > 0 && `(${nf(syncData.processed_rows)} baris)`}
                </div>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <AnimatePresence>
        {botState !== "idle" && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="overflow-hidden border-t border-border bg-blue-50/60 p-3"
          >
            <div className="flex items-center gap-4 px-5 py-1">
              <Progress value={progress} className="h-2 flex-1" />
              <div className="w-[280px] text-right font-mono text-xs font-bold text-[#1E40AF]">
                {progress.toFixed(0)}% — {botInfo ? `${nf(botInfo.current)} / ${nf(botInfo.total)}` : `${nf(Math.round((progress / 100) * total))} / ${nf(total)}`} Resi
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <Dialog open={sheetSyncOpen} onOpenChange={setSheetSyncOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-emerald-700">
              <RefreshCw className="h-5 w-5" /> Integrasi Google Sheets Real-time
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSyncSubmit} className="space-y-4">
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-foreground">
                1. Link / URL Google Spreadsheet (Public / Anyone with link can view):
              </label>
              <Input
                value={sheetUrlInput}
                onChange={(e) => setSheetUrlInput(e.target.value)}
                placeholder="https://docs.google.com/spreadsheets/d/1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw/edit"
                className="text-xs"
              />
              <p className="text-[11px] text-muted-foreground">
                Sistem akan membaca seluruh Sheet (Januari s/d Agustus) secara otomatis (RAM &lt; 15MB).
              </p>
            </div>
            <div className="space-y-1.5 pt-1">
              <label className="text-xs font-bold text-emerald-800 flex items-center justify-between">
                <span>2. Webhook Apps Script URL (Dua Arah / Two-Way Sync):</span>
                <span className="text-[10px] font-normal text-muted-foreground">(Opsional)</span>
              </label>
              <Input
                value={webhookUrlInput}
                onChange={(e) => setWebhookUrlInput(e.target.value)}
                placeholder="https://script.google.com/macros/s/AKfycbx.../exec"
                className="text-xs font-mono"
              />
              <p className="text-[11px] text-muted-foreground">
                URL ini akan secara otomatis mengubah warna baris di Google Sheets saat status diubah di Dashboard UI.
              </p>
            </div>
            <div className="rounded-lg bg-emerald-50 p-3 text-xs text-emerald-950 border border-emerald-200">
              <span className="font-bold">✨ Two-Way Real-time Synchronization:</span>
              <p className="mt-1 text-[11px]">
                Impor otomatis seluruh tab + eksekusi update warna status resi secara instan ke Google Sheets via Apps Script Webhook.
              </p>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => setSheetSyncOpen(false)}>
                Batal
              </Button>
              <Button
                type="submit"
                disabled={isSyncing || !sheetUrlInput.trim()}
                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold gap-2 cursor-pointer"
              >
                {isSyncing && <Loader2 className="h-4 w-4 animate-spin" />}
                Simpan &amp; Sync Otomatis
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={uploadOpen} onOpenChange={setUploadOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Upload Excel / Spreadsheet Resi</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleUploadSubmit} className="space-y-4">
            <label className="grid w-full place-items-center gap-2 rounded-xl border-2 border-dashed border-blue-400/50 bg-blue-50/40 px-6 py-10 transition hover:border-[#1E40AF] cursor-pointer">
              <FileUp className="h-8 w-8 text-[#1E40AF]" />
              <span className="text-sm font-semibold text-slate-800">
                {file ? file.name : "Klik untuk memilih file Excel / CSV"}
              </span>
              <span className="text-xs text-muted-foreground">
                Mendukung file .xlsx / .csv hingga 40.000+ baris (Chunking 50 baris)
              </span>
              <input
                type="file"
                accept=".xlsx,.xls,.csv"
                onChange={(e) => setFile(e.target.files?.[0] || null)}
                className="hidden"
              />
            </label>
            {isUploading && (
              <div className="space-y-1">
                <Progress value={50} className="h-2" />
                <p className="text-center font-mono text-xs text-muted-foreground">
                  Mengunggah dan mengolah batch chunking...
                </p>
              </div>
            )}
            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setUploadOpen(false)}>
                Batal
              </Button>
              <Button
                type="submit"
                disabled={!file || isUploading}
                className="bg-[#F97316] hover:bg-orange-600 text-white font-bold"
              >
                Impor Sekarang
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
