import { useState, useMemo } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { toast } from "sonner";
import {
  Building2,
  Plus,
  Search,
  Phone,
  Edit2,
  Trash2,
  Save,
  X,
  MapPin,
  Users,
  Send,
} from "lucide-react";
import { type PostOffice } from "@/lib/posindo";
import axios from "axios";

interface Props {
  isOpen: boolean;
  onClose: () => void;
  postOffices: PostOffice[];
  onOfficesUpdated: (updated: PostOffice[]) => void;
}

export function PostOfficesManagerModal({
  isOpen,
  onClose,
  postOffices = [],
  onOfficesUpdated,
}: Props) {
  const [search, setSearch] = useState("");
  const [editingId, setEditingId] = useState<number | string | null>(null);
  const [isAddingNew, setIsAddingNew] = useState(false);

  // Form states
  const [formData, setFormData] = useState({
    name: "",
    code: "",
    city: "",
    province: "",
    phone_wa: "",
    phone_wa_2: "",
    telegram_handle: "",
    pic_name: "",
    notes: "",
  });

  const filteredOffices = useMemo(() => {
    if (!search.trim()) return postOffices;
    const q = search.toLowerCase();
    return postOffices.filter(
      (po) =>
        (po.name && po.name.toLowerCase().includes(q)) ||
        (po.city && po.city.toLowerCase().includes(q)) ||
        (po.province && po.province.toLowerCase().includes(q)) ||
        (po.code && String(po.code).toLowerCase().includes(q)) ||
        (po.phone_wa && String(po.phone_wa).toLowerCase().includes(q)) ||
        (po.phone_wa_2 && String(po.phone_wa_2).toLowerCase().includes(q)) ||
        (po.telegram_handle && String(po.telegram_handle).toLowerCase().includes(q)) ||
        (po.pic_name && po.pic_name.toLowerCase().includes(q))
    );
  }, [postOffices, search]);

  const handleStartAdd = () => {
    setIsAddingNew(true);
    setEditingId(null);
    setFormData({
      name: "",
      code: "",
      city: "",
      province: "",
      phone_wa: "",
      phone_wa_2: "",
      telegram_handle: "",
      pic_name: "",
      notes: "",
    });
  };

  const handleStartEdit = (office: PostOffice) => {
    setIsAddingNew(false);
    setEditingId(office.id);
    setFormData({
      name: office.name,
      code: office.code || "",
      city: office.city || "",
      province: office.province || "",
      phone_wa: office.phone_wa || "",
      phone_wa_2: office.phone_wa_2 || "",
      telegram_handle: office.telegram_handle || "",
      pic_name: office.pic_name || "",
      notes: office.notes || "",
    });
  };

  const handleCancelForm = () => {
    setIsAddingNew(false);
    setEditingId(null);
  };

  const handleSave = async () => {
    if (!formData.name.trim() || (!formData.phone_wa.trim() && !formData.telegram_handle.trim())) {
      toast.error("Nama Kantor & Nomor WhatsApp atau Username Telegram Wajib Diisi!");
      return;
    }

    try {
      if (isAddingNew) {
        const res = await axios.post("/post-offices", formData);
        if (res.data?.success) {
          const newOffice = res.data.data;
          onOfficesUpdated([newOffice, ...postOffices]);
          toast.success("Kontak Kantor Pos Berhasil Ditambahkan!");
          handleCancelForm();
        }
      } else if (editingId) {
        const res = await axios.put(`/post-offices/${editingId}`, formData);
        if (res.data?.success) {
          const updatedOffice = res.data.data;
          const updatedList = postOffices.map((po) =>
            po.id === editingId ? updatedOffice : po
          );
          onOfficesUpdated(updatedList);
          toast.success("Kontak Kantor Pos Berhasil Diperbarui!");
          handleCancelForm();
        }
      }
    } catch (err: any) {
      toast.error("Gagal menyimpan data", {
        description: err.response?.data?.message || err.message,
      });
    }
  };

  const handleChatOffice = (po: PostOffice, phoneNumber?: string) => {
    let raw = phoneNumber || po.phone_wa || "";
    let clean = raw.replace(/[^0-9]/g, "");
    if (clean.startsWith("0")) {
      clean = "62" + clean.substring(1);
    } else if (clean.startsWith("8")) {
      clean = "62" + clean;
    }

    if (!clean || clean.length < 9) {
      toast.error("Nomor WhatsApp belum valid untuk kantor pos ini.");
      return;
    }

    const template = [
      `Halo Rekan CS / Antaran Pos Indonesia ${po.name},`,
      ``,
      `Perkenalkan kami dari Tim CS Posindo Mitra Pengirim.`,
      `Kami ingin berkoordinasi terkait pemantauan antaran dan penanganan kendala kiriman di wilayah ${po.city || po.name}.`,
      ``,
      `Mohon bantuannya ya kak jika nanti ada paket yang perlu dicek / di-follow up antaran ke penerima agar paket sukses terkirim dan tidak terjadi komplain.`,
      `Terima kasih banyak atas bantuan dan kerjasamanya! 🙏✨`,
    ].join("\n");

    const url = `https://wa.me/${clean}?text=${encodeURIComponent(template)}`;
    window.open(url, "_blank");
    toast.success(`Membuka WhatsApp ke ${po.name}`, {
      description: `Nomor: ${clean}`,
    });
  };

  const handleChatTelegram = (po: PostOffice) => {
    const cleanHandle = (po.telegram_handle || "").replace(/^@/, "").trim();
    if (!cleanHandle) {
      toast.error("Username Telegram belum valid untuk kantor pos ini.");
      return;
    }

    const template = [
      `Halo Rekan CS / Antaran Pos Indonesia ${po.name},`,
      ``,
      `Perkenalkan kami dari Tim CS Posindo Mitra Pengirim.`,
      `Kami ingin berkoordinasi terkait pemantauan antaran dan penanganan kendala kiriman di wilayah ${po.city || po.name}.`,
      ``,
      `Mohon bantuannya ya kak jika nanti ada paket yang perlu dicek / di-follow up antaran ke penerima agar paket sukses terkirim dan tidak terjadi komplain.`,
      `Terima kasih banyak atas bantuan dan kerjasamanya! 🙏✨`,
    ].join("\n");

    void navigator.clipboard?.writeText(template);
    window.open(`https://t.me/${cleanHandle}`, "_blank");
    toast.success(`Membuka Telegram ke @${cleanHandle}`, {
      description: "Template pesan perkenalan telah disalin ke clipboard! Silakan Paste (Ctrl+V) di Telegram.",
    });
  };

  const handleDelete = async (id: number | string) => {
    if (!confirm("Yakin ingin menghapus kontak kantor pos ini?")) return;

    try {
      const res = await axios.delete(`/post-offices/${id}`);
      if (res.data?.success) {
        onOfficesUpdated(postOffices.filter((po) => po.id !== id));
        toast.success("Kontak Kantor Pos Berhasil Dihapus!");
      }
    } catch (err: any) {
      toast.error("Gagal menghapus", {
        description: err.response?.data?.message || err.message,
      });
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-5xl max-h-[90vh] overflow-y-auto p-6 rounded-2xl">
        <DialogHeader className="border-b pb-3 pr-12 sm:pr-14 flex flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-3 min-w-0">
            <div className="w-10 h-10 rounded-xl bg-[#1E40AF] text-white flex items-center justify-center font-bold text-xl shadow-md shrink-0">
              <Building2 className="w-5 h-5" />
            </div>
            <div className="min-w-0">
              <DialogTitle className="text-lg font-bold text-slate-900">
                Database Kontak WhatsApp &amp; Telegram Kantor Pos (KC / KCU / SPP)
              </DialogTitle>
              <p className="text-xs text-muted-foreground mt-0.5">
                Kelola daftar kontak WhatsApp &amp; Telegram CS &amp; Helpdesk Kantor Pos se-Indonesia untuk auto follow-up.
              </p>
            </div>
          </div>
          <Button
            type="button"
            onClick={handleStartAdd}
            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-1.5 shadow shrink-0"
          >
            <Plus className="w-4 h-4" /> Tambah Kontak KC
          </Button>
        </DialogHeader>

        {/* Add / Edit Form Card */}
        {(isAddingNew || editingId) && (
          <div className="my-3 p-4 bg-slate-50 border border-slate-300 rounded-xl shadow-inner space-y-3">
            <div className="flex items-center justify-between border-b pb-2">
              <h4 className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                {isAddingNew ? (
                  <>
                    <Plus className="w-4 h-4 text-emerald-600" /> Tambah Kantor Pos Baru
                  </>
                ) : (
                  <>
                    <Edit2 className="w-4 h-4 text-blue-600" /> Edit Kontak Kantor Pos
                  </>
                )}
              </h4>
              <button
                type="button"
                onClick={handleCancelForm}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
              <div className="md:col-span-2">
                <label className="font-bold text-slate-700 block mb-1">
                  Nama Kantor Pos (KC/KCU/SPP) <span className="text-red-500">*</span>:
                </label>
                <Input
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="Contoh: KCU BANDUNG 40000 / KC SURABAYA 60000 / SPP JAKARTA"
                  className="text-xs font-semibold"
                />
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">
                  Nomor WhatsApp Utama (No. WA 1):
                </label>
                <Input
                  value={formData.phone_wa}
                  onChange={(e) => setFormData({ ...formData, phone_wa: e.target.value })}
                  placeholder="Contoh: 081234567890 / 628..."
                  className="text-xs font-mono font-semibold text-emerald-700"
                />
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">
                  Nomor WhatsApp Cadangan (No. WA 2):
                </label>
                <Input
                  value={formData.phone_wa_2}
                  onChange={(e) => setFormData({ ...formData, phone_wa_2: e.target.value })}
                  placeholder="Contoh: 0852... (opsional)"
                  className="text-xs font-mono font-semibold text-emerald-700"
                />
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">
                  Username Telegram CS/Helpdesk:
                </label>
                <div className="relative">
                  <span className="absolute left-2.5 top-2 text-xs font-bold text-[#229ED9]">@</span>
                  <Input
                    value={formData.telegram_handle ? formData.telegram_handle.replace(/^@/, "") : ""}
                    onChange={(e) => setFormData({ ...formData, telegram_handle: e.target.value ? `@${e.target.value.replace(/^@/, "")}` : "" })}
                    placeholder="CSPbr28000 / septianianita"
                    className="pl-7 text-xs font-mono font-bold text-[#229ED9]"
                  />
                </div>
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">Kota / Kabupaten:</label>
                <Input
                  value={formData.city}
                  onChange={(e) => setFormData({ ...formData, city: e.target.value })}
                  placeholder="Contoh: Bandung"
                  className="text-xs"
                />
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">Provinsi:</label>
                <Input
                  value={formData.province}
                  onChange={(e) => setFormData({ ...formData, province: e.target.value })}
                  placeholder="Contoh: Jawa Barat"
                  className="text-xs"
                />
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">Nama PIC / Helpdesk:</label>
                <Input
                  value={formData.pic_name}
                  onChange={(e) => setFormData({ ...formData, pic_name: e.target.value })}
                  placeholder="Contoh: CS Antaran / Pak Budi"
                  className="text-xs"
                />
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2 border-t">
              <Button
                type="button"
                variant="outline"
                onClick={handleCancelForm}
                className="text-xs"
              >
                Batal
              </Button>
              <Button
                type="button"
                onClick={handleSave}
                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5"
              >
                <Save className="w-3.5 h-3.5" /> Simpan Kontak
              </Button>
            </div>
          </div>
        )}

        {/* Search Bar */}
        <div className="relative my-2">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Cari berdasarkan nama kantor, kota, kode pos, nomor WA, atau Telegram..."
            className="pl-9 text-xs"
          />
        </div>

        {/* Table of Post Offices */}
        <div className="pos-scroll overflow-x-auto border border-border rounded-xl max-h-[50vh]">
          <table className="w-full text-xs text-left border-collapse">
            <thead className="bg-[#1E40AF] text-white sticky top-0 z-10 font-semibold">
              <tr>
                <th className="py-2.5 px-3">No</th>
                <th className="py-2.5 px-3">Nama Kantor Pos (KC/KCU/SPP)</th>
                <th className="py-2.5 px-3">Kota &amp; Provinsi</th>
                <th className="py-2.5 px-3">Kontak WhatsApp &amp; Telegram</th>
                <th className="py-2.5 px-3">PIC / Helpdesk</th>
                <th className="py-2.5 px-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200">
              {filteredOffices.length === 0 ? (
                <tr>
                  <td colSpan={6} className="text-center py-6 text-slate-400 italic">
                    Tidak ada data kontak kantor pos yang sesuai pencarian.
                  </td>
                </tr>
              ) : (
                filteredOffices.map((po, index) => (
                  <tr key={po.id} className="hover:bg-slate-50 transition">
                    <td className="py-2.5 px-3 text-slate-500 font-mono">{index + 1}</td>
                    <td className="py-2.5 px-3 font-bold text-slate-900">
                      <div className="flex items-center gap-1.5">
                        <Building2 className="w-3.5 h-3.5 text-blue-600 shrink-0" />
                        <span>{po.name}</span>
                      </div>
                    </td>
                    <td className="py-2.5 px-3 text-slate-600">
                      {po.city ? (
                        <div className="flex items-center gap-1">
                          <MapPin className="w-3 h-3 text-slate-400" />
                          <span>{po.city}{po.province ? `, ${po.province}` : ""}</span>
                        </div>
                      ) : "-"}
                    </td>
                    <td className="py-2.5 px-3">
                      <div className="flex flex-col gap-1 items-start">
                        {po.phone_wa && (
                          <button
                            type="button"
                            onClick={() => handleChatOffice(po, po.phone_wa)}
                            className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 font-mono font-bold text-[11px] shadow-xs transition hover:scale-102 cursor-pointer group"
                            title="Klik untuk chat WhatsApp 1"
                          >
                            <Phone className="w-3 h-3 text-emerald-600 group-hover:scale-110 transition shrink-0" />
                            <span>{po.phone_wa}</span>
                            <span className="text-[9px] bg-emerald-600 text-white font-sans px-1 py-0.1 rounded font-bold ml-1">
                              WA
                            </span>
                          </button>
                        )}
                        {po.phone_wa_2 && (
                          <button
                            type="button"
                            onClick={() => handleChatOffice(po, po.phone_wa_2)}
                            className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-mono text-[11px] shadow-xs transition hover:scale-102 cursor-pointer group"
                            title="Klik untuk chat WhatsApp Cadangan"
                          >
                            <Phone className="w-3 h-3 text-emerald-600 shrink-0" />
                            <span>{po.phone_wa_2}</span>
                            <span className="text-[9px] bg-emerald-500 text-white font-sans px-1 py-0.1 rounded font-bold ml-1">
                              WA 2
                            </span>
                          </button>
                        )}
                        {po.telegram_handle && (
                          <button
                            type="button"
                            onClick={() => handleChatTelegram(po)}
                            className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-50 hover:bg-sky-100 text-sky-800 border border-sky-300 font-mono font-bold text-[11px] shadow-xs transition hover:scale-102 cursor-pointer group"
                            title="Klik untuk membuka Telegram"
                          >
                            <Send className="w-3 h-3 text-[#229ED9] group-hover:scale-110 transition shrink-0" />
                            <span>{po.telegram_handle.startsWith("@") ? po.telegram_handle : `@${po.telegram_handle}`}</span>
                            <span className="text-[9px] bg-[#229ED9] text-white font-sans px-1 py-0.1 rounded font-bold ml-1">
                              Telegram
                            </span>
                          </button>
                        )}
                        {!po.phone_wa && !po.phone_wa_2 && !po.telegram_handle && (
                          <span className="text-slate-400 italic text-[11px]">-</span>
                        )}
                      </div>
                    </td>
                    <td className="py-2.5 px-3 text-slate-700">
                      {po.pic_name || "-"}
                    </td>
                    <td className="py-2.5 px-3 text-right">
                      <div className="flex items-center justify-end gap-1.5">
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => handleStartEdit(po)}
                          className="h-7 w-7 p-0 text-blue-600 hover:text-blue-800 hover:bg-blue-50"
                        >
                          <Edit2 className="w-3.5 h-3.5" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDelete(po.id)}
                          className="h-7 w-7 p-0 text-red-600 hover:text-red-800 hover:bg-red-50"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        <DialogFooter className="border-t pt-3 flex items-center justify-between">
          <span className="text-xs text-muted-foreground">
            Total {filteredOffices.length} Kontak Kantor Pos Terdaftar
          </span>
          <Button type="button" variant="outline" onClick={onClose} className="text-xs">
            Tutup
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
