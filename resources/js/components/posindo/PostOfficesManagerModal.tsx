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
    pic_name: "",
    notes: "",
  });

  const filteredOffices = useMemo(() => {
    if (!search.trim()) return postOffices;
    const q = search.toLowerCase();
    return postOffices.filter(
      (po) =>
        po.name.toLowerCase().includes(q) ||
        (po.city && po.city.toLowerCase().includes(q)) ||
        (po.province && po.province.toLowerCase().includes(q)) ||
        (po.code && po.code.includes(q)) ||
        (po.phone_wa && po.phone_wa.includes(q)) ||
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
      pic_name: office.pic_name || "",
      notes: office.notes || "",
    });
  };

  const handleCancelForm = () => {
    setIsAddingNew(false);
    setEditingId(null);
  };

  const handleSave = async () => {
    if (!formData.name.trim() || !formData.phone_wa.trim()) {
      toast.error("Nama Kantor & No. WhatsApp Wajib Diisi!");
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

  const handleChatOffice = (po: PostOffice) => {
    let clean = (po.phone_wa || "").replace(/[^0-9]/g, "");
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
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto p-6 rounded-2xl">
        <DialogHeader className="border-b pb-3 flex flex-row items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-[#1E40AF] text-white flex items-center justify-center font-bold text-xl shadow-md">
              <Building2 className="w-5 h-5" />
            </div>
            <div>
              <DialogTitle className="text-lg font-bold text-slate-900">
                Database Kontak WhatsApp Kantor Pos (KC / KCU)
              </DialogTitle>
              <p className="text-xs text-muted-foreground mt-0.5">
                Kelola daftar kontak WhatsApp CS & Helpdesk Kantor Pos se-Indonesia untuk auto follow-up.
              </p>
            </div>
          </div>
          <Button
            type="button"
            onClick={handleStartAdd}
            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-1.5 shadow"
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
                  Nama Kantor Pos (KC/KCU/KCP) <span className="text-red-500">*</span>:
                </label>
                <Input
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="Contoh: KCU BANDUNG 40000 / KC SURABAYA 60000"
                  className="text-xs font-semibold"
                />
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">
                  Nomor WhatsApp PIC / CS <span className="text-red-500">*</span>:
                </label>
                <Input
                  value={formData.phone_wa}
                  onChange={(e) => setFormData({ ...formData, phone_wa: e.target.value })}
                  placeholder="Contoh: 081234567890 / 628..."
                  className="text-xs font-mono font-semibold text-emerald-700"
                />
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
            placeholder="Cari berdasarkan nama kantor, kota, kode pos, atau nomor WA..."
            className="pl-9 text-xs"
          />
        </div>

        {/* Table of Post Offices */}
        <div className="pos-scroll overflow-x-auto border border-border rounded-xl max-h-[50vh]">
          <table className="w-full text-xs text-left border-collapse">
            <thead className="bg-[#1E40AF] text-white sticky top-0 z-10 font-semibold">
              <tr>
                <th className="py-2.5 px-3">No</th>
                <th className="py-2.5 px-3">Nama Kantor Pos (KC)</th>
                <th className="py-2.5 px-3">Kota &amp; Provinsi</th>
                <th className="py-2.5 px-3">Nomor WhatsApp</th>
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
                      {po.phone_wa ? (
                        <button
                          type="button"
                          onClick={() => handleChatOffice(po)}
                          className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 font-mono font-bold text-xs shadow-sm transition hover:scale-105 cursor-pointer group"
                          title="Klik untuk langsung chat WhatsApp ke KC ini"
                        >
                          <Phone className="w-3.5 h-3.5 text-emerald-600 group-hover:scale-110 transition" />
                          <span>{po.phone_wa}</span>
                          <span className="text-[10px] bg-emerald-600 text-white font-sans px-1.5 py-0.2 rounded-full font-bold ml-0.5">
                            Chat WA
                          </span>
                        </button>
                      ) : (
                        <span className="text-slate-400 italic">-</span>
                      )}
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
