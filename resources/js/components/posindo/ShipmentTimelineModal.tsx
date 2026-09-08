import { useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import {
  Package,
  Truck,
  MapPin,
  CheckCircle2,
  AlertTriangle,
  RotateCcw,
  Clock,
  Building2,
  ExternalLink,
  RefreshCw,
  Copy,
  MessageSquare,
  ShieldCheck,
  User,
  Phone,
} from "lucide-react";
import { type Shipment, formatDate } from "@/lib/posindo";
import { router } from "@inertiajs/react";

interface Props {
  isOpen: boolean;
  onClose: () => void;
  shipment: Shipment | null;
  onOpenWhatsApp?: (shipment: Shipment) => void;
}

export function ShipmentTimelineModal({
  isOpen,
  onClose,
  shipment,
  onOpenWhatsApp,
}: Props) {
  const [trackingLoading, setTrackingLoading] = useState(false);

  if (!shipment) return null;

  const niposUpper = (shipment.nipos || "").toUpperCase();
  const isRetur = shipment.fu === "ORANGE" || niposUpper.includes("RETURN") || niposUpper.includes("RETUR");
  const isDelivered = !isRetur && (shipment.fu === "BIRU" || niposUpper === "DELIVERED");
  const isFailed = niposUpper.includes("FAILED") || niposUpper.includes("GAGAL") || niposUpper.includes("KENDALA");

  const handleTrackLive = async () => {
    try {
      setTrackingLoading(true);
      const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
        (document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
          ? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)![1])
          : "");

      const res = await fetch(`/shipments/${shipment.id}/track`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-XSRF-TOKEN": csrfToken,
        },
      });
      const data = await res.json();
      if (data.success) {
        toast.success("Status NIPOS Terkini Berhasil Diambil!", {
          description: data.shipment?.status_pos || "Data pelacakan terbaru telah diperbarui.",
        });
        router.reload({ preserveScroll: true });
        onClose();
      } else {
        toast.error(data.message || "Gagal memperbarui status NIPOS");
      }
    } catch (err: any) {
      toast.error("Gagal melacak resi: " + (err?.message || err));
    } finally {
      setTrackingLoading(false);
    }
  };

  const copyResi = () => {
    void navigator.clipboard?.writeText(shipment.resi);
    toast.success("Nomor Resi Disalin", { description: shipment.resi });
  };

  const encryptedResi = encodeURIComponent(btoa(shipment.resi));
  const externalNiposUrl = `https://pid.posindonesia.co.id/lacak/admin/detail_lacak_banyak.php?id=${encryptedResi}`;

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto p-6 rounded-2xl bg-white border border-slate-200">
        <DialogHeader className="border-b pb-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-[#1E40AF] text-white flex items-center justify-center font-bold text-xl shadow-md">
                <Package className="w-5 h-5" />
              </div>
              <div>
                <DialogTitle className="text-lg font-bold text-slate-900 flex items-center gap-2">
                  Timeline Pelacakan Resi Pos
                  <span className="text-xs font-mono font-bold px-2 py-0.5 bg-blue-100 text-blue-900 rounded-md">
                    {shipment.resi}
                  </span>
                </DialogTitle>
                <p className="text-xs text-muted-foreground mt-0.5">
                  Rincian milestone perjalanan paket berdasarkan pembacaan sistem NIPOS Pos Indonesia.
                </p>
              </div>
            </div>

            <Button
              variant="outline"
              size="sm"
              onClick={copyResi}
              className="text-xs flex items-center gap-1.5"
            >
              <Copy className="w-3.5 h-3.5" />
              Salin
            </Button>
          </div>
        </DialogHeader>

        {/* Header Status Card */}
        <div className="p-4 rounded-xl border bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
              Status Akhir Sistem Pos
            </span>
            <div className="flex items-center gap-2 mt-0.5">
              <span
                className={`text-sm font-bold px-2.5 py-0.5 rounded-lg inline-flex items-center gap-1.5 ${
                  isDelivered
                    ? "bg-emerald-100 text-emerald-800 border border-emerald-300"
                    : isRetur
                    ? "bg-amber-100 text-amber-900 border border-amber-300"
                    : isFailed
                    ? "bg-rose-100 text-rose-800 border border-rose-300"
                    : "bg-blue-100 text-blue-800 border border-blue-300"
                }`}
              >
                {isDelivered && <CheckCircle2 className="w-4 h-4 text-emerald-600" />}
                {isRetur && <RotateCcw className="w-4 h-4 text-amber-600" />}
                {isFailed && <AlertTriangle className="w-4 h-4 text-rose-600" />}
                {!isDelivered && !isRetur && !isFailed && <Truck className="w-4 h-4 text-blue-600" />}
                {shipment.nipos || "ON PROCESS"}
              </span>

              <span className="text-xs font-semibold px-2 py-0.5 rounded bg-white text-slate-700 border border-slate-200">
                Warna FU: <strong>{shipment.fu}</strong>
              </span>
            </div>

            {shipment.keterangan && shipment.keterangan !== "-" && (
              <p className="text-xs text-slate-600 mt-1 font-medium italic">
                "{shipment.keterangan}"
              </p>
            )}
          </div>

          <div className="sm:text-right text-xs space-y-0.5 border-t sm:border-t-0 pt-2 sm:pt-0">
            <div className="text-slate-500">
              SLA Kiriman: <strong className="text-slate-800">{shipment.sla} Hari</strong>
            </div>
            <div className="text-slate-500">
              Terakhir Terlacak:{" "}
              <span className="font-mono font-semibold text-slate-700">
                {shipment.lastTrackedAt || "Belum ada riwayat"}
              </span>
            </div>
          </div>
        </div>

        {/* Sender & Receiver Info */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
          <div className="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
              Pengirim &amp; Kantor Pos Tujuan
            </span>
            <div className="flex items-center gap-2">
              <Building2 className="w-3.5 h-3.5 text-blue-600 shrink-0" />
              <span className="font-semibold text-slate-800">{shipment.seller}</span>
            </div>
            <div className="flex items-center gap-2">
              <MapPin className="w-3.5 h-3.5 text-rose-500 shrink-0" />
              <span className="font-semibold text-slate-800">
                {(shipment.kantorTujuan && !['KC PENGANTARAN', 'KC POS PENGANTARAN', 'KC TUJUAN', 'POS PENGANTARAN'].includes(shipment.kantorTujuan.toUpperCase())) ? shipment.kantorTujuan : "-"}
              </span>
            </div>
            <div className="flex items-center gap-2 text-slate-500">
              <Clock className="w-3.5 h-3.5 shrink-0" />
              <span>Tgl Kirim: <strong>{formatDate(shipment.tanggalKirim)}</strong></span>
            </div>
          </div>

          <div className="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">
              Penerima / Pembeli
            </span>
            <div className="flex items-center gap-2">
              <User className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
              <span className="font-bold text-slate-800">{shipment.penerima}</span>
            </div>
            <div className="flex items-center gap-2">
              <Phone className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
              <span className="font-mono font-semibold text-slate-700">{shipment.telepon || "-"}</span>
            </div>
            <div className="flex items-start gap-2 text-slate-600 leading-snug">
              <MapPin className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
              <span className="line-clamp-2" title={shipment.alamat}>{shipment.alamat}</span>
            </div>
          </div>
        </div>

        {/* Milestone Timeline */}
        <div className="space-y-3 pt-2">
          <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">
            Milestone Perjalanan Paket
          </h4>

          <div className="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-blue-200">
            {/* Step 1: Manifest */}
            <div className="relative">
              <div className="absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-xs">
                <CheckCircle2 className="w-3 h-3" />
              </div>
              <div className="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-slate-900">1. Paket Diterima Loket / Manifest POS</span>
                  <span className="text-[11px] font-mono text-slate-500">{formatDate(shipment.tanggalKirim)}</span>
                </div>
                <p className="text-xs text-slate-600 mt-0.5">
                  Paket terdata di sistem Pos Indonesia oleh seller <strong>{shipment.seller}</strong>.
                </p>
              </div>
            </div>

            {/* Step 2: In Location / Transit */}
            <div className="relative">
              <div className="absolute -left-6 top-0.5 w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-xs">
                <Truck className="w-3 h-3" />
              </div>
              <div className="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-slate-900">2. Proses Logistik Antar Kota (Transit / In Vehicle)</span>
                  <span className="text-[11px] font-mono text-slate-500">Dalam Perjalanan</span>
                </div>
                <p className="text-xs text-slate-600 mt-0.5">
                  Paket diproses melalui Sentral Pengolahan Pos (SPP / DC) menuju kota tujuan.
                </p>
              </div>
            </div>

            {/* Step 3: Tiba di KC Tujuan */}
            <div className="relative">
              <div
                className={`absolute -left-6 top-0.5 w-5 h-5 rounded-full flex items-center justify-center shadow-xs ${
                  shipment.nipos === "ON PROCESS" ? "bg-slate-300 text-slate-600" : "bg-blue-600 text-white"
                }`}
              >
                <Building2 className="w-3 h-3" />
              </div>
              <div className="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-slate-900">
                    3. Tiba di Kantor Pos Tujuan &amp; Antaran Kurir
                  </span>
                  <span className="text-[11px] font-semibold text-blue-700">
                    {shipment.kantorTujuan || "KC Pos Tujuan"}
                  </span>
                </div>
                <p className="text-xs text-slate-600 mt-0.5">
                  Paket diturunkan di Kantor Pos tujuan dan dimasukkan ke dalam daftar antaran kurir (Runsheet).
                </p>
              </div>
            </div>

            {/* Step 4: Status Akhir */}
            <div className="relative">
              <div
                className={`absolute -left-6 top-0.5 w-5 h-5 rounded-full flex items-center justify-center shadow-xs text-white ${
                  isDelivered
                    ? "bg-emerald-600"
                    : isRetur
                    ? "bg-amber-600"
                    : isFailed
                    ? "bg-rose-600"
                    : "bg-slate-400"
                }`}
              >
                {isDelivered ? (
                  <CheckCircle2 className="w-3 h-3" />
                ) : isRetur ? (
                  <RotateCcw className="w-3 h-3" />
                ) : (
                  <Clock className="w-3 h-3" />
                )}
              </div>
              <div
                className={`p-3 rounded-xl border shadow-2xs ${
                  isDelivered
                    ? "bg-emerald-50/50 border-emerald-200"
                    : isRetur
                    ? "bg-amber-50/50 border-amber-200"
                    : isFailed
                    ? "bg-rose-50/50 border-rose-200"
                    : "bg-white border-slate-200"
                }`}
              >
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-slate-900">
                    4. {isDelivered ? "Paket Berhasil Diterima" : isRetur ? "Paket Dikembalikan (Retur)" : isFailed ? "Kendala Antaran / Gagal Serah" : "Menunggu Hasil Pengantaran"}
                  </span>
                  <span className="text-[11px] font-mono text-slate-600">
                    {shipment.lastTrackedAt || "-"}
                  </span>
                </div>
                <p className="text-xs text-slate-700 mt-0.5 font-medium">
                  {shipment.keterangan || (isDelivered ? "Paket sukses diserahkan ke penerima." : "Paket sedang dalam antaran.")}
                </p>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter className="border-t pt-3 flex flex-col sm:flex-row items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <a
              href={externalNiposUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="text-xs text-blue-700 hover:text-blue-900 font-semibold flex items-center gap-1 hover:underline"
            >
              <ExternalLink className="w-3.5 h-3.5" />
              Buka Web NIPOS Eksternal
            </a>
          </div>

          <div className="flex items-center gap-2">
            {onOpenWhatsApp && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => {
                  onClose();
                  onOpenWhatsApp(shipment);
                }}
                className="text-xs flex items-center gap-1.5 text-emerald-700 border-emerald-300 hover:bg-emerald-50"
              >
                <MessageSquare className="w-3.5 h-3.5" />
                Follow-Up WA
              </Button>
            )}

            <Button
              type="button"
              size="sm"
              onClick={handleTrackLive}
              disabled={trackingLoading}
              className="bg-[#1E40AF] hover:bg-blue-900 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${trackingLoading ? "animate-spin" : ""}`} />
              {trackingLoading ? "Melacak..." : "Lacak Sekarang"}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
