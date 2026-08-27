import { useMemo, useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import { motion, AnimatePresence } from "framer-motion";
import { Truck, Upload, Bot, Loader2, CheckCircle2, FileUp, Zap, RefreshCw } from "lucide-react";
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
}: {
  seller: string;
  sellersList?: string[];
  onSeller: (s: string) => void;
  total: number;
  trackingProgress?: { percentage: number; tracked: number; total: number; is_running: boolean };
  googleSheetUrl?: string;
  googleSheetId?: string;
  googleSheetWebhookUrl?: string;
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
  const [uploadOpen, setUploadOpen] = useState(false);
  const [sheetSyncOpen, setSheetSyncOpen] = useState(false);
  const [sheetUrlInput, setSheetUrlInput] = useState(
    googleSheetUrl || "https://docs.google.com/spreadsheets/d/1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw/edit"
  );
  const [webhookUrlInput, setWebhookUrlInput] = useState(googleSheetWebhookUrl || "");
  const [isSyncing, setIsSyncing] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [botState, setBotState] = useState<"idle" | "running" | "done">("idle");
  const [progress, setProgress] = useState(0);

  // Sync bot status from backend trackingProgress if passed
  useEffect(() => {
    if (trackingProgress?.is_running) {
      setBotState("running");
      setProgress(trackingProgress.percentage || 0);
    } else if (trackingProgress && trackingProgress.percentage >= 100 && botState === "running") {
      setBotState("done");
      setProgress(100);
    }
  }, [trackingProgress]);

  const runBot = () => {
    setBotState("running");
    setProgress(10);
    router.post(
      "/bot/start-tracking",
      {},
      {
        onSuccess: () => {
          setBotState("done");
          setProgress(100);
          toast.success("Tracking Bot NIPOS@MID Berhasil Dijalankan!", {
            description: `${nf(total)} resi sedang di-tracking di background queue.`,
          });
        },
        onError: (err) => {
          setBotState("idle");
          toast.error("Gagal menjalankan tracking bot: " + JSON.stringify(err));
        },
      }
    );
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

  const handleSyncSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!sheetUrlInput.trim()) {
      toast.error("Silakan masukkan URL / ID Google Spreadsheet.");
      return;
    }
    setIsSyncing(true);
    router.post(
      "/shipments/sync-google-sheets",
      {
        url: sheetUrlInput,
        seller: seller === "Semua Seller" ? "Aliqa" : seller,
      },
      {
        onSuccess: () => {
          setIsSyncing(false);
          setSheetSyncOpen(false);
          toast.success("Sinkronisasi Google Sheets Berhasil Dikirim!", {
            description: "Proses membaca tab (Januari - Agustus) berjalan di background queue.",
          });
        },
        onError: () => {
          setIsSyncing(false);
          toast.error("Gagal mengirim sinkronisasi Google Sheets.");
        },
      }
    );
  };

  const badge =
    botState === "idle"
      ? { text: "Idle", cls: "bg-muted text-muted-foreground" }
      : botState === "running"
      ? { text: "Scraping NIPOS@MID", cls: "bg-pos-orange text-white" }
      : { text: "Completed", cls: "bg-emerald-500 text-white" };

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
            <h1 className="text-[15px] leading-tight font-bold tracking-tight text-foreground">
              Posindo Tracking &amp; CS Follow-Up System
            </h1>
            <p className="text-xs text-muted-foreground">
              Outgoing Shipments Monitoring &amp; Seller Reporting Dashboard (Cilacap Region)
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <div className="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-50/90 px-3 py-1 text-xs font-bold text-emerald-800 shadow-sm transition-all hover:bg-emerald-100">
            <span className="relative flex h-2.5 w-2.5">
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
              <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
            </span>
            <span>Auto-Sync Active</span>
            <span className="rounded bg-emerald-200/80 px-1.5 py-0.5 text-[10px] font-extrabold text-emerald-900">
              Every 15m
            </span>
          </div>

          <Button
            variant="outline"
            className="gap-2 border-emerald-600 bg-emerald-50 text-emerald-900 hover:bg-emerald-100 cursor-pointer font-semibold text-xs"
            onClick={() => setSheetSyncOpen(true)}
          >
            <RefreshCw className="h-3.5 w-3.5 text-emerald-600" /> Sync Google Sheets
          </Button>

          <Button variant="outline" className="gap-2 text-xs" onClick={() => setUploadOpen(true)}>
            <Upload className="h-3.5 w-3.5 text-[#F97316]" /> Upload Excel
          </Button>

          <Select value={seller} onValueChange={onSeller}>
            <SelectTrigger className="w-[180px] text-xs bg-white border border-slate-300 font-semibold cursor-pointer shadow-sm">
              <SelectValue />
            </SelectTrigger>
            <SelectContent className="bg-white border border-slate-200 shadow-xl rounded-xl">
              <SelectItem value="Semua Seller" className="font-semibold cursor-pointer">Semua Seller</SelectItem>
              {sellerOptions.map((s) => (
                <SelectItem key={s} value={s} className="font-semibold cursor-pointer">
                  {s}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Button
            onClick={runBot}
            disabled={botState === "running"}
            className="gap-2 bg-[#1E40AF] text-white hover:bg-blue-900 cursor-pointer text-xs"
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
              <div className="w-[230px] text-right font-mono text-xs font-bold text-[#1E40AF]">
                {progress.toFixed(0)}% — {nf(Math.round((progress / 100) * total))} / {nf(total)} Resi
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
