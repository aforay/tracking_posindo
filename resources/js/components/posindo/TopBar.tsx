import { useEffect, useRef, useState } from "react";
import { router } from "@inertiajs/react";
import { motion, AnimatePresence } from "framer-motion";
import { Truck, Upload, Bot, Loader2, CheckCircle2, FileUp, Zap } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
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
  onSeller,
  total,
  trackingProgress,
}: {
  seller: string;
  onSeller: (s: string) => void;
  total: number;
  trackingProgress?: { percentage: number; tracked: number; total: number; is_running: boolean };
}) {
  const [uploadOpen, setUploadOpen] = useState(false);
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
        toast.success("Spreadsheet Berhasil Diimpor!", {
          description: "Data resi diproses dengan chunking batch 50 baris.",
        });
      },
      onError: (errors) => {
        setIsUploading(false);
        toast.error("Gagal mengimpor file: " + Object.values(errors).join(", "));
      },
    });
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
          <Button variant="outline" className="gap-2" onClick={() => setUploadOpen(true)}>
            <Upload className="h-4 w-4 text-[#F97316]" /> Upload Excel/Spreadsheet
          </Button>

          <Select value={seller} onValueChange={onSeller}>
            <SelectTrigger className="w-[190px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="Semua Seller">Semua Seller</SelectItem>
              {SELLERS.map((s) => (
                <SelectItem key={s} value={s}>
                  {s}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Button
            onClick={runBot}
            disabled={botState === "running"}
            className="gap-2 bg-[#1E40AF] text-white hover:bg-blue-900 cursor-pointer"
          >
            {botState === "running" ? (
              <Loader2 className="h-4 w-4 animate-spin text-[#F97316]" />
            ) : botState === "done" ? (
              <CheckCircle2 className="h-4 w-4 text-emerald-400" />
            ) : (
              <Bot className="h-4 w-4 text-[#F97316]" />
            )}
            Run Automatic Tracking Bot (NIPOS)
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
