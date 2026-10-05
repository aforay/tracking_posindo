import { useState, useEffect, useMemo, useRef } from "react";
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
  Send,
  Search,
  ChevronDown,
  Check,
  X,
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
  initialTarget?: "POST_OFFICE" | "CUSTOMER";
}

export function WhatsAppFollowUpModal({
  isOpen,
  onClose,
  shipment,
  postOffices = [],
  onStatusUpdate,
  initialTarget = "POST_OFFICE",
}: Props) {
  if (!shipment) return null;

  // Target tab: POST_OFFICE (KC Tujuan) vs CUSTOMER (Pembeli/Penerima)
  const [targetType, setTargetType] = useState<"POST_OFFICE" | "CUSTOMER">(initialTarget);

  // Post office target states
  const [selectedOfficeId, setSelectedOfficeId] = useState<string>("");
  const [officePhoneNumber, setOfficePhoneNumber] = useState<string>("");
  const [officeTelegram, setOfficeTelegram] = useState<string>("");
  const [picName, setPicName] = useState<string>("");
  const [selectedOfficeTemplate, setSelectedOfficeTemplate] = useState<WaTemplateType>("ANTAR_ULANG");

  // Search bar states for KC Pos
  const [officeSearchQuery, setOfficeSearchQuery] = useState<string>("");
  const [isOfficeDropdownOpen, setIsOfficeDropdownOpen] = useState<boolean>(false);
  const officeDropdownRef = useRef<HTMLDivElement>(null);

  // Customer target states
  const [customerPhoneNumber, setCustomerPhoneNumber] = useState<string>("");
  const [selectedCustomerTemplate, setSelectedCustomerTemplate] = useState<WaCustomerTemplateType>("RUMAH_KOSONG");

  // Common states
  const [customNote, setCustomNote] = useState<string>("");
  const [autoMarkFu, setAutoMarkFu] = useState<boolean>(true);
  const [autoMarkStatus, setAutoMarkStatus] = useState<FuStatus>("BIRU_TUA");

  // Initial phone number, Telegram & office matching
  useEffect(() => {
    if (!shipment) return;
    if (initialTarget) {
      setTargetType(initialTarget);
    }

    // Customer phone init
    setCustomerPhoneNumber(shipment.telepon || "");

    // Telegram handle init
    const initialTg = shipment.kantorPosTelegram || (shipment.kantorPosPhone?.startsWith('@') ? shipment.kantorPosPhone : "");
    setOfficeTelegram(initialTg);

    // KC Pos matching init
    if (shipment.kantorPosPhone || shipment.kantorPosTelegram) {
      if (shipment.kantorPosPhone && !shipment.kantorPosPhone.startsWith('@')) {
        setOfficePhoneNumber(shipment.kantorPosPhone);
      } else {
        setOfficePhoneNumber("");
      }
      setPicName(shipment.kantorPosPic || "");

      const matched = postOffices.find(
        (po) =>
          (shipment.kantorPosPhone && po.phone_wa === shipment.kantorPosPhone) ||
          (shipment.kantorPosTelegram && po.telegram_handle === shipment.kantorPosTelegram) ||
          (shipment.kantorTujuan && (
            po.name.toLowerCase() === shipment.kantorTujuan.toLowerCase() ||
            po.name.toLowerCase().includes(shipment.kantorTujuan.toLowerCase()) ||
            shipment.kantorTujuan.toLowerCase().includes(po.name.toLowerCase())
          ))
      );

      if (matched) {
        setSelectedOfficeId(String(matched.id));
        if (matched.telegram_handle) setOfficeTelegram(matched.telegram_handle);
        if (matched.phone_wa && !matched.phone_wa.startsWith('@')) setOfficePhoneNumber(matched.phone_wa);
      } else {
        setSelectedOfficeId("");
      }
    } else {
      const combined = `${shipment.kantorTujuan || ""} ${shipment.alamat || ""} ${shipment.tujuan || ""}`.toLowerCase();
      const matched = postOffices.find((po) => {
        const poName = po.name.toLowerCase();
        const poCity = (po.city || "").toLowerCase();

        if (combined.includes("hinai") || combined.includes("langkat") || combined.includes("stabat") || combined.includes("20854")) {
          return poName.includes("binjai") || poCity.includes("binjai");
        }
        if (combined.includes("kaliorang")) {
          return poName.includes("bontang") || poCity.includes("bontang");
        }
        if (combined.includes("muarasabak") || combined.includes("muara sabak")) {
          return poName.includes("jambi") || poCity.includes("jambi");
        }

        if (
          shipment.kantorTujuan &&
          (poName.includes(shipment.kantorTujuan.toLowerCase()) ||
           shipment.kantorTujuan.toLowerCase().includes(poName))
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
        setOfficePhoneNumber(matched.phone_wa && !matched.phone_wa.startsWith('@') ? matched.phone_wa : "");
        setOfficeTelegram(matched.telegram_handle || (matched.phone_wa?.startsWith('@') ? matched.phone_wa : ""));
        setPicName(matched.pic_name || matched.name);
      } else {
        setSelectedOfficeId("");
        setOfficePhoneNumber("");
        setOfficeTelegram("");
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
      setOfficePhoneNumber(office.phone_wa && !office.phone_wa.startsWith('@') ? office.phone_wa : "");
      setOfficeTelegram(office.telegram_handle || (office.phone_wa?.startsWith('@') ? office.phone_wa : ""));
      setPicName(office.pic_name || office.name);
      setOfficeSearchQuery(`${office.name}${office.city ? ` (${office.city})` : ""}`);
    } else {
      setOfficePhoneNumber("");
      setOfficeTelegram("");
      setPicName("");
      setOfficeSearchQuery("");
    }
  };

  // Sync search input when selectedOfficeId changes from initial matching
  useEffect(() => {
    if (selectedOfficeId) {
      const office = postOffices.find((p) => String(p.id) === selectedOfficeId);
      if (office) {
        setOfficeSearchQuery(`${office.name}${office.city ? ` (${office.city})` : ""}`);
      }
    }
  }, [selectedOfficeId, postOffices]);

  // Close dropdown on outside click
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (officeDropdownRef.current && !officeDropdownRef.current.contains(event.target as Node)) {
        setIsOfficeDropdownOpen(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, []);

  const filteredOffices = useMemo(() => {
    if (!officeSearchQuery.trim()) {
      return postOffices;
    }
    const q = officeSearchQuery.toLowerCase().trim();
    return postOffices.filter((po) => {
      const name = (po.name || "").toLowerCase();
      const city = (po.city || "").toLowerCase();
      const code = (po.code || "").toLowerCase();
      const wa = (po.phone_wa || "").toLowerCase();
      const notes = (po.notes || "").toLowerCase();
      const pic = (po.pic_name || "").toLowerCase();
      return name.includes(q) || city.includes(q) || code.includes(q) || wa.includes(q) || notes.includes(q) || pic.includes(q);
    });
  }, [postOffices, officeSearchQuery]);

  const handleSelectOffice = (po: PostOffice) => {
    handleOfficeSelect(String(po.id));
    setOfficeSearchQuery(`${po.name}${po.city ? ` (${po.city})` : ""}`);
    setIsOfficeDropdownOpen(false);
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
    toast.success("Template Pesan Disalin!", {
      description: "Teks follow-up sudah siap di-paste di WhatsApp atau Telegram.",
    });
  };

  const cleanTelegram = useMemo(() => {
    return (officeTelegram || "").replace(/^@/, "").trim();
  }, [officeTelegram]);

  const handleOpenTelegram = () => {
    if (!cleanTelegram) {
      toast.error("Username Telegram belum valid", {
        description: "Masukkan username Telegram KC Pos (contoh: @CSPbr28000).",
      });
      return;
    }

    const textToSend = (messageText && messageText.trim()) ? messageText : generatedMessage;
    void navigator.clipboard?.writeText(textToSend);

    window.open(`https://t.me/${cleanTelegram}`, "_blank");

    if (autoMarkFu && onStatusUpdate) {
      onStatusUpdate([shipment.id], autoMarkStatus);
      toast.success(`Telegram Terbuka (@${cleanTelegram}) & Status Berhasil Diperbarui`, {
        description: "Template pesan telah disalin ke clipboard! Silakan Paste (Ctrl+V) di Telegram.",
      });
    } else {
      toast.success(`Telegram Terbuka (@${cleanTelegram})`, {
        description: "Template pesan telah disalin ke clipboard! Silakan Paste (Ctrl+V) di Telegram.",
      });
    }

    onClose();
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
                {shipment.namaCs && (
                  <span className="text-xs font-semibold px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded-md inline-flex items-center gap-1">
                    <User className="w-3 h-3 text-indigo-600" /> CS: {shipment.namaCs}
                  </span>
                )}
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
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
              <div className="relative" ref={officeDropdownRef}>
                <label className="text-xs font-bold text-slate-700 flex items-center justify-between mb-1">
                  <span className="flex items-center gap-1.5">
                    <Building2 className="w-3.5 h-3.5 text-blue-600" />
                    Kantor Pos Cabang (KC) Tujuan:
                  </span>
                  {selectedOfficeId && (
                    <span className="text-[10px] text-emerald-600 font-semibold flex items-center gap-0.5">
                      <Check className="w-3 h-3" /> Terpilih
                    </span>
                  )}
                </label>
                <div className="relative">
                  <Search className="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                  <input
                    type="text"
                    value={officeSearchQuery}
                    onChange={(e) => {
                      setOfficeSearchQuery(e.target.value);
                      setIsOfficeDropdownOpen(true);
                    }}
                    onFocus={() => setIsOfficeDropdownOpen(true)}
                    placeholder="Ketik cari KC / kota / kodepos / WA..."
                    className="w-full text-xs font-medium bg-white border border-slate-300 rounded-lg pl-8 pr-14 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                  />
                  <div className="absolute right-1.5 top-1/2 -translate-y-1/2 flex items-center gap-0.5">
                    {officeSearchQuery && (
                      <button
                        type="button"
                        onClick={() => {
                          setOfficeSearchQuery("");
                          setSelectedOfficeId("");
                          setOfficePhoneNumber("");
                          setOfficeTelegram("");
                          setPicName("");
                          setIsOfficeDropdownOpen(true);
                        }}
                        className="p-1 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-100"
                        title="Hapus pencarian"
                      >
                        <X className="w-3.5 h-3.5" />
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => setIsOfficeDropdownOpen((prev) => !prev)}
                      className="p-1 text-slate-400 hover:text-slate-600 rounded-full hover:bg-slate-100"
                      title="Buka daftar KC"
                    >
                      <ChevronDown className={`w-3.5 h-3.5 transition-transform duration-200 ${isOfficeDropdownOpen ? "rotate-180" : ""}`} />
                    </button>
                  </div>
                </div>

                {/* Dropdown Hasil Pencarian */}
                {isOfficeDropdownOpen && (
                  <div className="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-lg shadow-2xl z-50 max-h-64 overflow-y-auto divide-y divide-slate-100">
                    <div className="px-3 py-1.5 bg-slate-50 text-[10px] font-semibold text-slate-500 flex items-center justify-between sticky top-0 border-b border-slate-100 z-10">
                      <span>Ditemukan {filteredOffices.length} Kantor Pos</span>
                      <span>Ketik untuk menyaring</span>
                    </div>
                    {filteredOffices.length === 0 ? (
                      <div className="p-4 text-center text-xs text-slate-500">
                        Tidak ada kantor pos yang cocok dengan "<strong>{officeSearchQuery}</strong>"
                      </div>
                    ) : (
                      filteredOffices.map((po) => {
                        const isSelected = String(po.id) === selectedOfficeId;
                        return (
                          <div
                            key={po.id}
                            onClick={() => handleSelectOffice(po)}
                            className={`px-3 py-2 text-xs cursor-pointer transition flex items-start justify-between gap-2 ${
                              isSelected
                                ? "bg-blue-50/80 text-blue-900 font-semibold"
                                : "hover:bg-slate-50 text-slate-800"
                            }`}
                          >
                            <div className="min-w-0 flex-1">
                              <div className="flex items-center gap-1.5 flex-wrap">
                                <span className="font-bold text-slate-900">{po.name}</span>
                                {po.code && (
                                  <span className="text-[10px] bg-slate-100 text-slate-600 px-1 rounded font-mono">
                                    {po.code}
                                  </span>
                                )}
                                {po.city && (
                                  <span className="text-[11px] text-slate-500">
                                    ({po.city})
                                  </span>
                                )}
                              </div>
                              {po.notes && (
                                <p className="text-[10px] text-slate-400 truncate mt-0.5" title={po.notes}>
                                  {po.notes}
                                </p>
                              )}
                              <div className="flex items-center gap-1.5 mt-1 text-[10px] flex-wrap">
                                {po.phone_wa && (
                                  <span className="text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.2 rounded flex items-center gap-0.5 font-mono">
                                    WA: {po.phone_wa}
                                  </span>
                                )}
                                {po.telegram_handle && (
                                  <span className="text-sky-700 bg-sky-50 border border-sky-200 px-1.5 py-0.2 rounded flex items-center gap-0.5 font-mono">
                                    TG: {po.telegram_handle}
                                  </span>
                                )}
                                {po.pic_name && (
                                  <span className="text-slate-500 text-[10px]">
                                    • {po.pic_name}
                                  </span>
                                )}
                              </div>
                            </div>
                            {isSelected && (
                              <Check className="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                            )}
                          </div>
                        );
                      })
                    )}
                  </div>
                )}
              </div>

              <div>
                <label className="text-xs font-bold text-slate-700 flex items-center gap-1.5 mb-1">
                  <Phone className="w-3.5 h-3.5 text-emerald-600" />
                  Nomor WhatsApp KC:
                </label>
                <Input
                  type="text"
                  value={officePhoneNumber}
                  onChange={(e) => setOfficePhoneNumber(e.target.value)}
                  placeholder="Contoh: 08123456789 / 628123..."
                  className="text-xs font-mono font-semibold bg-white"
                />
              </div>

              <div>
                <label className="text-xs font-bold text-slate-700 flex items-center gap-1.5 mb-1">
                  <Send className="w-3.5 h-3.5 text-sky-500" />
                  Username Telegram KC:
                </label>
                <Input
                  type="text"
                  value={officeTelegram}
                  onChange={(e) => setOfficeTelegram(e.target.value)}
                  placeholder="Contoh: @CSPbr28000"
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

        <DialogFooter className="border-t pt-3 flex items-center justify-between gap-2 sm:justify-between flex-wrap">
          <Button
            type="button"
            variant="outline"
            onClick={onClose}
            className="text-xs"
          >
            Batal
          </Button>

          <div className="flex items-center gap-2 flex-wrap justify-end">
            <Button
              type="button"
              variant="outline"
              onClick={handleCopyMessage}
              className="text-xs flex items-center gap-1.5"
            >
              <Copy className="w-3.5 h-3.5" />
              Salin Teks
            </Button>

            {/* Tombol Telegram (Jika target KC Pos dan memiliki username Telegram) */}
            {targetType === "POST_OFFICE" && cleanTelegram && (
              <Button
                type="button"
                onClick={handleOpenTelegram}
                className="bg-[#229ED9] hover:bg-[#1E88E5] text-white font-bold text-xs flex items-center gap-1.5 shadow-md px-3.5 cursor-pointer"
                title={`Buka chat Telegram @${cleanTelegram} & salin template`}
              >
                <Send className="w-3.5 h-3.5" />
                Buka Telegram (@{cleanTelegram})
              </Button>
            )}

            {/* Tombol WhatsApp (Jika ada no WA atau bukan KC Telegram-only) */}
            {(!cleanTelegram || cleanPhone) && (
              <Button
                type="button"
                onClick={handleOpenWhatsApp}
                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-md px-4 cursor-pointer"
              >
                <ExternalLink className="w-4 h-4" />
                Buka WhatsApp {targetType === "POST_OFFICE" ? "KC Pos" : "Pembeli"}
              </Button>
            )}
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
