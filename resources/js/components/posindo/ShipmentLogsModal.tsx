import { useState, useEffect } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import { History, User, Clock, Shield, Headphones, FileText, CheckCircle2 } from "lucide-react";
import axios from "axios";
import { type Shipment } from "@/lib/posindo";

export interface LogItem {
  id: number;
  action: string;
  note: string | null;
  created_at: string | null;
  user: {
    id: number;
    name: string;
    role: string;
  } | null;
}

interface Props {
  isOpen: boolean;
  onClose: () => void;
  shipment: Shipment | null;
}

export function ShipmentLogsModal({ isOpen, onClose, shipment }: Props) {
  const [logs, setLogs] = useState<LogItem[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (isOpen && shipment?.id) {
      setLoading(true);
      setLogs([]);
      axios
        .get(`/shipments/${shipment.id}/logs`, {
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
          withCredentials: true,
        })
        .then((res) => {
          if (res.data?.success) {
            setLogs(res.data.logs || []);
          }
        })
        .catch((err) => {
          // If redirect to login (HTML response), show session expired message
          const contentType = err.response?.headers?.["content-type"] || "";
          const isHtmlRedirect = contentType.includes("text/html");
          if (isHtmlRedirect || err.response?.status === 401 || err.response?.status === 302) {
            toast.error("Sesi telah berakhir. Silakan refresh halaman dan login ulang.");
          } else {
            const msg = err.response?.data?.message || err.message;
            toast.error("Gagal mengambil riwayat log resi: " + msg);
          }
        })
        .finally(() => {
          setLoading(false);
        });
    } else {
      setLogs([]);
    }
  }, [isOpen, shipment]);

  if (!shipment) return null;

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto p-6 rounded-2xl bg-white border border-slate-200">
        <DialogHeader className="border-b pb-3">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-slate-800 text-white flex items-center justify-center font-bold text-xl shadow-md">
              <History className="w-5 h-5 text-amber-400" />
            </div>
            <div>
              <DialogTitle className="text-lg font-bold text-slate-900 flex items-center gap-2">
                Riwayat Aktivitas &amp; Catatan CS
                <span className="text-xs font-mono font-bold px-2 py-0.5 bg-blue-100 text-blue-800 rounded-md">
                  {shipment.resi}
                </span>
              </DialogTitle>
              <p className="text-xs text-muted-foreground mt-0.5">
                Audit trail seluruh perubahan status, eskalasi, dan catatan yang dilakukan oleh tim CS &amp; Admin.
              </p>
            </div>
          </div>
        </DialogHeader>

        {/* Resi Summary Header */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
          <div>
            <span className="text-[10px] text-slate-400 uppercase font-bold block">Penerima</span>
            <span className="font-semibold text-slate-800 truncate block">{shipment.penerima}</span>
          </div>
          <div>
            <span className="text-[10px] text-slate-400 uppercase font-bold block">Status NIPOS</span>
            <span className="font-semibold text-slate-800 truncate block">{shipment.nipos}</span>
          </div>
          <div>
            <span className="text-[10px] text-slate-400 uppercase font-bold block">Status Warna FU</span>
            <span className="font-bold text-slate-800 truncate block">{shipment.fu}</span>
          </div>
          <div>
            <span className="text-[10px] text-slate-400 uppercase font-bold block">KC Tujuan</span>
            <span className="font-semibold text-slate-800 truncate block">{shipment.kantorTujuan || "-"}</span>
          </div>
        </div>

        {/* Timeline Log List */}
        <div className="py-2 space-y-3">
          {loading ? (
            <div className="py-12 text-center text-xs text-slate-400">
              Memuat riwayat aktivitas resi...
            </div>
          ) : logs.length === 0 ? (
            <div className="py-12 text-center text-xs text-slate-400 bg-slate-50 rounded-xl border border-dashed border-slate-200">
              <FileText className="w-8 h-8 text-slate-300 mx-auto mb-2" />
              Belum ada riwayat aktivitas atau catatan khusus untuk resi ini.
            </div>
          ) : (
            <div className="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
              {logs.map((log) => {
                const isAdmin = log.user?.role === "admin";
                const isStatusChange = log.action === "UPDATE_STATUS" || log.action === "UPDATE_COLOR";
                return (
                  <div key={log.id} className="relative group">
                    {/* Bullet marker */}
                    <div className="absolute -left-6 top-1 w-5 h-5 rounded-full bg-white border-2 border-slate-400 flex items-center justify-center group-hover:border-blue-600 transition-colors">
                      <div className="w-2 h-2 rounded-full bg-slate-600 group-hover:bg-blue-600 transition-colors" />
                    </div>

                    <div className="bg-slate-50/80 hover:bg-slate-50 p-3 rounded-xl border border-slate-200 transition-colors">
                      <div className="flex items-center justify-between gap-2 mb-1">
                        <div className="flex items-center gap-2">
                          <span
                            className={`text-[10px] font-bold px-2 py-0.5 rounded-md uppercase ${
                              isStatusChange
                                ? "bg-blue-100 text-blue-800 border border-blue-200"
                                : log.action === "DELETE"
                                ? "bg-red-100 text-red-800 border border-red-200"
                                : "bg-amber-100 text-amber-800 border border-amber-200"
                            }`}
                          >
                            {log.action}
                          </span>

                          <span className="text-xs font-semibold text-slate-800 flex items-center gap-1">
                            {isAdmin ? (
                              <Shield className="w-3 h-3 text-purple-600 inline" />
                            ) : (
                              <Headphones className="w-3 h-3 text-emerald-600 inline" />
                            )}
                            {log.user?.name || "Sistem / Otomatis"}
                          </span>
                        </div>

                        <span className="text-[11px] text-slate-400 flex items-center gap-1 font-mono">
                          <Clock className="w-3 h-3" />
                          {log.created_at || "-"}
                        </span>
                      </div>

                      <p className="text-xs text-slate-700 leading-relaxed font-medium">
                        {log.note || "Tidak ada catatan keterangan."}
                      </p>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        <DialogFooter className="border-t pt-3 flex justify-end">
          <Button variant="outline" size="sm" onClick={onClose} className="text-xs">
            Tutup
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
