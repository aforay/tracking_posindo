import { useState, useEffect, useMemo } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { toast } from "sonner";
import {
  MessageSquare,
  ExternalLink,
  Copy,
  Building2,
  Phone,
  User,
  MapPin,
  Package,
  AlertCircle,
  CheckCircle2,
} from "lucide-react";
import {
  type Shipment,
  type PostOffice,
  type FuStatus,
  type WaTemplateType,
  WA_TEMPLATES,
  generatePostOfficeWaMessage,
} from "@/lib/posindo";

interface Props {
  isOpen: boolean;
  onClose: () => void;
  shipment: Shipment | null;
  postOffices: PostOffice[];
  onStatusUpdate?: (ids: string[], status: FuStatus) => void;
}

export function WhatsAppFollowUpModal({
  isOpen,
  onClose,
  shipment,
  postOffices = [],
  onStatusUpdate,
}: Props) {
  if (!shipment) return null;

  const [selectedOfficeId, setSelectedOfficeId] = useState<string>("");
  const [phoneNumber, setPhoneNumber] = useState<string>("");
  const [picName, setPicName] = useState<string>("");
  const [customNote, setCustomNote] = useState<string>("");
  const [selectedTemplate, setSelectedTemplate] = useState<WaTemplateType>("ANTAR_ULANG");
  const [autoMarkFuPos, setAutoMarkFuPos] = useState<boolean>(true);
  const [autoMarkStatus, setAutoMarkStatus] = useState<FuStatus>("BIRU_TUA");

  // Initial phone number & office matching
  useEffect(() => {
    if (!shipment) return;

    if (shipment.kantorPosPhone) {
      setPhoneNumber(shipment.kantorPosPhone);
      setPicName(shipment.kantorPosPic || "");
    } else {
      // Find match in postOffices list
      const matched = postOffices.find((po) => {
        if (shipment.kantorTujuan && po.name.toLowerCase().includes(shipment.kantorTujuan.toLowerCase())) {
          return true;
        }
        if (shipment.alamat && po.city && shipment.alamat.toLowerCase().includes(po.city.toLowerCase())) {
          return true;
        }
        return false;
      });

      if (matched) {
        setSelectedOfficeId(String(matched.id));
        setPhoneNumber(matched.phone_wa || "");
        setPicName(matched.pic_name || matched.name);
      } else {
        setPhoneNumber("");
        setPicName("");
      }
    }
  }, [shipment, postOffices]);

  // When dropdown selection changes
  const handleOfficeSelect = (officeId: string) => {
    setSelectedOfficeId(officeId);
    const office = postOffices.find((p) => String(p.id) === officeId);
    if (office) {
      setPhoneNumber(office.phone_wa || "");
      setPicName(office.pic_name || office.name);
    }
  };

  const selectedOfficeName = useMemo(() => {
    const office = postOffices.find((p) => String(p.id) === selectedOfficeId);
    return office?.name || shipment.kantorTujuan || "KC Pos Indonesia Tujuan";
  }, [selectedOfficeId, postOffices, shipment]);

  const waMessage = useMemo(() => {
    return generatePostOfficeWaMessage(shipment, selectedOfficeName, customNote, selectedTemplate);
  }, [shipment, selectedOfficeName, customNote, selectedTemplate]);

  const cleanPhone = useMemo(() => {
    let clean = phoneNumber.replace(/[^0-9]/g, "");
    if (clean.startsWith("0")) {
      clean = "62" + clean.substring(1);
    } else if (clean.startsWith("8")) {
      clean = "62" + clean;
    }
    return clean;
  }, [phoneNumber]);

  const handleOpenWhatsApp = () => {
    if (!cleanPhone || cleanPhone.length < 9) {
      toast.error("Nomor WhatsApp belum valid", {
        description: "Masukkan nomor WhatsApp CS/Kantor Pos tujuan (format: 08xxx / 628xxx).",
      });
      return;
    }

    const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(waMessage)}`;
    window.open(waUrl, "_blank");

    if (autoMarkFuPos && onStatusUpdate) {
      onStatusUpdate([shipment.id], autoMarkStatus);
      toast.success("WhatsApp Terbuka & Status Berhasil Diperbarui", {
        description: `Status resi ${shipment.resi} diubah menjadi ${autoMarkStatus === "BIRU_TUA" ? "FU POS" : "SUDAH DI FU"}.`,
      });
    } else {
      toast.success("WhatsApp Terbuka", {
        description: `Menghubungkan ke ${selectedOfficeName} (${cleanPhone}).`,
      });
    }

    onClose();
  };

  const handleCopyMessage = () => {
    void navigator.clipboard?.writeText(waMessage);
    toast.success("Template Pesan WhatsApp Disalin!", {
      description: "Teks follow-up sudah siap di-paste di WhatsApp.",
    });
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto p-6 rounded-2xl">
        <DialogHeader className="border-b pb-3">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xl shadow-md">
              <MessageSquare className="w-5 h-5" />
            </div>
            <div>
              <DialogTitle className="text-lg font-bold text-slate-900 flex items-center gap-2">
                Follow-Up CS ke Kantor Pos Tujuan
                <span className="text-xs font-mono font-medium px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-md">
                  {shipment.resi}
                </span>
              </DialogTitle>
              <p className="text-xs text-muted-foreground mt-0.5">
                Kirim pesan cepat ke Admin/CS KC Pos Indonesia tanpa pindah-pindah tab manual.
              </p>
            </div>
          </div>
        </DialogHeader>

        <div className="space-y-4 py-2">
          {/* Target Post Office Selection */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
            <div>
              <label className="text-xs font-bold text-slate-700 flex items-center gap-1.5 mb-1">
                <Building2 className="w-3.5 h-3.5 text-blue-600" />
                Kantor Pos Cabang (KC) Tujuan:
              </label>
              <select
                value={selectedOfficeId}
                onChange={(e) => handleOfficeSelect(e.target.value)}
                className="w-full text-xs font-medium bg-white border border-slate-300 rounded-lg px-2.5 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
              >
                <option value="">-- Pilih dari Database KC Pos --</option>
                {postOffices.map((po) => (
                  <option key={po.id} value={String(po.id)}>
                    {po.name} {po.city ? `(${po.city})` : ""} - {po.phone_wa || "Belum ada no WA"}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="text-xs font-bold text-slate-700 flex items-center gap-1.5 mb-1">
                <Phone className="w-3.5 h-3.5 text-emerald-600" />
                Nomor WhatsApp KC / PIC:
              </label>
              <div className="flex gap-2">
                <Input
                  type="text"
                  value={phoneNumber}
                  onChange={(e) => setPhoneNumber(e.target.value)}
                  placeholder="Contoh: 08123456789 / 628123..."
                  className="text-xs font-mono font-semibold"
                />
              </div>
            </div>

            {picName && (
              <div className="col-span-full flex items-center gap-2 text-xs text-slate-600 font-medium">
                <User className="w-3.5 h-3.5 text-slate-400" />
                <span>PIC/Helpdesk: <strong>{picName}</strong></span>
              </div>
            )}
          </div>

          {/* Quick Shipment Info Summary */}
          <div className="grid grid-cols-2 md:grid-cols-3 gap-2 bg-blue-50/70 p-3 rounded-xl border border-blue-100 text-xs">
            <div>
              <span className="text-[10px] text-blue-600 uppercase font-bold block">Penerima</span>
              <span className="font-semibold text-slate-800">{shipment.penerima} ({shipment.telepon})</span>
            </div>
            <div>
              <span className="text-[10px] text-blue-600 uppercase font-bold block">Status NIPOS</span>
              <span className="font-semibold text-slate-800">{shipment.nipos}</span>
            </div>
            <div>
              <span className="text-[10px] text-blue-600 uppercase font-bold block">SLA Hari</span>
              <span className="font-semibold text-slate-800">{shipment.sla} Hari</span>
            </div>
            <div className="col-span-full">
              <span className="text-[10px] text-blue-600 uppercase font-bold flex items-center gap-1">
                <MapPin className="w-3 h-3" /> Alamat Tujuan:
              </span>
              <span className="text-slate-700 font-normal">{shipment.alamat}</span>
            </div>
          </div>

          {/* Pilihan Template Pesan WhatsApp */}
          <div>
            <label className="text-xs font-bold text-slate-700 flex items-center justify-between mb-1">
              <span className="flex items-center gap-1.5">
                <MessageSquare className="w-3.5 h-3.5 text-blue-600" />
                Pilihan Template Pesan WhatsApp CS:
              </span>
              <span className="text-[10px] text-muted-foreground font-normal">
                Sesuaikan skenario follow-up paket
              </span>
            </label>
            <select
              value={selectedTemplate}
              onChange={(e) => setSelectedTemplate(e.target.value as WaTemplateType)}
              className="w-full text-xs font-bold bg-white border border-slate-300 rounded-lg px-2.5 py-2 text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none shadow-sm cursor-pointer"
            >
              {WA_TEMPLATES.map((tpl) => (
                <option key={tpl.id} value={tpl.id} className="font-semibold text-slate-800 py-1">
                  {tpl.label}
                </option>
              ))}
            </select>
            <p className="text-[11px] text-slate-500 mt-1 italic">
              💡 {WA_TEMPLATES.find((t) => t.id === selectedTemplate)?.desc}
            </p>
          </div>

          {/* Custom Note Addition */}
          <div>
            <label className="text-xs font-bold text-slate-700 block mb-1">
              Catatan / Permintaan Khusus (Opsional):
            </label>
            <Input
              type="text"
              value={customNote}
              onChange={(e) => setCustomNote(e.target.value)}
              placeholder="Misal: Penerima minta diantar setelah jam 2 siang / No telp alternatif 08..."
              className="text-xs"
            />
          </div>

          {/* WhatsApp Template Message Live Preview */}
          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                <MessageSquare className="w-3.5 h-3.5 text-emerald-600" />
                Preview Template Pesan WhatsApp:
              </label>
              <button
                type="button"
                onClick={handleCopyMessage}
                className="text-[11px] text-emerald-700 hover:text-emerald-800 font-semibold flex items-center gap-1 cursor-pointer"
              >
                <Copy className="w-3 h-3" /> Salin Pesan
              </button>
            </div>
            <Textarea
              readOnly
              value={waMessage}
              rows={8}
              className="font-mono text-xs bg-slate-50 text-slate-800 border-slate-300 resize-none leading-relaxed"
            />
          </div>

          {/* Auto-Update Checkbox */}
          <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
            <div className="flex items-center space-x-2.5">
              <Checkbox
                id="auto-mark"
                checked={autoMarkFuPos}
                onCheckedChange={(c) => setAutoMarkFuPos(!!c)}
              />
              <label
                htmlFor="auto-mark"
                className="text-xs font-medium text-slate-700 cursor-pointer select-none"
              >
                Otomatis tandai status setelah chat dibuka:
              </label>
            </div>
            {autoMarkFuPos && (
              <select
                value={autoMarkStatus}
                onChange={(e) => setAutoMarkStatus(e.target.value as FuStatus)}
                className="text-xs font-bold bg-white border border-slate-300 rounded-md px-2 py-1 outline-none"
              >
                <option value="BIRU_TUA">BIRU TUA (FU POS)</option>
                <option value="KUNING">KUNING (SUDAH DI FU)</option>
                <option value="HIJAU">HIJAU (FU 2 KALI)</option>
              </select>
            )}
          </div>
        </div>

        <DialogFooter className="border-t pt-3 flex items-center justify-between gap-2 sm:justify-between">
          <Button
            type="button"
            variant="outline"
            onClick={onClose}
            className="text-xs"
          >
            Batal
          </Button>

          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={handleCopyMessage}
              className="text-xs flex items-center gap-1.5"
            >
              <Copy className="w-3.5 h-3.5" />
              Salin Teks
            </Button>
            <Button
              type="button"
              onClick={handleOpenWhatsApp}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md px-4"
            >
              <ExternalLink className="w-4 h-4" />
              Buka WhatsApp (Web / App)
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
