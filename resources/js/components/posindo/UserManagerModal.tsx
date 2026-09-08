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
import { toast } from "sonner";
import {
  Users,
  UserPlus,
  Search,
  Shield,
  Headphones,
  Edit2,
  Trash2,
  Save,
  X,
  KeyRound,
  RefreshCw,
} from "lucide-react";
import axios from "axios";

export interface SystemUser {
  id: number;
  name: string;
  email: string;
  role: "admin" | "cs";
  created_at: string;
}

interface Props {
  isOpen: boolean;
  onClose: () => void;
  currentUserId?: number;
}

export function UserManagerModal({ isOpen, onClose, currentUserId }: Props) {
  const [users, setUsers] = useState<SystemUser[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState<string>("all");

  const [isAddingNew, setIsAddingNew] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);

  const [formData, setFormData] = useState({
    name: "",
    email: "",
    role: "cs" as "admin" | "cs",
    password: "",
  });

  const fetchUsers = async () => {
    try {
      setLoading(true);
      const res = await axios.get("/users");
      if (res.data?.success) {
        setUsers(res.data.data || []);
      }
    } catch (err: any) {
      toast.error("Gagal mengambil daftar pengguna: " + (err.response?.data?.message || err.message));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isOpen) {
      fetchUsers();
      setIsAddingNew(false);
      setEditingId(null);
    }
  }, [isOpen]);

  const filteredUsers = useMemo(() => {
    return users.filter((u) => {
      const matchSearch =
        u.name.toLowerCase().includes(search.toLowerCase()) ||
        u.email.toLowerCase().includes(search.toLowerCase());
      const matchRole = roleFilter === "all" || u.role === roleFilter;
      return matchSearch && matchRole;
    });
  }, [users, search, roleFilter]);

  const handleStartAdd = () => {
    setEditingId(null);
    setFormData({
      name: "",
      email: "",
      role: "cs",
      password: "",
    });
    setIsAddingNew(true);
  };

  const handleStartEdit = (user: SystemUser) => {
    setIsAddingNew(false);
    setEditingId(user.id);
    setFormData({
      name: user.name,
      email: user.email,
      role: user.role,
      password: "",
    });
  };

  const handleCancelForm = () => {
    setIsAddingNew(false);
    setEditingId(null);
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!formData.name.trim() || !formData.email.trim()) {
      toast.error("Nama dan Email wajib diisi");
      return;
    }

    if (isAddingNew && (!formData.password || formData.password.length < 6)) {
      toast.error("Kata sandi awal minimal harus 6 karakter");
      return;
    }

    try {
      setLoading(true);
      if (isAddingNew) {
        const res = await axios.post("/users", formData);
        toast.success("Pengguna berhasil ditambahkan!", {
          description: res.data?.message,
        });
      } else if (editingId) {
        const payload: Record<string, any> = {
          name: formData.name,
          email: formData.email,
          role: formData.role,
        };
        if (formData.password && formData.password.trim().length > 0) {
          payload.password = formData.password.trim();
        }
        const res = await axios.put(`/users/${editingId}`, payload);
        toast.success("Data pengguna diperbarui!", {
          description: res.data?.message,
        });
      }
      handleCancelForm();
      fetchUsers();
    } catch (err: any) {
      toast.error("Gagal menyimpan pengguna", {
        description: err.response?.data?.message || err.message,
      });
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (user: SystemUser) => {
    if (user.id === currentUserId) {
      toast.error("Anda tidak bisa menghapus akun Anda sendiri!");
      return;
    }

    if (!confirm(`Yakin ingin menghapus pengguna ${user.name} (${user.email})? Tindakan ini tidak dapat dibatalkan.`)) {
      return;
    }

    try {
      setLoading(true);
      const res = await axios.delete(`/users/${user.id}`);
      toast.success("Pengguna berhasil dihapus", {
        description: res.data?.message,
      });
      fetchUsers();
    } catch (err: any) {
      toast.error("Gagal menghapus pengguna", {
        description: err.response?.data?.message || err.message,
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto p-6 rounded-2xl bg-white border border-slate-200">
        <DialogHeader className="border-b pb-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-purple-600 text-white flex items-center justify-center font-bold text-xl shadow-md">
                <Users className="w-5 h-5" />
              </div>
              <div>
                <DialogTitle className="text-lg font-bold text-slate-900 flex items-center gap-2">
                  Manajemen Pengguna &amp; Tim CS
                  <span className="text-xs font-semibold px-2.5 py-0.5 bg-purple-100 text-purple-800 rounded-full">
                    {users.length} User Terdaftar
                  </span>
                </DialogTitle>
                <p className="text-xs text-muted-foreground mt-0.5">
                  Kelola staf Customer Service (CS) dan Administrator sistem Posindo Tracking.
                </p>
              </div>
            </div>

            {!isAddingNew && !editingId && (
              <Button
                onClick={handleStartAdd}
                size="sm"
                className="bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm"
              >
                <UserPlus className="w-4 h-4" />
                Tambah Pengguna
              </Button>
            )}
          </div>
        </DialogHeader>

        {/* Form Tambah / Edit Pengguna */}
        {(isAddingNew || editingId) && (
          <form onSubmit={handleSave} className="bg-purple-50/60 p-4 rounded-xl border border-purple-200 my-2 space-y-3">
            <div className="flex items-center justify-between border-b border-purple-200/80 pb-2">
              <h4 className="text-xs font-bold text-purple-900 flex items-center gap-1.5">
                {isAddingNew ? <UserPlus className="w-4 h-4 text-purple-600" /> : <Edit2 className="w-4 h-4 text-purple-600" />}
                {isAddingNew ? "Tambah Pengguna Baru" : "Edit Akun Pengguna"}
              </h4>
              <button
                type="button"
                onClick={handleCancelForm}
                className="text-slate-400 hover:text-slate-600 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label className="text-xs font-bold text-slate-700 block mb-1">Nama Lengkap:</label>
                <Input
                  required
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="Contoh: Rina Amalia"
                  className="text-xs bg-white"
                />
              </div>

              <div>
                <label className="text-xs font-bold text-slate-700 block mb-1">Email / Username Login:</label>
                <Input
                  required
                  type="email"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  placeholder="Contoh: cs.rina@posindo.com"
                  className="text-xs bg-white"
                />
              </div>

              <div>
                <label className="text-xs font-bold text-slate-700 block mb-1">Role / Hak Akses:</label>
                <select
                  value={formData.role}
                  onChange={(e) => setFormData({ ...formData, role: e.target.value as "admin" | "cs" })}
                  className="w-full text-xs font-semibold bg-white border border-slate-300 rounded-lg px-2.5 py-2 focus:ring-2 focus:ring-purple-500 outline-none"
                >
                  <option value="cs">Customer Service (CS)</option>
                  <option value="admin">Administrator</option>
                </select>
                <p className="text-[10px] text-slate-500 mt-1">
                  {formData.role === "admin"
                    ? "Akses penuh: kelola user, sinkronisasi Google Sheets, impor data & konfigurasi NIPOS."
                    : "Akses operasional: update status, kirim WA follow-up, update warna & catatan."}
                </p>
              </div>

              <div>
                <label className="text-xs font-bold text-slate-700 block mb-1">
                  {isAddingNew ? "Kata Sandi Awal:" : "Reset Kata Sandi (Kosongkan jika tidak diubah):"}
                </label>
                <Input
                  type="password"
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  placeholder={isAddingNew ? "Minimal 6 karakter" : "Biarkan kosong bila tidak ingin ganti"}
                  className="text-xs bg-white"
                />
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={handleCancelForm}
                className="text-xs"
              >
                Batal
              </Button>
              <Button
                type="submit"
                size="sm"
                disabled={loading}
                className="bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs flex items-center gap-1.5"
              >
                <Save className="w-3.5 h-3.5" />
                {loading ? "Menyimpan..." : "Simpan Pengguna"}
              </Button>
            </div>
          </form>
        )}

        {/* Filter & Search Bar */}
        <div className="flex flex-col sm:flex-row gap-2 py-2">
          <div className="relative flex-1">
            <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Cari nama atau email pengguna..."
              className="pl-8 text-xs bg-slate-50"
            />
          </div>

          <div className="flex gap-1.5">
            <select
              value={roleFilter}
              onChange={(e) => setRoleFilter(e.target.value)}
              className="text-xs font-semibold bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-purple-500 outline-none"
            >
              <option value="all">Semua Role</option>
              <option value="admin">Administrator</option>
              <option value="cs">Customer Service</option>
            </select>

            <Button
              variant="outline"
              size="sm"
              onClick={fetchUsers}
              disabled={loading}
              className="text-xs flex items-center gap-1"
              title="Refresh data"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin" : ""}`} />
            </Button>
          </div>
        </div>

        {/* User Table List */}
        <div className="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
          <table className="w-full text-xs text-left">
            <thead className="bg-slate-100/90 text-slate-700 font-bold border-b border-slate-200">
              <tr>
                <th className="p-2.5 pl-3">Pengguna</th>
                <th className="p-2.5">Email</th>
                <th className="p-2.5">Role</th>
                <th className="p-2.5">Terdaftar</th>
                <th className="p-2.5 pr-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 bg-white">
              {filteredUsers.length === 0 ? (
                <tr>
                  <td colSpan={5} className="text-center py-8 text-slate-400">
                    Tidak ada pengguna yang cocok dengan pencarian.
                  </td>
                </tr>
              ) : (
                filteredUsers.map((u) => {
                  const isMe = u.id === currentUserId;
                  return (
                    <tr key={u.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="p-2.5 pl-3 font-semibold text-slate-800 flex items-center gap-2">
                        <div className="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center font-bold text-[11px] text-slate-700 border border-slate-200">
                          {u.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                          <span>{u.name}</span>
                          {isMe && (
                            <span className="ml-2 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                              (Anda)
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="p-2.5 text-slate-600 font-mono text-[11px]">{u.email}</td>
                      <td className="p-2.5">
                        {u.role === "admin" ? (
                          <span className="inline-flex items-center gap-1 font-bold text-[10px] bg-purple-100 text-purple-800 px-2 py-0.5 rounded-full">
                            <Shield className="w-3 h-3" />
                            ADMIN
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 font-bold text-[10px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">
                            <Headphones className="w-3 h-3" />
                            CS
                          </span>
                        )}
                      </td>
                      <td className="p-2.5 text-slate-400 text-[11px]">
                        {u.created_at ? u.created_at.slice(0, 10) : "-"}
                      </td>
                      <td className="p-2.5 pr-3 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            type="button"
                            onClick={() => handleStartEdit(u)}
                            className="p-1 rounded text-slate-500 hover:text-purple-600 hover:bg-purple-50 transition cursor-pointer"
                            title="Edit / Reset Password"
                          >
                            <Edit2 className="w-3.5 h-3.5" />
                          </button>
                          {!isMe && (
                            <button
                              type="button"
                              onClick={() => handleDelete(u)}
                              className="p-1 rounded text-slate-500 hover:text-red-600 hover:bg-red-50 transition cursor-pointer"
                              title="Hapus Akun"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        <DialogFooter className="border-t pt-3 flex items-center justify-between">
          <p className="text-[11px] text-slate-500">
            💡 Password akun yang baru dibuat atau di-reset dapat langsung digunakan untuk login.
          </p>
          <Button variant="outline" onClick={onClose} size="sm" className="text-xs">
            Tutup
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
