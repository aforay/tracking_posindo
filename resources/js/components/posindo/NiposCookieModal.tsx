import { useState, useEffect } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "sonner";
import { Cookie, CheckCircle2, AlertCircle, RefreshCw, Satellite, Sparkles, KeyRound } from "lucide-react";

interface Props {
  isOpen: boolean;
  onClose: () => void;
  onStatusChange?: (connected: boolean) => void;
}

export function NiposCookieModal({ isOpen, onClose, onStatusChange }: Props) {
  const [cookie, setCookie] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [isTesting, setIsTesting] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [status, setStatus] = useState<{
    connected: boolean;
    message: string;
    latency_ms: number | null;
    status_code: number | null;
  } | null>(null);

  const getCsrfToken = () => {
    return (
      document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
      (document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
        ? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)![1])
        : "")
    );
  };

  const fetchStatus = async () => {
    setIsLoading(true);
    try {
      const res = await fetch("/settings/nipos-cookie/status");
      const data = await res.json();
      if (data.status) {
        setStatus(data.status);
        if (onStatusChange) {
          onStatusChange(Boolean(data.status.connected));
        }
      }
    } catch (err) {
      console.error("Gagal mengambil status cookie NIPOS:", err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    if (isOpen) {
      void fetchStatus();
    }
  }, [isOpen]);

  const handleTest = async () => {
    setIsTesting(true);
    try {
      const res = await fetch("/settings/nipos-cookie/test", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": getCsrfToken(),
        },
        body: JSON.stringify({ cookie: cookie.trim() || undefined }),
      });
      const data = await res.json();
      setStatus({
        connected: Boolean(data.connected),
        message: data.message || (data.connected ? "Terhubung ke NIPOS" : "Koneksi gagal"),
        latency_ms: data.latency_ms ?? null,
        status_code: data.status_code ?? null,
      });

      if (data.connected) {
        toast.success("Koneksi NIPOS Terhubung!", {
          description: `${data.message} (${data.latency_ms || 0}ms)`,
        });
        if (onStatusChange) onStatusChange(true);
      } else {
        toast.error("Koneksi Gagal / Expired", {
          description: data.message,
        });
        if (onStatusChange) onStatusChange(false);
      }
    } catch (err: any) {
      toast.error("Terjadi kesalahan saat menguji: " + (err?.message || err));
    } finally {
      setIsTesting(false);
    }
  };

  const handleSave = async () => {
    if (!cookie.trim()) {
      toast.error("Kolom cookie tidak boleh kosong!");
      return;
    }

    setIsSaving(true);
    try {
      const res = await fetch("/settings/nipos-cookie", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": getCsrfToken(),
        },
        body: JSON.stringify({ cookie: cookie.trim() }),
      });
      const data = await res.json();
      if (data.success) {
        toast.success("Cookie NIPOS Berhasil Disimpan ke Database!", {
          description: data.message,
        });
        if (data.test_result) {
          setStatus(data.test_result);
          if (onStatusChange) onStatusChange(Boolean(data.test_result.connected));
        }
        setCookie("");
      } else {
        toast.error(data.message || "Gagal menyimpan cookie.");
      }
    } catch (err: any) {
      toast.error("Terjadi kesalahan saat menyimpan: " + (err?.message || err));
    } finally {
      setIsSaving(false);
    }
  };

  const isConnected = Boolean(status?.connected);

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-xl bg-white border border-slate-200 shadow-2xl rounded-2xl p-6">
        <DialogHeader>
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-amber-500/10 text-amber-600 border border-amber-500/20">
              <Cookie className="h-6 w-6" />
            </div>
            <div>
              <DialogTitle className="text-base font-bold text-slate-900">
                Pengaturan Session Cookie NIPOS
              </DialogTitle>
              <DialogDescription className="text-xs text-slate-500">
                Simpan & validasi cookie sesi Pos Indonesia langsung ke basis data MySQL
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div className="space-y-4 my-2">
          {/* Status Badge Box */}
          <div
            className={`p-4 rounded-xl border transition-all ${
              isConnected
                ? "bg-emerald-50 border-emerald-200 text-emerald-950"
                : "bg-rose-50 border-rose-200 text-rose-950"
            }`}
          >
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <span className="relative flex h-3 w-3">
                  <span
                    className={`animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 ${
                      isConnected ? "bg-emerald-400" : "bg-rose-400"
                    }`}
                  />
                  <span
                    className={`relative inline-flex rounded-full h-3 w-3 ${
                      isConnected ? "bg-emerald-600" : "bg-rose-600"
                    }`}
                  />
                </span>
                <span className="text-xs font-extrabold uppercase tracking-wide">
                  {isConnected ? "NIPOS ONLINE (TERHUBUNG)" : "NIPOS OFFLINE / EXPIRED"}
                </span>
              </div>
              {status?.latency_ms !== null && status?.latency_ms !== undefined && (
                <span className="text-[11px] font-bold px-2 py-0.5 rounded bg-white/70 shadow-xs border border-current/20">
                  {status.latency_ms} ms
                </span>
              )}
            </div>
            <p className="text-xs mt-1.5 opacity-85 leading-relaxed">
              {status?.message || "Status koneksi belum diuji. Klik tombol uji untuk mengecek."}
            </p>
          </div>

          {/* Form Input */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <label className="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                <KeyRound className="h-3.5 w-3.5 text-blue-600" />
                Input Cookie NIPOS Baru
              </label>
              <span className="text-[11px] text-slate-400">
                Contoh: PHPSESSID=...; TS01...
              </span>
            </div>
            <Textarea
              value={cookie}
              onChange={(e) => setCookie(e.target.value)}
              placeholder="Tempel string Cookie dari Request Header browser (PHPSESSID=...; TS011d97f9=...)"
              className="text-xs font-mono min-h-[90px] bg-slate-50 border-slate-300 resize-y leading-relaxed"
            />
          </div>

          {/* Info Notes */}
          <div className="text-[11px] text-slate-500 bg-slate-50 rounded-xl p-3 border border-slate-200/80 space-y-1">
            <div className="font-semibold text-slate-700 flex items-center gap-1">
              <Sparkles className="h-3.5 w-3.5 text-amber-500" />
              Petunjuk Cepat:
            </div>
            <p>
              Buka portal Lacak NIPOS &rarr; Inspect Element (F12) &rarr; Network &rarr; Cari request URL &rarr; Salin nilai pada header <code className="font-mono bg-slate-200 px-1 py-0.5 rounded text-slate-800">Cookie:</code>.
            </p>
          </div>
        </div>

        <div className="flex items-center justify-between pt-2 border-t border-slate-100">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={handleTest}
            disabled={isTesting || isLoading}
            className="text-xs font-bold gap-1.5 border-slate-300 hover:bg-slate-100 text-slate-700 cursor-pointer"
          >
            {isTesting ? (
              <RefreshCw className="h-3.5 w-3.5 animate-spin" />
            ) : (
              <Satellite className="h-3.5 w-3.5 text-blue-600" />
            )}
            {isTesting ? "Menguji..." : "Test Koneksi"}
          </Button>

          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={onClose}
              className="text-xs font-semibold text-slate-600 cursor-pointer"
            >
              Batal
            </Button>
            <Button
              type="button"
              size="sm"
              onClick={handleSave}
              disabled={isSaving || !cookie.trim()}
              className="text-xs font-bold gap-1.5 bg-[#1E40AF] hover:bg-blue-900 text-white cursor-pointer shadow-sm"
            >
              {isSaving ? (
                <RefreshCw className="h-3.5 w-3.5 animate-spin" />
              ) : (
                <CheckCircle2 className="h-3.5 w-3.5 text-orange-400" />
              )}
              {isSaving ? "Menyimpan..." : "Simpan Cookie"}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
