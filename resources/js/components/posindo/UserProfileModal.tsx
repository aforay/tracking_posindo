import { useState } from "react";
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
import { KeyRound, Lock, UserCheck, Shield } from "lucide-react";
import axios from "axios";

interface Props {
  isOpen: boolean;
  onClose: () => void;
  currentUser?: {
    id: number;
    name: string;
    email: string;
    role: string;
  } | null;
}

export function UserProfileModal({ isOpen, onClose, currentUser }: Props) {
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!currentPassword) {
      toast.error("Masukkan kata sandi saat ini");
      return;
    }

    if (newPassword.length < 6) {
      toast.error("Kata sandi baru minimal harus 6 karakter");
      return;
    }

    if (newPassword !== confirmPassword) {
      toast.error("Konfirmasi kata sandi baru tidak cocok");
      return;
    }

    try {
      setLoading(true);
      const res = await axios.post("/profile/password", {
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      });

      toast.success("Kata Sandi Berhasil Diperbarui!", {
        description: res.data?.message || "Silakan gunakan password baru untuk login berikutnya.",
      });

      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
      onClose();
    } catch (err: any) {
      toast.error("Gagal Memperbarui Kata Sandi", {
        description: err.response?.data?.message || err.message,
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-md p-6 rounded-2xl bg-white border border-slate-200">
        <DialogHeader className="border-b pb-3">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-xl shadow-md">
              <KeyRound className="w-5 h-5" />
            </div>
            <div>
              <DialogTitle className="text-lg font-bold text-slate-900">
                Ubah Kata Sandi Akun
              </DialogTitle>
              <p className="text-xs text-muted-foreground mt-0.5">
                Perbarui kata sandi untuk menjaga keamanan akun Anda.
              </p>
            </div>
          </div>
        </DialogHeader>

        {currentUser && (
          <div className="bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs space-y-1">
            <div className="flex items-center justify-between">
              <span className="text-slate-500">Nama Pengguna:</span>
              <span className="font-bold text-slate-800">{currentUser.name}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-slate-500">Email Akun:</span>
              <span className="font-mono text-slate-700">{currentUser.email}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-slate-500">Hak Akses:</span>
              <span className="font-semibold uppercase text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                {currentUser.role}
              </span>
            </div>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-3 py-1">
          <div>
            <label className="text-xs font-bold text-slate-700 block mb-1">
              Kata Sandi Saat Ini:
            </label>
            <Input
              type="password"
              required
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              placeholder="Masukkan password saat ini..."
              className="text-xs"
            />
          </div>

          <div>
            <label className="text-xs font-bold text-slate-700 block mb-1">
              Kata Sandi Baru:
            </label>
            <Input
              type="password"
              required
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              placeholder="Minimal 6 karakter..."
              className="text-xs"
            />
          </div>

          <div>
            <label className="text-xs font-bold text-slate-700 block mb-1">
              Konfirmasi Kata Sandi Baru:
            </label>
            <Input
              type="password"
              required
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              placeholder="Ulangi kata sandi baru..."
              className="text-xs"
            />
          </div>

          <DialogFooter className="border-t pt-3 flex items-center justify-between gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              className="text-xs"
            >
              Batal
            </Button>
            <Button
              type="submit"
              disabled={loading}
              className="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm"
            >
              <Lock className="w-3.5 h-3.5" />
              {loading ? "Menyimpan..." : "Simpan Kata Sandi"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
