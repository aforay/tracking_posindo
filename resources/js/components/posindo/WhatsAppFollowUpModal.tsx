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
  UserCheck,
} from "lucide-react";
import {
  type Shipment,
  type PostOffice,
  type FuStatus,
  type WaTemplateType,
  type WaCustomerTemplateType,
  WA_TEMPLATES,
  WA_CUSTOMER_TEMPLATES,
  generatePostOfficeWaMessage,
  generateCustomerWaMessage,
  resolveDestinationOffice,
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

  // Target tab: POST_OFFICE (KC Tujuan) vs CUSTOMER (Pembeli/Penerima)
  const [targetType, setTargetType] = useState<"POST_OFFICE" | "CUSTOMER">("POST_OFFICE");

  // Post office target states
  const [selectedOfficeId, setSelectedOfficeId] = useState<string>("");
  const [officePhoneNumber, setOfficePhoneNumber] = useState<string>("");
  const [picName, setPicName] = useState<string>("");
  const [selectedOfficeTemplate, setSelectedOfficeTemplate] = useState<WaTemplateType>("ANTAR_ULANG");

  // Customer target states
  const [customerPhoneNumber, setCustomerPhoneNumber] = useState<string>("");
  const [selectedCustomerTemplate, setSelectedCustomerTemplate] = useState<WaCustomerTemplateType>("RUMAH_KOSONG");

  // Common states
  const [customNote, setCustomNote] = useState<string>("");
  const [autoMarkFu, setAutoMarkFu] = useState<boolean>(true);
  const [autoMarkStatus, setAutoMarkStatus] = useState<FuStatus>("BIRU_TUA");

  // Initial phone number & office matching
  useEffect(() => {
    if (!shipment) return;

    // Customer phone init
    setCustomerPhoneNumber(shipment.telepon || "");

    // KC Pos matching init
    if (shipment.kantorPosPhone) {
      setOfficePhoneNumber(shipment.kantorPosPhone);
      setPicName(shipment.kantorPosPic || "");

      const matched = postOffices.find(
        (po) =>
          po.phone_wa === shipment.kantorPosPhone ||
          (shipment.kantorTujuan && (
            po.name.toLowerCase() === shipment.kantorTujuan.toLowerCase() ||
            po.name.toLowerCase().includes(shipment.kantorTujuan.toLowerCase()) ||
            shipment.kantorTujuan.toLowerCase().includes(po.name.toLowerCase())
          ))
      );

      if (matched) {
        setSelectedOfficeId(String(matched.id));
      } else {
        setSelectedOfficeId("");
      }
    } else {
      const matched = postOffices.find((po) => {
        if (
          shipment.kantorTujuan &&
          (po.name.toLowerCase().includes(shipment.kantorTujuan.toLowerCase()) ||
           shipment.kantorTujuan.toLowerCase().includes(po.name.toLowerCase()))
        ) {
          return true;
        }
        if (shipment.alamat && po.city && shipment.alamat.toLowerCase().includes(po.city.toLowerCase())) {
          return true;
        }
        return false;
      });

      if (matched) {
        setSelectedOfficeId(String(matched.id));
        setOfficePhoneNumber(matched.phone_wa || "");
        setPicName(matched.pic_name || matched.name);
      } else {
        setSelectedOfficeId("");
        setOfficePhoneNumber("");
        setPicName("");
      }
    }
  }, [shipment, postOffices]);

  // Adjust default auto mark status based on target tab
  useEffect(() => {
    if (targetType === "CUSTOMER") {
      setAutoMarkStatus(shipment?.fu === "KUNING" ? "HIJAU" : "KUNING");
    } else {
      setAutoMarkStatus("BIRU_TUA");
    }
  }, [targetType, shipment]);

  const handleOfficeSelect = (officeId: string) => {
    setSelectedOfficeId(officeId);
    const office = postOffices.find((p) => String(p.id) === officeId);
    if (office) {
      setOfficePhoneNumber(office.phone_wa || "");
      setPicName(office.pic_name || office.name);
    }
  };

  const selectedOfficeName = useMemo(() => {
    const office = postOffices.find((p) => String(p.id) === selectedOfficeId);
    return resolveDestinationOffice(shipment, office?.name);
  }, [selectedOfficeId, postOffices, shipment]);

  const activePhoneNumber = targetType === "POST_OFFICE" ? officePhoneNumber : customerPhoneNumber;

  const generatedMessage = useMemo(() => {
    if (targetType === "POST_OFFICE") {
      return generatePostOfficeWaMessage(shipment, selectedOfficeName, customNote, selectedOfficeTemplate);
    } else {
      return generateCustomerWaMessage(shipment, selectedCustomerTemplate, customNote);
    }
  }, [targetType, shipment, selectedOfficeName, customNote, selectedOfficeTemplate, selectedCustomerTemplate]);

  const [messageText, setMessageText] = useState("");

  useEffect(() => {
    setMessageText(generatedMessage);
  }, [generatedMessage]);

  const cleanPhone = useMemo(() => {
    let clean = (activePhoneNumber || "").replace(/[^0-9]/g, "");
    if (clean.startsWith("0")) {
      clean = "62" + clean.substring(1);
    } else if (clean.startsWith("8")) {
      clean = "62" + clean;
    }
    return clean;
  }, [activePhoneNumber]);

  const handleOpenWhatsApp = () => {
    if (!cleanPhone || cleanPhone.length < 9) {
      toast.error("Nomor WhatsApp belum valid", {
        description: `Masukkan nomor WhatsApp ${targetType === "POST_OFFICE" ? "Kantor Pos (KC)" : "Penerima/Pembeli"} (format: 08xxx / 628xxx).`,
      });
      return;
    }

    const textToSend = (messageText && messageText.trim()) ? messageText : generatedMessage;
    const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(textToSend)}`;
    window.open(waUrl, "_blank");

    if (autoMarkFu && onStatusUpdate) {
      onStatusUpdate([shipment.id], autoMarkStatus);
      toast.success("WhatsApp Terbuka & Status Berhasil Diperbarui", {
        description: `Status resi ${shipment.resi} diubah menjadi ${autoMarkStatus}.`,
      });
    } else {
      toast.success("WhatsApp Terbuka", {
        description: `Menghubungkan ke ${targetType === "POST_OFFICE" ? selectedOfficeName : shipment.penerima} (${cleanPhone}).`,
      });
    }

    onClose();
  };

  const handleCopyMessage = () => {
    const textToCopy = (messageText && messageText.trim()) ? messageText : generatedMessage;
    void navigator.clipboard?.writeText(textToCopy);
    toast.success("Template Pesan WhatsApp Disalin!", {
      description: "Teks follow-up sudah siap di-paste di WhatsApp.",
    });
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto p-6 rounded-2xl bg-white border border-slate-200">
        <DialogHeader className="border-b pb-3">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xl shadow-md">
              <MessageSquare className="w-5 h-5" />
            </div>
            <div>
              <DialogTitle className="text-lg font-bold text-slate-900 flex items-center gap-2">
                Follow-Up CS Posindo
                <span className="text-xs font-mono font-bold px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-md">
                  {shipment.resi}
                </span>
              </DialogTitle>
              <p className="text-xs text-muted-foreground mt-0.5">
                Kirim pesan cepat ke Kantor Pos tujuan (KC) atau langsung ke Penerima/Pembeli.
              </p>
            </div>
          </div>
        </DialogHeader>

        {/* Tab Pilihan Target: KC Pos vs Pembeli */}
        <div className="flex rounded-xl bg-slate-100 p-1 border border-slate-200">
          <button
            type="button"
            onClick={() => setTargetType("POST_OFFICE")}
            className={`flex-1 py-2 px-3 text-xs font-bold rounded-lg flex items-center justify-center gap-1.5 transition cursor-pointer ${
              targetType === "POST_OFFICE"
                ? "bg-white text-blue-700 shadow-sm"
                : "text-slate-600 hover:text-slate-900"
            }`}
          >
            <Building2 className="w-3.5 h-3.5" />
            🏢 Hubungi Kantor Pos (KC Tujuan)
          </button>
          <button
            type="button"
            onClick={() => setTargetType("CUSTOMER")}
            className={`flex-1 py-2 px-3 text-xs font-bold rounded-lg flex items-center justify-center gap-1.5 transition cursor-pointer ${
              targetType === "CUSTOMER"
                ? "bg-white text-emerald-700 shadow-sm"
                : "text-slate-600 hover:text-slate-900"
            }`}
          >
            <UserCheck className="w-3.5 h-3.5" />
            👤 Hubungi Pembeli / Penerima
          </button>
        </div>

        <div className="space-y-4 py-1">
          {/* Skenario 1: Hubungi Kantor Pos (KC) */}
          {targetType === "POST_OFFICE" ? (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
              <div>
                <label className="text-xs font-bold text-slate-700 flex items-center gap-1.5 mb-1">
                  <Building2 className="w-3.5 h-3.5 text-blue-600" />
                  Kantor Pos Cabang (KC) Tujuan:
                </label>
                <select
                  value={selectedOfficeId}
                  onChange={(e) => handleOfficeSelect(e.target.value)}
                  className="w-full text-xs font-medium bg-white border border-slate-300 rounded-lg px-2.5 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
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
                <Input
                  type="text"
                  value={officePhoneNumber}
                  onChange={(e) => setOfficePhoneNumber(e.target.value)}
                  placeholder="Contoh: 08123456789 / 628123..."
                  className="text-xs font-mono font-semibold bg-white"
                />
              </div>

              {picName && (
                <div className="col-span-full flex items-center gap-2 text-xs text-slate-600 font-medium">
                  <User className="w-3.5 h-3.5 text-slate-400" />
                  <span>PIC/Helpdesk KC: <strong>{picName}</strong></span>
                </div>
              )}
            </div>
          ) : (
            /* Skenario 2: Hubungi Penerima / Customer */
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 bg-emerald-50/50 p-3.5 rounded-xl border border-emerald-200">
              <div>
                <label className="text-xs font-bold text-slate-700 flex items-center gap-1.5 mb-1">
                  <User className="w-3.5 h-3.5 text-emerald-600" />
                  Nama Pembeli / Penerima:
                </label>
                <Input
                  readOnly
                  value={shipment.penerima || "-"}
                  className="text-xs font-semibold bg-white cursor-not-allowed"
                />
              </div>

              <div>
                <label className="text-xs font-bold text-slate-700 flex items-center gap-1.5 mb-1">
                  <Phone className="w-3.5 h-3.5 text-emerald-600" />
                  Nomor WhatsApp / HP Pembeli:
                </label>
                <Input
                  type="text"
                  value={customerPhoneNumber}
                  onChange={(e) => setCustomerPhoneNumber(e.target.value)}
                  placeholder="Contoh: 08123456789"
                  className="text-xs font-mono font-semibold bg-white"
                />
              </div>
            </div>
          )}

          {/* Quick Shipment Info Summary */}
          <div className="grid grid-cols-2 md:grid-cols-3 gap-2 bg-blue-50/70 p-3 rounded-xl border border-blue-100 text-xs">
            <div>
              <span className="text-[10px] text-blue-600 uppercase font-bold block">Penerima</span>
              <span className="font-semibold text-slate-800 truncate block">{shipment.penerima} ({shipment.telepon})</span>
            </div>
            <div>
              <span className="text-[10px] text-blue-600 uppercase font-bold block">Status NIPOS</span>
              <span className="font-semibold text-slate-800 truncate block">{shipment.nipos}</span>
            </div>
            <div>
              <span className="text-[10px] text-blue-600 uppercase font-bold block">SLA Hari</span>
              <span className="font-semibold text-slate-800">{shipment.sla} Hari</span>
            </div>
            <div className="col-span-full">
              <span className="text-[10px] text-blue-600 uppercase font-bold flex items-center gap-1">
                <MapPin className="w-3 h-3" /> Alamat Tujuan:
              </span>
              <span className="text-slate-700 font-normal leading-relaxed">{shipment.alamat}</span>
            </div>
          </div>

          {/* Pilihan Template Pesan */}
          <div>
            <label className="text-xs font-bold text-slate-700 flex items-center justify-between mb-1">
              <span className="flex items-center gap-1.5">
                <MessageSquare className="w-3.5 h-3.5 text-blue-600" />
                {targetType === "POST_OFFICE"
                  ? "Pilihan Template Pesan WhatsApp ke KC Pos:"
                  : "Pilihan Template Pesan WhatsApp ke Pembeli / Penerima:"}
              </span>
              <span className="text-[10px] text-muted-foreground font-normal">
                Sesuaikan skenario masalah paket
              </span>
            </label>

            {targetType === "POST_OFFICE" ? (
              <select
                value={selectedOfficeTemplate}
                onChange={(e) => setSelectedOfficeTemplate(e.target.value as WaTemplateType)}
                className="w-full text-xs font-bold bg-white border border-slate-300 rounded-lg px-2.5 py-2 text-slate-800 focus:ring-2 focus:ring-emerald-500 outline-none shadow-sm cursor-pointer"
              >
                {WA_TEMPLATES.map((tpl) => (
                  <option key={tpl.id} value={tpl.id} className="font-semibold text-slate-800 py-1">
                    {tpl.label}
                  </option>
                ))}
              </select>
            ) : (
              <select
                value={selectedCustomerTemplate}
                onChange={(e) => setSelectedCustomerTemplate(e.target.value as WaCustomerTemplateType)}
                className="w-full text-xs font-bold bg-white border border-slate-300 rounded-lg px-2.5 py-2 text-slate-800 focus:ring-2 focus:ring-emerald-500 outline-none shadow-sm cursor-pointer"
              >
                {WA_CUSTOMER_TEMPLATES.map((tpl) => (
                  <option key={tpl.id} value={tpl.id} className="font-semibold text-slate-800 py-1">
                    {tpl.label}
                  </option>
                ))}
              </select>
            )}

            <p className="text-[11px] text-slate-500 mt-1 italic">
              💡 {targetType === "POST_OFFICE"
                ? WA_TEMPLATES.find((t) => t.id === selectedOfficeTemplate)?.desc
                : WA_CUSTOMER_TEMPLATES.find((t) => t.id === selectedCustomerTemplate)?.desc}
            </p>
          </div>

          {/* Custom Note Addition */}
          <div>
            <label className="text-xs font-bold text-slate-700 block mb-1">
              Catatan / Permintaan Khusus Tambahan (Opsional):
            </label>
            <Input
              type="text"
              value={customNote}
              onChange={(e) => setCustomNote(e.target.value)}
              placeholder="Misal: Penerima minta diantar setelah jam 2 siang / Tolong titip ke pos satpam..."
              className="text-xs bg-white"
            />
          </div>

          {/* WhatsApp Template Message Live Preview */}
          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                <MessageSquare className="w-3.5 h-3.5 text-emerald-600" />
                Live Preview Pesan WhatsApp:
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
              value={messageText}
              onChange={(e) => setMessageText(e.target.value)}
              rows={9}
              className="font-mono text-xs bg-white text-slate-800 border-slate-300 resize-y leading-relaxed shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
              placeholder="Teks pesan WhatsApp siap dikirim (bisa diedit langsung di sini)..."
            />
            <p className="text-[10px] text-slate-400 mt-1 italic">
              ✏️ Anda dapat mengedit langsung susunan teks di atas sebelum dikirim atau disalin.
            </p>
          </div>

          {/* Auto-Update Checkbox */}
          <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
            <div className="flex items-center space-x-2.5">
              <Checkbox
                id="auto-mark"
                checked={autoMarkFu}
                onCheckedChange={(c) => setAutoMarkFu(!!c)}
              />
              <label
                htmlFor="auto-mark"
                className="text-xs font-medium text-slate-700 cursor-pointer select-none"
              >
                Otomatis tandai status setelah chat dibuka:
              </label>
            </div>
            {autoMarkFu && (
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
              Buka WhatsApp {targetType === "POST_OFFICE" ? "KC Pos" : "Pembeli"}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
