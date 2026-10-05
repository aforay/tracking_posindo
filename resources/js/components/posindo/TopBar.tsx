import { useMemo, useEffect, useState, useCallback, useRef } from "react";
import { router } from "@inertiajs/react";
import { motion, AnimatePresence } from "framer-motion";
import { Truck, Bot, Loader2, CheckCircle2, Zap, RefreshCw, Send, Building2, AlertCircle, Check, ArrowUpRight, Search, Cookie, LogOut, ShieldCheck, User, Users, KeyRound, AlertTriangle, ChevronDown, Trash2, Package, Palette, Laptop, Smartphone, Radio } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { Progress } from "@/components/ui/progress";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { SELLERS, nf } from "@/lib/posindo";
import { NiposCookieModal } from "./NiposCookieModal";
import { UserManagerModal } from "./UserManagerModal";
import { UserProfileModal } from "./UserProfileModal";

export function TopBar({
  seller,
  sellersList,
  onSeller,
  total,
  month,
  monthPendingCounts,
  trackingProgress,
  googleSheetUrl,
  googleSheetId,
  googleSheetWebhookUrl,
  googleSheetUrlAliqa,
  googleSheetUrlZaherba,
  googleSheetWebhookUrlAliqa,
  googleSheetWebhookUrlZaherba,
  onOpenPostOffices,
  currentUser,
}: {
  seller: string;
  sellersList?: string[];
  onSeller: (s: string) => void;
  total: number;
  month?: string | number;
  monthPendingCounts?: number[];
  trackingProgress?: { percentage: number; tracked: number; total: number; is_running: boolean; pending?: number };
  googleSheetUrl?: string;
  googleSheetId?: string;
  googleSheetWebhookUrl?: string;
  googleSheetUrlAliqa?: string;
  googleSheetUrlZaherba?: string;
  googleSheetWebhookUrlAliqa?: string;
  googleSheetWebhookUrlZaherba?: string;
  onOpenPostOffices?: () => void;
  currentUser?: { id: number; name: string; email: string; role: string; is_admin: boolean } | null;
}) {
  const isAdmin = currentUser ? (currentUser.is_admin ?? currentUser.role === "admin") : true;
  const monthNames = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember"
  ];

  const activeBotMonth = useMemo(() => {
    if (typeof month === "number" && month >= 1 && month <= 12) {
      return month;
    }
    if (typeof month === "string" && !isNaN(Number(month)) && Number(month) >= 1 && Number(month) <= 12) {
      return Number(month);
    }
    // Jika filter month adalah "all" / belum dipilih spesifik:
    // Cek apakah ada bulan yang memiliki data pending tracking (prioritaskan bulan terbaru yang ada data)
    if (monthPendingCounts && Array.isArray(monthPendingCounts)) {
      for (let m = monthPendingCounts.length; m >= 1; m--) {
        if ((monthPendingCounts[m - 1] ?? 0) > 0) {
          return m;
        }
      }
    }
    const nowMonth = new Date().getMonth() + 1;
    return nowMonth >= 1 && nowMonth <= 12 ? nowMonth : 9;
  }, [month, monthPendingCounts]);

  const activeBotMonthName = monthNames[activeBotMonth - 1] || "September";

  const userInitials = useMemo(() => {
    if (!currentUser?.name) return "AP";
    const parts = currentUser.name.trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return currentUser.name.slice(0, 2).toUpperCase();
  }, [currentUser?.name]);

  const targetMonthPending = useMemo(() => {
    if (monthPendingCounts && Array.isArray(monthPendingCounts) && monthPendingCounts[activeBotMonth - 1] !== undefined) {
      return monthPendingCounts[activeBotMonth - 1];
    }
    return trackingProgress?.pending ?? 0;
  }, [monthPendingCounts, activeBotMonth, trackingProgress?.pending]);

  const sellerOptions = useMemo(() => {
    return ["Mitra Aliqa", "Mitra Zaherba"];
  }, []);
  const normalizedSeller = useMemo(() => {
    if (seller && (seller.toUpperCase().includes("ZAHERBA") || seller === "Zaherba")) {
      return "Mitra Zaherba";
    }
    return "Mitra Aliqa";
  }, [seller]);
  const defaultSyncMonth = activeBotMonth;
  const [selectedSyncMonths, setSelectedSyncMonths] = useState<number[]>([defaultSyncMonth]);
  const [isSyncAllMonths, setIsSyncAllMonths] = useState<boolean>(false);
  const [selectedSyncMonth, setSelectedSyncMonth] = useState<string>("current");
  const [includeFuColors, setIncludeFuColors] = useState<boolean>(true);
  const [isSyncingColors, setIsSyncingColors] = useState<boolean>(false);
  const [sheetSyncOpen, setSheetSyncOpen] = useState(false);
  const [sheetSettingsOpen, setSheetSettingsOpen] = useState(false);

  // Auto-sync default month when seller or activeBotMonth changes
  useEffect(() => {
    if (!isSyncAllMonths && selectedSyncMonths.length === 1) {
      setSelectedSyncMonths([activeBotMonth]);
    }
  }, [activeBotMonth]);

  const handlePresetCurrentMonth = () => {
    setIsSyncAllMonths(false);
    setSelectedSyncMonths([activeBotMonth]);
  };

  const handlePresetLast3Months = () => {
    setIsSyncAllMonths(false);
    const cur = activeBotMonth;
    const m1 = cur - 2 <= 0 ? cur - 2 + 12 : cur - 2;
    const m2 = cur - 1 <= 0 ? cur - 1 + 12 : cur - 1;
    const months = Array.from(new Set([m1, m2, cur])).sort((a, b) => a - b);
    setSelectedSyncMonths(months);
  };

  const handlePresetLast4Months = () => {
    setIsSyncAllMonths(false);
    const cur = activeBotMonth;
    const m1 = cur - 3 <= 0 ? cur - 3 + 12 : cur - 3;
    const m2 = cur - 2 <= 0 ? cur - 2 + 12 : cur - 2;
    const m3 = cur - 1 <= 0 ? cur - 1 + 12 : cur - 1;
    const months = Array.from(new Set([m1, m2, m3, cur])).sort((a, b) => a - b);
    setSelectedSyncMonths(months);
  };

  const handlePresetAllMonths = () => {
    setIsSyncAllMonths(true);
    setSelectedSyncMonths([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]);
  };

  const toggleMonthSelection = (mNum: number) => {
    setIsSyncAllMonths(false);
    setSelectedSyncMonths((prev) => {
      if (prev.includes(mNum)) {
        if (prev.length === 1) return prev; // keep at least 1 month
        return prev.filter((m) => m !== mNum);
      } else {
        return [...prev, mNum].sort((a, b) => a - b);
      }
    });
  };

  // Per-seller URL states (for settings modal)
  const [settingsAliqaUrl, setSettingsAliqaUrl] = useState(
    googleSheetUrlAliqa || "https://docs.google.com/spreadsheets/d/1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg/edit"
  );
  const [settingsZaherbaUrl, setSettingsZaherbaUrl] = useState(
    googleSheetUrlZaherba || "https://docs.google.com/spreadsheets/d/1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw/edit"
  );
  const [settingsAliqaWebhook, setSettingsAliqaWebhook] = useState(googleSheetWebhookUrlAliqa || googleSheetWebhookUrl || "");
  const [settingsZaherbaWebhook, setSettingsZaherbaWebhook] = useState(googleSheetWebhookUrlZaherba || "");
  const [isSavingSettings, setIsSavingSettings] = useState(false);

  // Sync when props change (e.g. page reload)
  useEffect(() => {
    if (googleSheetUrlAliqa) setSettingsAliqaUrl(googleSheetUrlAliqa);
    if (googleSheetUrlZaherba) setSettingsZaherbaUrl(googleSheetUrlZaherba);
    if (googleSheetWebhookUrlAliqa) setSettingsAliqaWebhook(googleSheetWebhookUrlAliqa);
    else if (googleSheetWebhookUrl) setSettingsAliqaWebhook(googleSheetWebhookUrl);
    if (googleSheetWebhookUrlZaherba) setSettingsZaherbaWebhook(googleSheetWebhookUrlZaherba);
  }, [googleSheetUrlAliqa, googleSheetUrlZaherba, googleSheetWebhookUrl, googleSheetWebhookUrlAliqa, googleSheetWebhookUrlZaherba]);

  // Dynamic sheetUrlInput — strictly reads active seller URL
  const sheetUrlInput = normalizedSeller === "Mitra Zaherba"
    ? (googleSheetUrlZaherba || "https://docs.google.com/spreadsheets/d/1wUqPnU1_QOq6WocHwpxAhjhScjlb_ZhhSy8I2WqGQKw/edit")
    : (googleSheetUrlAliqa || "https://docs.google.com/spreadsheets/d/1EeckOBzI5EPNTT1bHsqu6kar9asKD6Ifar2CpTkSnBg/edit");
  const webhookUrlInput = normalizedSeller === "Mitra Zaherba" ? (settingsZaherbaWebhook || settingsAliqaWebhook) : settingsAliqaWebhook;
  const [isSyncing, setIsSyncing] = useState(false);
  const [sheetSyncState, setSheetSyncState] = useState<"idle" | "syncing" | "done">("idle");

  const handleSaveSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSavingSettings(true);
    try {
      const csrfToken = getCsrfToken();
      const res = await fetch("/settings/google-sheets", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({
          aliqa_url: settingsAliqaUrl.trim(),
          zaherba_url: settingsZaherbaUrl.trim(),
          webhook_url: (normalizedSeller === "Mitra Zaherba" ? settingsZaherbaWebhook : settingsAliqaWebhook).trim(),
          aliqa_webhook_url: settingsAliqaWebhook.trim(),
          zaherba_webhook_url: settingsZaherbaWebhook.trim(),
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        toast.error(data.message || "Gagal menyimpan pengaturan Google Sheets.");
      } else {
        toast.success("Pengaturan Google Sheets berhasil disimpan!", {
          description: "URL Spreadsheet per-seller telah diperbarui.",
        });
        setSheetSettingsOpen(false);
        // Refresh to reflect new URLs in Sync Sheets button
        router.reload({ preserveScroll: true });
      }
    } catch (err: any) {
      toast.error("Gagal menyimpan: " + (err.message || String(err)));
    } finally {
      setIsSavingSettings(false);
    }
  };


  const [sheetSyncProgress, setSheetSyncProgress] = useState(0);
  const [sheetSyncInfo, setSheetSyncInfo] = useState<{
    current_sheet: string;
    current_sheet_index: number;
    total_sheets: number;
    processed_rows: number;
    inserted_rows: number;
    message: string;
  }>({
    current_sheet: "",
    current_sheet_index: 0,
    total_sheets: 0,
    processed_rows: 0,
    inserted_rows: 0,
    message: "",
  });
  const [botState, setBotState] = useState<"idle" | "running" | "done">("idle");
  const [progress, setProgress] = useState(0);
  const [niposModalOpen, setNiposModalOpen] = useState(false);
  const [niposConnected, setNiposConnected] = useState<boolean | null>(null);
  const [userManagerOpen, setUserManagerOpen] = useState(false);
  const [userProfileOpen, setUserProfileOpen] = useState(false);

  // Background check status Cookie NIPOS secara otomatis
  useEffect(() => {
    const checkCookieStatus = () => {
      fetch("/settings/nipos-cookie/status")
        .then((res) => res.json())
        .then((data) => {
          if (data.status) {
            setNiposConnected(Boolean(data.status.connected));
          }
        })
        .catch(() => setNiposConnected(false));
    };

    checkCookieStatus();
    const cookieInterval = setInterval(checkCookieStatus, 5 * 60 * 1000);
    return () => clearInterval(cookieInterval);
  }, []);

  // State: Perangkat yang Sedang Aktif Membuka Web
  const [activeDevices, setActiveDevices] = useState<{
    count: number;
    devices: Array<{
      id: string;
      ip: string;
      user_name: string;
      user_role: string;
      device_type: string;
      browser: string;
      last_seen_human: string;
      is_current: boolean;
    }>;
  }>({ count: 1, devices: [] });
  const [devicesModalOpen, setDevicesModalOpen] = useState(false);

  const fetchDevices = useCallback(async () => {
    try {
      const res = await fetch("/active-devices");
      if (res.ok) {
        const data = await res.json();
        if (data.success) {
          setActiveDevices({ count: data.count, devices: data.devices });
        }
      }
    } catch (e) {
      // silent ignore
    }
  }, []);

  useEffect(() => {
    fetchDevices();
    // Realtime heartbeat setiap 4 detik (background silent AJAX tanpa reload halaman / anti-lag)
    const devInterval = setInterval(fetchDevices, 4000);
    return () => clearInterval(devInterval);
  }, [fetchDevices]);

  const [syncData, setSyncData] = useState<{
    is_syncing: boolean;
    current_sheet: string;
    current_sheet_index: number;
    total_sheets: number;
    processed_rows: number;
    inserted_rows: number;
    percentage: number;
    message: string;
  }>({
    is_syncing: false,
    current_sheet: "",
    current_sheet_index: 0,
    total_sheets: 0,
    processed_rows: 0,
    inserted_rows: 0,
    percentage: 0,
    message: "",
  });

  const checkSyncProgressRef = useRef<() => void>(() => {});

  // Poll background sync progress (Cerdas & Hemat Sumber Daya)
  useEffect(() => {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let isMounted = true;

    const checkSyncProgress = async () => {
      // 1. Lewati jika tab browser sedang di latar belakang
      if (typeof document !== "undefined" && document.hidden) {
        if (isMounted) timer = setTimeout(checkSyncProgress, 15000);
        return;
      }

      try {
        const res = await fetch("/sync/progress");
        if (res.ok) {
          const data = await res.json();
          if (!isMounted) return;

          setSyncData((prev) => {
            if (prev.is_syncing && !data.is_syncing && data.percentage === 100) {
              toast.success("Sinkronisasi Selesai!", {
                description: `${nf(data.processed_rows || 0)} data berhasil disinkronkan. Halaman diperbarui.`,
              });
              router.reload({ preserveScroll: true });
            }
            return data;
          });

          // Jika sedang sync: cek setiap 1.5 detik agar bar responsif.
          // Jika idle: cek setiap 10 detik agar deteksi sync dari device lain cepat.
          const delay = data.is_syncing ? 1500 : 10000;
          if (isMounted) timer = setTimeout(checkSyncProgress, delay);
          return;
        }
      } catch (e) {
        // ignore network hiccups
      }

      if (isMounted) {
        timer = setTimeout(checkSyncProgress, 10000);
      }
    };

    checkSyncProgressRef.current = checkSyncProgress;
    checkSyncProgress();

    return () => {
      isMounted = false;
      if (timer) clearTimeout(timer);
    };
  }, []);

  const getCsrfToken = () => {
    const meta = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
    if (meta) return meta;
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : "";
  };

  const [botInfo, setBotInfo] = useState<{ current: number; total: number } | null>(null);
  const [botResultModalOpen, setBotResultModalOpen] = useState(false);
  const [botUpdatedItems, setBotUpdatedItems] = useState<Array<{
    id: string;
    resi: string;
    seller: string;
    status_pos: string;
    keterangan: string;
    status_kategori: string;
    color_code: string;
    sla?: number;
    last_tracked_at: string;
  }>>([]);
  const [botFilterQuery, setBotFilterQuery] = useState("");

  const runBot = async () => {
    if (botState === "running") return;
    setBotState("running");
    setProgress(0);
    setBotInfo(null);
    setBotUpdatedItems([]);

    try {
      const csrfToken = getCsrfToken();
      const sessionStart = new Date().toISOString();
      let isFinishedGlobally = false;
      let totalProcessed = 0;
      let initialTotalPending = 0;
      let batchCount = 0;
      const accumulatedUpdated: any[] = [];

      while (!isFinishedGlobally) {
        batchCount++;
        try {
          const res = await fetch("/bot/start-tracking", {
            method: "POST",
            headers: {
              "Accept": "application/json",
              "Content-Type": "application/json",
              "X-CSRF-TOKEN": csrfToken,
              "X-XSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify({
              limit: 500,
              seller: seller || normalizedSeller,
              month: activeBotMonth,
              session_start: sessionStart,
            }),
          });

          if (!res.ok) {
            break;
          }

          const data = await res.json();
          if (!data.success) {
            break;
          }

          const processedThisBatch = data.processed_count ?? 0;
          const remainingPending = data.pending_count ?? 0;
          totalProcessed += processedThisBatch;

          if (Array.isArray(data.updated_items) && data.updated_items.length > 0) {
            accumulatedUpdated.push(...data.updated_items);
            setBotUpdatedItems(accumulatedUpdated.slice(-500));
          }

          if (initialTotalPending === 0) {
            initialTotalPending = (data.total_pending && data.total_pending > 0)
              ? data.total_pending
              : (totalProcessed + remainingPending);
          }

          const effectiveTotal = Math.max(initialTotalPending, 1);
          const displayCurrent = Math.min(totalProcessed, effectiveTotal);
          const currentPct = effectiveTotal > 0
            ? Math.min(100, Math.round((displayCurrent / effectiveTotal) * 100))
            : 100;

          setBotInfo({ current: displayCurrent, total: effectiveTotal });
          setProgress(currentPct);

          const maxAllowedBatches = Math.max(8, Math.ceil(effectiveTotal / 500) + 2);
          if (data.is_finished || remainingPending === 0 || processedThisBatch === 0 || batchCount >= maxAllowedBatches) {
            isFinishedGlobally = true;
            break;
          }
        } catch (e) {
          break;
        }
      }

      // Explicitly set 100% completion
      setProgress(100);
      setBotState("done");
      if (initialTotalPending > 0) {
        setBotInfo({ current: Math.min(totalProcessed, initialTotalPending), total: initialTotalPending });
      } else if (totalProcessed > 0) {
        setBotInfo({ current: totalProcessed, total: totalProcessed });
      }

      toast.success(`Pelacakan Bot NIPOS Selesai (${activeBotMonthName})!`, {
        description: totalProcessed > 0
          ? `${nf(totalProcessed)} data resi bulan ${activeBotMonthName} berhasil diperbarui dari NIPOS.`
          : `Semua data resi bulan ${activeBotMonthName} sudah berstatus SUKSES/RETUR.`,
      });

      // Buka modal pemberitahuan hasil update bot agar user bisa verifikasi
      if (accumulatedUpdated.length > 0) {
        setBotResultModalOpen(true);
      }

      // Reload background Inertia data
      router.reload({ preserveScroll: true });

      setTimeout(() => {
        setBotState("idle");
        setProgress(0);
        setBotInfo(null);
      }, 3000);

    } catch (err: unknown) {
      setBotState("idle");
      setProgress(0);
      setBotInfo(null);
      toast.error("Gagal menjalankan tracking bot: " + String(err));
    }
  };


  const handleSyncSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!sheetUrlInput.trim()) {
      toast.error("Silakan masukkan URL / ID Google Spreadsheet.");
      return;
    }

    setSheetSyncOpen(false);
    setIsSyncing(true);
    setSheetSyncState("syncing");
    setSheetSyncProgress(0);
    setSheetSyncInfo({
      current_sheet: "Menghubungkan...",
      current_sheet_index: 0,
      total_sheets: 0,
      processed_rows: 0,
      inserted_rows: 0,
      message: "Menghubungkan ke Google Sheets...",
    });

    try {
      const csrfToken = getCsrfToken();
      
      const payload: Record<string, any> = {
        url: sheetUrlInput,
        webhook_url: webhookUrlInput,
        seller: normalizedSeller,
      };

      if (isSyncAllMonths) {
        payload.month = "ALL";
        payload.months = ["ALL"];
      } else {
        payload.months = selectedSyncMonths;
        payload.month = selectedSyncMonths.length === 1 ? selectedSyncMonths[0] : selectedSyncMonths.join(",");
      }

      // 1. Discover Sheet Names
      const discoverRes = await fetch("/sync/discover", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify(payload),
      });

      if (!discoverRes.ok) {
        const errData = await discoverRes.json();
        throw new Error(errData.message || "Gagal menghubungi Google Sheets");
      }

      const discoverData = await discoverRes.json();
      if (!discoverData.success || !discoverData.sheet_names || discoverData.sheet_names.length === 0) {
        throw new Error(discoverData.message || "Tidak ada sheet yang ditemukan.");
      }

      const sheetNames: string[] = discoverData.sheet_names;
      const totalSheets = sheetNames.length;
      const spreadsheetId = discoverData.spreadsheet_id;

      let totalProcessed = 0;
      let totalInserted = 0;
      let totalColors = 0;

      // 2. Loop Sheet-by-sheet (Ultra-fast direct CSV export, without slow per-sheet webhook)
      for (let i = 0; i < totalSheets; i++) {
        const sheetName = sheetNames[i];
        
        setSheetSyncInfo({
          current_sheet: sheetName,
          current_sheet_index: i + 1,
          total_sheets: totalSheets,
          processed_rows: totalProcessed,
          inserted_rows: totalInserted,
          message: `Menyinkronkan data: ${sheetName} (${i + 1}/${totalSheets})...`,
        });

        const syncRes = await fetch("/sync/sheet", {
          method: "POST",
          headers: {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken,
          },
          body: JSON.stringify({
            spreadsheet_id: spreadsheetId,
            sheet_name: sheetName,
            seller: normalizedSeller,
            with_colors: false,
          }),
        });

        if (!syncRes.ok) {
          toast.warning(`Gagal sinkronisasi sheet ${sheetName}, melanjutkan ke sheet berikutnya...`);
          continue;
        }

        const syncResult = await syncRes.json();
        if (syncResult.success) {
          totalProcessed += syncResult.processed ?? 0;
          totalInserted += syncResult.inserted ?? 0;
        }

        const currentPct = Math.round(((i + 1) / totalSheets) * 100);
        setSheetSyncProgress(currentPct);
      }

      // 3. Tarik Warna FU (Hanya 1x untuk sheet bulan aktif agar super cepat & tidak lemot)
      if (includeFuColors) {
        const activeMonthName = monthNames[activeBotMonth - 1]?.toUpperCase() || "";
        const targetColorSheet = sheetNames.find(s => s.toUpperCase().includes(activeMonthName))
          || sheetNames[sheetNames.length - 1];

        setSheetSyncInfo({
          current_sheet: "Format Warna",
          current_sheet_index: totalSheets,
          total_sheets: totalSheets,
          processed_rows: totalProcessed,
          inserted_rows: totalInserted,
          message: `Menarik format warna status FU (${targetColorSheet})...`,
        });

        try {
          const colorRes = await fetch("/sync/colors", {
            method: "POST",
            headers: {
              "Accept": "application/json",
              "Content-Type": "application/json",
              "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify({
              url: sheetUrlInput,
              spreadsheet_id: spreadsheetId,
              seller: normalizedSeller,
              sheet_name: targetColorSheet,
            }),
          });
          if (colorRes.ok) {
            const colorData = await colorRes.json();
            if (colorData.success) {
              totalColors += (colorData.updated_count ?? colorData.total_colors_updated ?? 0);
            }
          }
        } catch (colorErr) {
          console.warn("Gagal menarik warna FU:", colorErr);
        }
      }

      setSheetSyncProgress(100);
      setSheetSyncState("done");
      
      toast.success("Sinkronisasi Selesai!", {
        description: totalColors > 0
          ? `${nf(totalProcessed)} data dan ${nf(totalColors)} status warna FU berhasil disinkronkan.`
          : `${nf(totalProcessed)} data pengiriman berhasil disinkronkan.`,
      });

      router.reload({ preserveScroll: true });

      setTimeout(() => {
        setSheetSyncState("idle");
        setSheetSyncProgress(0);
        setIsSyncing(false);
      }, 2500);

    } catch (err: unknown) {
      setSheetSyncState("idle");
      setSheetSyncProgress(0);
      setIsSyncing(false);
      const msg = (err as any)?.message || String(err);
      toast.error(msg.startsWith("Gagal") ? msg : `Gagal sinkronisasi: ${msg}`, { duration: 6000 });
    }
  };

  const handleSyncColorsQuick = async () => {
    if (isSyncingColors || isSyncing) return;
    setIsSyncingColors(true);
    const toastId = toast.loading(`Menarik data format warna dari Google Sheets ${normalizedSeller}...`);

    try {
      const csrfToken = getCsrfToken();
      const targetMonth = activeBotMonth;
      const res = await fetch("/sync/colors", {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({
          seller: normalizedSeller,
          month: targetMonth,
        }),
      });

      const data = await res.json();
      if (res.ok && data.success) {
        toast.success(`Warna Berhasil Disinkronkan!`, {
          id: toastId,
          description: data.message || `${data.updated_count || 0} warna resi berhasil diperbarui dari sheet.`,
        });
        router.reload({ preserveScroll: true });
      } else {
        toast.error("Gagal menarik warna", {
          id: toastId,
          description: data.message || "Pastikan URL Webhook di Pengaturan sudah aktif.",
        });
      }
    } catch (err: any) {
      toast.error("Gagal menghubungi server", {
        id: toastId,
        description: err.message || "Terjadi kesalahan saat menarik warna.",
      });
    } finally {
      setIsSyncingColors(false);
    }
  };

  const badge =
    botState === "idle"
      ? {
          text: targetMonthPending > 0 ? `${nf(targetMonthPending)} Pending` : "All Done",
          cls: targetMonthPending > 0 ? "bg-amber-500 text-white" : "bg-emerald-500 text-white",
        }
      : botState === "running"
      ? { text: `Tracking ${activeBotMonthName}...`, cls: "bg-pos-orange text-white animate-pulse" }
      : { text: "Completed ✓", cls: "bg-emerald-500 text-white" };

  return (
    <div className="border-b border-slate-200/80 bg-white">
      <div className="flex flex-wrap lg:flex-nowrap items-center justify-between gap-2.5 sm:gap-4 px-3 sm:px-5 py-2 sm:py-2.5 w-full max-w-[1920px] 2xl:max-w-[2200px] mx-auto">
        {/* Brand Logo & System Info */}
        <div className="flex items-center gap-2 sm:gap-3 flex-wrap sm:flex-nowrap shrink-0">
          <div className="relative grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-[#1E40AF] to-blue-800 text-white shadow-md shadow-blue-900/20 shrink-0">
            <Truck className="h-5 w-5" />
            <span className="absolute -bottom-0.5 -right-0.5 grid h-4 w-4 place-items-center rounded-full bg-[#F97316] text-white shadow-xs">
              <Zap className="h-2.5 w-2.5" />
            </span>
          </div>
          <div className="flex items-center gap-1.5">
            <h1 className="text-base font-black tracking-wider text-[#1E40AF]">
              TRACKO
            </h1>
            <span className="rounded bg-blue-100/90 px-1.5 py-0.5 text-[9px] font-extrabold text-blue-800 border border-blue-200">SYSTEM</span>
          </div>

          {/* Segmented Dashboard Switcher: Mitra Aliqa vs Mitra Zaherba */}
          <div className="ml-1 sm:ml-3 flex items-center rounded-xl bg-slate-100 p-1 border border-slate-200/90 shadow-inner">
            <button
              type="button"
              onClick={() => onSeller("Mitra Aliqa")}
              className={`flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-black transition-all cursor-pointer ${
                normalizedSeller === "Mitra Aliqa"
                  ? "bg-emerald-600 text-white shadow-md shadow-emerald-700/20 scale-[1.02]"
                  : "text-slate-600 hover:text-slate-900 hover:bg-white/60"
              }`}
              title="Buka Dashboard Pengiriman Khusus Mitra Aliqa"
            >
              <Package className="h-3.5 w-3.5" />
              <span><span className="hidden sm:inline">Dashboard </span>Aliqa</span>
            </button>
            <button
              type="button"
              onClick={() => onSeller("Mitra Zaherba")}
              className={`flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-black transition-all cursor-pointer ${
                normalizedSeller === "Mitra Zaherba"
                  ? "bg-purple-700 text-white shadow-md shadow-purple-800/20 scale-[1.02]"
                  : "text-slate-600 hover:text-slate-900 hover:bg-white/60"
              }`}
              title="Buka Dashboard Pengiriman Khusus Mitra Zaherba"
            >
              <Package className="h-3.5 w-3.5" />
              <span><span className="hidden sm:inline">Dashboard </span>Zaherba</span>
            </button>
          </div>
        </div>

        <div className="flex items-center gap-1.5 sm:gap-2 flex-wrap sm:flex-nowrap justify-end shrink-0">
          {/* Grup 1: Sinkronisasi Sheets (Khusus Admin) */}
          {isAdmin && (
            <div className="flex items-center gap-1.5 rounded-xl bg-slate-50 p-1 border border-slate-200/80 shadow-2xs">
              {/* Sync Sheets: Menarik data sekaligus warna otomatis dalam 1 tombol */}
              <Button
                variant="outline"
                disabled={isSyncing}
                className="gap-1.5 border-emerald-600/70 bg-emerald-50 text-emerald-900 hover:bg-emerald-100 hover:text-emerald-950 cursor-pointer font-semibold text-xs h-9 shadow-xs rounded-lg transition"
                onClick={() => setSheetSyncOpen(true)}
                title={`Sync Data & Warna dari Google Sheets (${normalizedSeller})`}
              >
                {isSyncing ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin text-emerald-600" />
                ) : (
                  <RefreshCw className="h-3.5 w-3.5 text-emerald-600" />
                )}
                <span>Sync Sheets</span>
              </Button>

              {/* Settings: Konfigurasi URL per-seller */}
              <Button
                variant="outline"
                size="icon"
                className="h-9 w-9 border-slate-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 cursor-pointer shadow-xs rounded-lg shrink-0"
                onClick={() => setSheetSettingsOpen(true)}
                title="Pengaturan Link Google Spreadsheet per-Seller"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </Button>


            </div>
          )}

          {/* Grup 2: Quick Tools (Kontak KC Pos) */}
          <div className="flex items-center gap-1.5">
            <Button
              variant="outline"
              className="gap-1.5 text-xs h-9 font-semibold border-slate-200 bg-white hover:bg-slate-50 cursor-pointer shadow-xs text-slate-700 hover:text-slate-900 rounded-lg"
              onClick={onOpenPostOffices}
              title="Buka Database Kontak WhatsApp KC/KCU Pos Indonesia"
            >
              <Building2 className="h-3.5 w-3.5 text-blue-600" /> Kontak KC Pos
            </Button>
          </div>

          {/* Grup 3: Bot NIPOS Action Button */}
          <div className="flex items-center">
            <Button
              onClick={runBot}
              disabled={botState === "running"}
              className="gap-2 bg-[#1E40AF] text-white hover:bg-blue-900 cursor-pointer text-xs h-9 font-bold shadow-sm shadow-blue-900/20 rounded-lg transition-all"
              title={`Jalankan Bot NIPOS khusus bulan ${activeBotMonthName}`}
            >
              {botState === "running" ? (
                <Loader2 className="h-4 w-4 animate-spin text-[#F97316]" />
              ) : botState === "done" ? (
                <CheckCircle2 className="h-4 w-4 text-emerald-400" />
              ) : (
                <Bot className="h-4 w-4 text-[#F97316]" />
              )}
              <span>Run Bot NIPOS</span>
              <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold shadow-xs ${badge.cls}`}>
                {badge.text}
              </span>
            </Button>
          </div>

          {/* Indikator Device Online (🟢) */}
          <button
            type="button"
            onClick={() => {
              fetchDevices();
              setDevicesModalOpen(true);
            }}
            className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100/90 border border-emerald-200/90 text-emerald-800 text-xs font-bold transition shadow-2xs cursor-pointer select-none shrink-0"
            title="Klik untuk melihat siapa saja perangkat yang sedang aktif"
          >
            <span className="relative flex h-2 w-2">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span className="font-black tracking-tight">{activeDevices.count} Online</span>
          </button>

          {/* Grup 4: User Profile & Actions */}
          {currentUser && (
            <div className="flex items-center pl-2.5 border-l border-slate-200">
              {/* Avatar Bulat Inisial dengan Menu Dropdown Lengkap */}
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <button
                    className="flex items-center gap-2.5 p-1 rounded-xl hover:bg-slate-100/80 transition-all cursor-pointer outline-none group select-none"
                    title={`Menu Akun: ${currentUser.name}`}
                  >
                    <div className="text-right hidden sm:block">
                      <span className="block text-[11px] font-bold text-slate-800 leading-tight group-hover:text-blue-900 transition-colors">
                        {currentUser.name}
                      </span>
                      <span
                        className={`inline-block text-[9px] font-black uppercase px-1.5 py-0.2 rounded-md ${
                          isAdmin
                            ? "bg-purple-100 text-purple-800 border border-purple-200"
                            : "bg-emerald-100 text-emerald-800 border border-emerald-200"
                        }`}
                      >
                        {currentUser.role}
                      </span>
                    </div>

                    <div
                      className="h-8.5 w-8.5 rounded-full bg-gradient-to-tr from-[#6366f1] via-[#7c3aed] to-[#818cf8] text-white font-black text-xs flex items-center justify-center shadow-xs group-hover:scale-105 group-active:scale-95 transition-all select-none shrink-0 ring-2 ring-indigo-100"
                    >
                      {userInitials}
                    </div>
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56 p-2 rounded-xl border-slate-200 shadow-xl bg-white">
                  {/* Header Informasi Akun */}
                  <div className="flex items-center gap-2.5 p-2 rounded-lg bg-slate-50 border border-slate-100 mb-1">
                    <div className="h-8 w-8 rounded-full bg-gradient-to-tr from-[#6366f1] via-[#7c3aed] to-[#818cf8] text-white font-black text-xs flex items-center justify-center shrink-0 ring-1 ring-indigo-200">
                      {userInitials}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="text-xs font-bold text-slate-800 truncate leading-tight">
                        {currentUser.name}
                      </p>
                      <p className="text-[10px] font-mono text-slate-500 truncate leading-tight mt-0.5">
                        {currentUser.email}
                      </p>
                      <span
                        className={`inline-block text-[8px] font-black uppercase px-1.5 py-0.2 rounded mt-1 ${
                          isAdmin
                            ? "bg-purple-100 text-purple-800 border border-purple-200"
                            : "bg-emerald-100 text-emerald-800 border border-emerald-200"
                        }`}
                      >
                        {currentUser.role}
                      </span>
                    </div>
                  </div>

                  <DropdownMenuSeparator />

                  {/* Menu: Ubah Kata Sandi */}
                  <DropdownMenuItem
                    onClick={() => setUserProfileOpen(true)}
                    className="gap-2.5 text-xs font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-100 rounded-lg p-2 cursor-pointer transition"
                  >
                    <KeyRound className="h-3.5 w-3.5 text-slate-500" />
                    <span>Ubah Kata Sandi</span>
                  </DropdownMenuItem>

                  {/* Menu: Kelola Pengguna (Admin) */}
                  {isAdmin && (
                    <DropdownMenuItem
                      onClick={() => setUserManagerOpen(true)}
                      className="gap-2.5 text-xs font-semibold text-purple-700 hover:text-purple-900 hover:bg-purple-50 rounded-lg p-2 cursor-pointer transition"
                    >
                      <Users className="h-3.5 w-3.5 text-purple-600" />
                      <span>Manajemen Pengguna</span>
                    </DropdownMenuItem>
                  )}

                  {/* Menu: Pengaturan Cookie NIPOS (Admin) */}
                  {isAdmin && (
                    <DropdownMenuItem
                      onClick={() => setNiposModalOpen(true)}
                      className="gap-2.5 text-xs font-semibold text-amber-800 hover:text-amber-950 hover:bg-amber-50 rounded-lg p-2 cursor-pointer transition justify-between"
                    >
                      <div className="flex items-center gap-2.5">
                        <Cookie className="h-3.5 w-3.5 text-amber-600" />
                        <span>Cookie NIPOS</span>
                      </div>
                      <span className="flex items-center gap-1.5 pl-2">
                        <span
                          className={`h-2 w-2 rounded-full ${
                            niposConnected === true
                              ? "bg-emerald-500 ring-2 ring-emerald-200"
                              : niposConnected === false
                              ? "bg-rose-500 ring-2 ring-rose-200 animate-pulse"
                              : "bg-amber-400 ring-2 ring-amber-200"
                          }`}
                        />
                        <span className="text-[10px] font-medium text-slate-500">
                          {niposConnected === true
                            ? "Aktif"
                            : niposConnected === false
                            ? "Mati"
                            : "Cek..."}
                        </span>
                      </span>
                    </DropdownMenuItem>
                  )}

                  <DropdownMenuSeparator />

                  {/* Menu: Lambang Log Out */}
                  <DropdownMenuItem
                    onClick={() => {
                      router.post("/logout", {}, {
                        onFinish: () => {
                          window.location.href = "/login";
                        },
                      });
                    }}
                    className="gap-2.5 text-xs font-bold text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-lg p-2 cursor-pointer transition"
                  >
                    <LogOut className="h-3.5 w-3.5 text-rose-600" />
                    <span>Keluar / Logout</span>
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          )}
        </div>
      </div>

      {/* Peringatan Kritis Cookie NIPOS Mati / Expired */}
      {niposConnected === false && isAdmin && (
        <div className="bg-rose-600 text-white px-5 py-2 text-xs flex items-center justify-between font-semibold shadow-xs">
          <div className="flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 text-amber-300 shrink-0" />
            <span>
              <strong>Peringatan Sistem:</strong> Sesi Cookie NIPOS tidak aktif atau kedaluwarsa! Bot pelacakan otomatis tidak dapat melacak status kiriman.
            </span>
          </div>
          <button
            type="button"
            onClick={() => setNiposModalOpen(true)}
            className="underline hover:text-amber-200 font-bold ml-4 cursor-pointer shrink-0"
          >
            Perbarui Cookie Sekarang &rarr;
          </button>
        </div>
      )}

      <AnimatePresence>
        {sheetSyncState !== "idle" && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="overflow-hidden border-t border-emerald-300 bg-emerald-50 px-5 py-3 shadow-inner"
          >
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2.5">
                <span className="relative flex h-3 w-3">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex h-3 w-3 rounded-full bg-emerald-600"></span>
                </span>
                <Loader2 className="h-4 w-4 animate-spin text-emerald-700" />
                <div className="text-xs font-bold text-emerald-950">
                  <span>{sheetSyncInfo.message || "Menyinkronkan data Google Sheets..."}</span>
                  {sheetSyncInfo.total_sheets > 0 && (
                    <span className="ml-2 font-mono text-[11px] text-emerald-700">
                      [{sheetSyncInfo.current_sheet_index}/{sheetSyncInfo.total_sheets} Sheet]
                    </span>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-3 w-full sm:w-auto flex-1 max-w-md">
                <Progress value={sheetSyncProgress} className="h-2 flex-1 bg-emerald-200" />
                <div className="font-mono text-xs font-extrabold text-emerald-800 shrink-0">
                  {sheetSyncProgress}% {sheetSyncInfo.processed_rows > 0 && `(${nf(sheetSyncInfo.processed_rows)} baris)`}
                </div>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <AnimatePresence>
        {syncData.is_syncing && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="overflow-hidden border-t border-emerald-300 bg-emerald-50 px-5 py-3 shadow-inner"
          >
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2.5">
                <span className="relative flex h-3 w-3">
                  <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex h-3 w-3 rounded-full bg-emerald-600"></span>
                </span>
                <Loader2 className="h-4 w-4 animate-spin text-emerald-700" />
                <div className="text-xs font-bold text-emerald-950">
                  <span>{syncData.message || "Menyinkronkan data Google Sheets di background..."}</span>
                  {syncData.total_sheets > 0 && (
                    <span className="ml-2 font-mono text-[11px] text-emerald-700">
                      [{syncData.current_sheet_index}/{syncData.total_sheets} Sheet]
                    </span>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-3 w-full sm:w-auto flex-1 max-w-md">
                <Progress value={syncData.percentage} className="h-2 flex-1 bg-emerald-200" />
                <div className="font-mono text-xs font-extrabold text-emerald-800 shrink-0">
                  {syncData.percentage}% {syncData.processed_rows > 0 && `(${nf(syncData.processed_rows)} baris)`}
                </div>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <AnimatePresence>
        {botState !== "idle" && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: "auto", opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="overflow-hidden border-t border-blue-200 bg-blue-50/90 px-5 py-2.5 shadow-inner"
          >
            <div className="flex flex-wrap items-center justify-between gap-3">
              {/* Status Info Kiri */}
              <div className="flex items-center gap-2.5">
                <span className="relative flex h-3 w-3">
                  <span className={`absolute inline-flex h-full w-full rounded-full opacity-75 ${
                    botState === "running" ? "animate-ping bg-blue-400" : "bg-emerald-400"
                  }`} />
                  <span className={`relative inline-flex h-3 w-3 rounded-full ${
                    botState === "running" ? "bg-[#1E40AF]" : "bg-emerald-600"
                  }`} />
                </span>
                {botState === "running" ? (
                  <Loader2 className="h-4 w-4 animate-spin text-[#1E40AF]" />
                ) : (
                  <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                )}
                <div className="text-xs font-bold text-slate-800">
                  <span>
                    {botState === "running"
                      ? `Bot NIPOS sedang melacak resi (${activeBotMonthName})...`
                      : `Pelacakan selesai! Semua resi (${activeBotMonthName}) terverifikasi.`}
                  </span>
                  {botUpdatedItems.length > 0 && (
                    <span className="ml-2 rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-extrabold text-emerald-800 border border-emerald-200">
                      +{botUpdatedItems.length} Resi Berubah Status
                    </span>
                  )}
                </div>
              </div>

              {/* Progress Bar & Counter Kanan */}
              <div className="flex items-center gap-3 w-full sm:w-auto flex-1 max-w-md">
                <Progress
                  value={progress}
                  className={`h-2.5 flex-1 rounded-full ${botState === "done" ? "bg-emerald-200" : "bg-blue-200"}`}
                />
                <div className="font-mono text-xs font-extrabold text-[#1E40AF] shrink-0">
                  {progress.toFixed(0)}%{" "}
                  <span className="text-slate-500 font-semibold">
                    ({botInfo ? `${nf(botInfo.current)} / ${nf(botInfo.total)}` : `${nf(Math.round((progress / 100) * total))} / ${nf(total)}`} Resi)
                  </span>
                </div>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* ── Dialog: Sync Sheets (Konfirmasi, URL otomatis dari Settings) ── */}
      <Dialog open={sheetSyncOpen} onOpenChange={setSheetSyncOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-emerald-700">
              <RefreshCw className="h-5 w-5" /> Sinkronisasi Google Sheets
            </DialogTitle>
            <DialogDescription className="text-xs text-slate-500 mt-1">
              Data akan diimport dari spreadsheet <span className="font-bold text-slate-700">{normalizedSeller}</span>.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            {/* Tampilkan URL aktif */}
            <div className="rounded-xl bg-emerald-50 border border-emerald-200 p-3 space-y-1">
              <p className="text-[10px] font-bold text-emerald-700 uppercase tracking-wide">Spreadsheet Aktif</p>
              <p className="text-[11px] text-emerald-900 font-mono break-all leading-snug">
                {sheetUrlInput
                  ? sheetUrlInput.replace(/\/edit.*$/, "").replace("https://docs.google.com/spreadsheets/d/", "ID: ")
                  : <span className="text-red-500 font-semibold">URL belum dikonfigurasi!</span>}
              </p>
            </div>
            {/* Multi-Month Selector */}
            <div className="rounded-xl bg-slate-50 border border-slate-200 p-3 space-y-2.5">
              <div className="flex items-center justify-between">
                <label className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
                  Pilih Bulan Yang Sedang Berjalan
                </label>
                <button
                  type="button"
                  onClick={() => { setSheetSyncOpen(false); setSheetSettingsOpen(true); }}
                  className="text-[10px] text-emerald-700 font-bold hover:underline flex items-center gap-1 cursor-pointer"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                  Ubah URL
                </button>
              </div>

              {/* Tombol Pintas / Quick Presets */}
              <div className="flex flex-wrap items-center gap-1.5 pb-0.5">
                <button
                  type="button"
                  onClick={handlePresetCurrentMonth}
                  className={`text-[11px] px-2.5 py-1 rounded-lg border font-semibold transition-all cursor-pointer ${
                    !isSyncAllMonths && selectedSyncMonths.length === 1 && selectedSyncMonths[0] === activeBotMonth
                      ? "bg-emerald-600 text-white border-emerald-600 shadow-2xs"
                      : "bg-white text-slate-700 hover:bg-slate-100 border-slate-200"
                  }`}
                >
                  ⚡ Bulan Berjalan
                </button>
                <button
                  type="button"
                  onClick={handlePresetLast3Months}
                  className={`text-[11px] px-2.5 py-1 rounded-lg border font-semibold transition-all cursor-pointer ${
                    !isSyncAllMonths && selectedSyncMonths.length === 3
                      ? "bg-emerald-600 text-white border-emerald-600 shadow-2xs"
                      : "bg-white text-slate-700 hover:bg-slate-100 border-slate-200"
                  }`}
                >
                  ⚡ 3 Bulan Terakhir
                </button>
                <button
                  type="button"
                  onClick={handlePresetLast4Months}
                  className={`text-[11px] px-2.5 py-1 rounded-lg border font-semibold transition-all cursor-pointer ${
                    !isSyncAllMonths && selectedSyncMonths.length === 4
                      ? "bg-emerald-600 text-white border-emerald-600 shadow-2xs"
                      : "bg-white text-slate-700 hover:bg-slate-100 border-slate-200"
                  }`}
                >
                  ⚡ 4 Bulan Terakhir
                </button>
                <button
                  type="button"
                  onClick={handlePresetAllMonths}
                  className={`text-[11px] px-2.5 py-1 rounded-lg border font-semibold transition-all cursor-pointer ${
                    isSyncAllMonths
                      ? "bg-emerald-700 text-white border-emerald-700 shadow-2xs"
                      : "bg-white text-slate-700 hover:bg-slate-100 border-slate-200"
                  }`}
                >
                  ⚡ Semua Bulan (ALL)
                </button>
              </div>

              {/* Grid 12 Bulan (Multi-Select Chips) */}
              <div className="grid grid-cols-3 sm:grid-cols-4 gap-1.5 pt-1">
                {monthNames.map((mName, idx) => {
                  const mNum = idx + 1;
                  const isSelected = isSyncAllMonths || selectedSyncMonths.includes(mNum);
                  const isCurrent = mNum === activeBotMonth;
                  return (
                    <button
                      key={mNum}
                      type="button"
                      onClick={() => toggleMonthSelection(mNum)}
                      className={`text-xs py-1.5 px-2 rounded-lg border font-medium flex items-center justify-between transition-all cursor-pointer ${
                        isSelected
                          ? "bg-emerald-600 text-white border-emerald-600 font-semibold shadow-2xs"
                          : "bg-white text-slate-600 hover:bg-slate-100 border-slate-200"
                      }`}
                    >
                      <span className="truncate">{mName}</span>
                      {isSelected ? (
                        <Check className="h-3 w-3 shrink-0 ml-1 text-white" />
                      ) : isCurrent ? (
                        <span className="text-[9px] px-1 bg-amber-100 text-amber-800 rounded font-bold shrink-0">Aktif</span>
                      ) : null}
                    </button>
                  );
                })}
              </div>

              {/* Ringkasan Pilihan */}
              <div className="flex items-center justify-between text-[11px] text-slate-500 pt-1.5 border-t border-slate-200/60">
                <span>
                  Terpilih: <strong className="text-emerald-700">
                    {isSyncAllMonths
                      ? "Semua Bulan (12 Tab)"
                      : `${selectedSyncMonths.length} Bulan (${selectedSyncMonths.map((m) => monthNames[m - 1]).join(", ")})`}
                  </strong>
                </span>
                <span className="text-[10px] text-slate-400">Klik tab untuk tambah / lepas</span>
              </div>
            </div>

            {/* Opsi Tarik Warna Status FU */}
            <div className="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 space-y-2">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={includeFuColors}
                  onChange={(e) => setIncludeFuColors(e.target.checked)}
                  className="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500 h-3.5 w-3.5 cursor-pointer"
                />
                <span className="text-xs font-bold text-emerald-950">
                  Tarik Tanda Warna Status FU (Google Apps Script)
                </span>
              </label>
              <p className="text-[11px] text-emerald-800/80 leading-snug">
                Data baru &amp; nomor resi disinkronkan secara aman dengan upsert tanpa menghapus riwayat tracking di database.
              </p>
            </div>
            {!sheetUrlInput && (
              <p className="text-[11px] text-red-600 font-semibold text-center">
                ⚠ URL Spreadsheet belum dikonfigurasi. Klik "Ubah URL" untuk mengatur.
              </p>
            )}
          </div>
          <div className="flex justify-end gap-2 pt-1">
            <Button type="button" variant="outline" size="sm" onClick={() => setSheetSyncOpen(false)}>
              Batal
            </Button>
            <Button
              type="button"
              onClick={() => { setSheetSyncOpen(false); handleSyncSubmit({ preventDefault: () => {} } as any); }}
              disabled={isSyncing || !sheetUrlInput}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold gap-2 cursor-pointer text-xs h-9"
            >
              {isSyncing && <Loader2 className="h-4 w-4 animate-spin" />}
              Mulai Sinkronisasi
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* ── Dialog: Settings Google Sheets per-Seller ── */}
      <Dialog open={sheetSettingsOpen} onOpenChange={setSheetSettingsOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-slate-800">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-blue-600"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
              Pengaturan Google Sheets
            </DialogTitle>
            <DialogDescription className="text-xs text-slate-500">
              Atur link Google Spreadsheet untuk masing-masing seller agar data pengiriman dapat terhubung dan disinkronkan secara otomatis ke sistem.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={handleSaveSettings} className="space-y-4">
            {/* Mitra Aliqa */}
            <div className="rounded-xl border border-blue-200 bg-blue-50/50 p-4 space-y-2.5">
              <div className="flex items-center gap-2 mb-1">
                <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-600 px-2.5 py-0.5 text-[10px] font-extrabold text-white uppercase tracking-wide">
                  📦 Mitra Aliqa
                </span>
              </div>
              <div className="space-y-1">
                <label className="text-[11px] font-bold text-blue-900">
                  1. Link / URL Google Spreadsheet Aliqa
                </label>
                <Input
                  value={settingsAliqaUrl}
                  onChange={(e) => setSettingsAliqaUrl(e.target.value)}
                  placeholder="https://docs.google.com/spreadsheets/d/1EeckOBzI5.../edit"
                  className="text-xs font-mono bg-white border-blue-200 focus:ring-blue-400"
                />
              </div>
              <div className="space-y-1 pt-1">
                <div className="flex items-center justify-between">
                  <label className="text-[11px] font-bold text-blue-900 flex items-center gap-1.5">
                    <Send className="h-3 w-3 text-blue-600" />
                    2. Webhook Apps Script URL Aliqa (Two-Way Sync)
                  </label>
                  <span className="text-[9px] font-semibold text-blue-500 border border-blue-200 rounded px-1.5 py-0.2 bg-white">Opsional</span>
                </div>
                <Input
                  value={settingsAliqaWebhook}
                  onChange={(e) => setSettingsAliqaWebhook(e.target.value)}
                  placeholder="https://script.google.com/macros/s/AKfycbx_aliqa.../exec"
                  className="text-xs font-mono bg-white border-blue-200 focus:ring-blue-400"
                />
                <p className="text-[10px] text-blue-600">
                  Untuk otomatis push warna (Hijau Toska, Merah, dsb) ke spreadsheet Aliqa.
                </p>
              </div>
            </div>

            {/* Mitra Zaherba */}
            <div className="rounded-xl border border-purple-200 bg-purple-50/50 p-4 space-y-2.5">
              <div className="flex items-center gap-2 mb-1">
                <span className="inline-flex items-center gap-1.5 rounded-full bg-purple-600 px-2.5 py-0.5 text-[10px] font-extrabold text-white uppercase tracking-wide">
                  📦 Mitra Zaherba
                </span>
              </div>
              <div className="space-y-1">
                <label className="text-[11px] font-bold text-purple-900">
                  1. Link / URL Google Spreadsheet Zaherba
                </label>
                <Input
                  value={settingsZaherbaUrl}
                  onChange={(e) => setSettingsZaherbaUrl(e.target.value)}
                  placeholder="https://docs.google.com/spreadsheets/d/1wUqPnU1_QOq6.../edit"
                  className="text-xs font-mono bg-white border-purple-200 focus:ring-purple-400"
                />
              </div>
              <div className="space-y-1 pt-1">
                <div className="flex items-center justify-between">
                  <label className="text-[11px] font-bold text-purple-900 flex items-center gap-1.5">
                    <Send className="h-3 w-3 text-purple-600" />
                    2. Webhook Apps Script URL Zaherba (Two-Way Sync)
                  </label>
                  <span className="text-[9px] font-semibold text-purple-500 border border-purple-200 rounded px-1.5 py-0.2 bg-white">Opsional</span>
                </div>
                <Input
                  value={settingsZaherbaWebhook}
                  onChange={(e) => setSettingsZaherbaWebhook(e.target.value)}
                  placeholder="https://script.google.com/macros/s/AKfycbx_zaherba.../exec"
                  className="text-xs font-mono bg-white border-purple-200 focus:ring-purple-400"
                />
                <p className="text-[10px] text-purple-600">
                  Untuk otomatis push warna (Biru Toska #46BDC6, Orange #FF9900, dsb) ke spreadsheet Zaherba.
                </p>
              </div>
            </div>

            {/* Info Box: Otomatis hapus data lama saat link berganti */}
            <div className="rounded-xl border border-amber-200 bg-amber-50/80 p-3 text-[11px] text-amber-800 flex items-start gap-2">
              <span className="text-base leading-none">💡</span>
              <p className="leading-snug">
                <strong>Ketentuan Ganti Link:</strong> Jika Anda mengganti Link Google Spreadsheet suatu seller dengan link baru, sistem secara otomatis akan menghapus seluruh data resi lama seller tersebut dari database agar tidak tercampur.
              </p>
            </div>

            <div className="flex justify-end gap-2 pt-1">
              <Button type="button" variant="outline" size="sm" onClick={() => setSheetSettingsOpen(false)}>
                Batal
              </Button>
              <Button
                type="submit"
                disabled={isSavingSettings}
                className="bg-blue-600 hover:bg-blue-700 text-white font-bold gap-2 cursor-pointer text-xs h-9"
              >
                {isSavingSettings && <Loader2 className="h-4 w-4 animate-spin" />}
                Simpan Pengaturan
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>


      <Dialog open={botResultModalOpen} onOpenChange={setBotResultModalOpen}>
        <DialogContent className="max-w-3xl max-h-[85vh] flex flex-col bg-white border border-slate-200 shadow-2xl rounded-2xl p-0 overflow-hidden">
          <DialogHeader className="p-5 pb-3 border-b border-slate-100 bg-slate-50/80">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-lg bg-blue-600/10 flex items-center justify-center text-blue-600">
                  <Bot className="h-5 w-5" />
                </div>
                <div>
                  <DialogTitle className="text-base font-bold text-slate-900">
                    Pemberitahuan Hasil Update Bot NIPOS ({activeBotMonthName})
                  </DialogTitle>
                  <DialogDescription className="text-xs text-slate-500">
                    Berikut adalah data resi yang baru saja di-tracking &amp; diperbarui statusnya dari web Pos Indonesia.
                  </DialogDescription>
                </div>
              </div>
            </div>

            {/* Statistik Ringkasan */}
            <div className="grid grid-cols-4 gap-2 pt-3">
              <div className="rounded-xl border border-slate-200 bg-white p-2.5 text-center shadow-xs">
                <div className="text-[11px] font-medium text-slate-500">Total Diperbarui</div>
                <div className="text-lg font-bold text-slate-900">{botUpdatedItems.length}</div>
              </div>
              <div className="rounded-xl border border-emerald-200 bg-emerald-50/60 p-2.5 text-center shadow-xs">
                <div className="text-[11px] font-medium text-emerald-700">Sukses (Delivered)</div>
                <div className="text-lg font-bold text-emerald-700">
                  {botUpdatedItems.filter((i) => i.status_kategori === "SUKSES" || i.color_code === "BIRU").length}
                </div>
              </div>
              <div className="rounded-xl border border-amber-200 bg-amber-50/60 p-2.5 text-center shadow-xs">
                <div className="text-[11px] font-medium text-amber-800">Retur (Return)</div>
                <div className="text-lg font-bold text-amber-800">
                  {botUpdatedItems.filter((i) => i.status_kategori === "RETUR" || i.color_code === "ORANGE").length}
                </div>
              </div>
              <div className="rounded-xl border border-blue-200 bg-blue-50/60 p-2.5 text-center shadow-xs">
                <div className="text-[11px] font-medium text-blue-700">In Process / FU</div>
                <div className="text-lg font-bold text-blue-700">
                  {botUpdatedItems.filter((i) => !["SUKSES", "RETUR"].includes(i.status_kategori) && !["BIRU", "ORANGE"].includes(i.color_code)).length}
                </div>
              </div>
            </div>

            {/* Search filter di dalam modal */}
            <div className="relative pt-2">
              <Search className="absolute left-2.5 top-5 h-3.5 w-3.5 text-slate-400" />
              <Input
                placeholder="Cari nomor resi atau status di daftar ini..."
                value={botFilterQuery}
                onChange={(e) => setBotFilterQuery(e.target.value)}
                className="pl-8 h-8 text-xs bg-white"
              />
            </div>
          </DialogHeader>

          {/* Daftar Resi yang Terupdate */}
          <div className="flex-1 overflow-y-auto p-5 pt-3 space-y-2">
            {(() => {
              const filtered = botUpdatedItems.filter((item) => {
                if (!botFilterQuery.trim()) return true;
                const q = botFilterQuery.toLowerCase();
                return (
                  item.resi.toLowerCase().includes(q) ||
                  (item.status_pos && item.status_pos.toLowerCase().includes(q)) ||
                  (item.keterangan && item.keterangan.toLowerCase().includes(q)) ||
                  (item.status_kategori && item.status_kategori.toLowerCase().includes(q))
                );
              });

              if (filtered.length === 0) {
                return (
                  <div className="text-center py-8 text-xs text-slate-500">
                    Tidak ada data resi yang cocok dengan pencarian.
                  </div>
                );
              }

              return filtered.map((item, idx) => {
                const isSukses = item.status_kategori === "SUKSES" || item.color_code === "BIRU";
                const isRetur = item.status_kategori === "RETUR" || item.color_code === "ORANGE";

                return (
                  <div
                    key={item.id || item.resi || idx}
                    className={`flex items-start justify-between gap-3 p-3 rounded-xl border text-xs transition ${
                      isSukses
                        ? "bg-emerald-50/40 border-emerald-200"
                        : isRetur
                        ? "bg-amber-50/40 border-amber-200"
                        : "bg-slate-50 border-slate-200"
                    }`}
                  >
                    <div className="flex-1 min-w-0 space-y-1">
                      <div className="flex items-center gap-2">
                        <span className="font-mono font-bold text-slate-900 text-sm">
                          {item.resi}
                        </span>
                        <span
                          className={`rounded px-1.5 py-0.5 text-[10px] font-bold uppercase ${
                            isSukses
                              ? "bg-emerald-600 text-white"
                              : isRetur
                              ? "bg-amber-600 text-white"
                              : "bg-blue-600 text-white"
                          }`}
                        >
                          {item.status_kategori || "IN PROCESS"}
                        </span>
                        {item.sla !== undefined && (
                          <span
                            className={`rounded border px-1.5 py-0.5 text-[10px] font-bold ${
                              item.sla < 0
                                ? "bg-red-100 border-red-300 text-red-700"
                                : "bg-slate-200/90 border-slate-300 text-slate-800"
                            }`}
                          >
                            {item.sla < 0
                              ? `Over SLA ${Math.abs(item.sla)} Hari`
                              : `SLA: ${item.sla} Hari`}
                          </span>
                        )}
                        <span className="text-[10px] text-slate-400">
                          {item.seller}
                        </span>
                      </div>
                      <div className="text-slate-700 font-medium break-words leading-relaxed text-[11px]">
                        <span className="font-bold text-slate-900">Status NIPOS:</span> {item.status_pos || "-"}
                      </div>
                      {item.keterangan && item.keterangan !== item.status_pos && (
                        <div className="text-slate-500 italic text-[11px] break-words">
                          {item.keterangan}
                        </div>
                      )}
                    </div>

                    <div className="shrink-0 text-right space-y-1">
                      <div className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700 bg-emerald-100/80 px-2 py-0.5 rounded-full border border-emerald-300">
                        <CheckCircle2 className="h-3 w-3" /> Valid Diperbarui
                      </div>
                      <div className="text-[10px] text-slate-400">
                        {item.last_tracked_at || "Baru saja"}
                      </div>
                    </div>
                  </div>
                );
              });
            })()}
          </div>

          <div className="p-3 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
            <span className="text-xs text-slate-500">
              Menampilkan {botUpdatedItems.length} data yang telah diverifikasi bot.
            </span>
            <Button
              onClick={() => {
                setBotResultModalOpen(false);
                router.reload({ preserveScroll: true });
              }}
              className="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold"
            >
              Tutup &amp; Lihat Dashboard
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* NIPOS Cookie & Connection Settings Modal */}
      <NiposCookieModal
        isOpen={niposModalOpen}
        onClose={() => setNiposModalOpen(false)}
        onStatusChange={setNiposConnected}
      />

      {/* Manajemen Pengguna Modal (Admin) */}
      <UserManagerModal
        isOpen={userManagerOpen}
        onClose={() => setUserManagerOpen(false)}
        currentUserId={currentUser?.id}
      />

      {/* Ubah Password Mandiri Modal */}
      <UserProfileModal
        isOpen={userProfileOpen}
        onClose={() => setUserProfileOpen(false)}
        currentUser={currentUser}
      />

      {/* Modal Daftar Perangkat Online (🟢) */}
      <Dialog open={devicesModalOpen} onOpenChange={setDevicesModalOpen}>
        <DialogContent className="sm:max-w-md rounded-2xl p-5 bg-white shadow-2xl border-slate-200">
          <DialogHeader className="pb-3 border-b border-slate-100">
            <div className="flex items-center gap-2.5">
              <div className="h-9 w-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                <Radio className="h-4.5 w-4.5 animate-pulse" />
              </div>
              <div>
                <DialogTitle className="text-base font-black text-slate-800">
                  Perangkat Sedang Aktif
                </DialogTitle>
                <DialogDescription className="text-xs text-slate-500">
                  Ada <span className="font-bold text-emerald-700">{activeDevices.count} perangkat</span> yang sedang membuka Web Tracko
                </DialogDescription>
              </div>
            </div>
          </DialogHeader>

          <div className="space-y-2 py-3 max-h-[350px] overflow-y-auto">
            {activeDevices.devices.length === 0 ? (
              <div className="text-center py-6 text-xs text-slate-400">
                Memuat daftar perangkat aktif...
              </div>
            ) : (
              activeDevices.devices.map((dev) => {
                const isMobile = dev.device_type.toLowerCase().includes("hp") || dev.device_type.toLowerCase().includes("phone") || dev.device_type.toLowerCase().includes("android");
                return (
                  <div
                    key={dev.id}
                    className={`flex items-center justify-between p-3 rounded-xl border transition ${
                      dev.is_current
                        ? "bg-emerald-50/70 border-emerald-300 ring-1 ring-emerald-200"
                        : "bg-slate-50 border-slate-200/80 hover:bg-slate-100/60"
                    }`}
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <div className={`h-9 w-9 rounded-xl flex items-center justify-center shrink-0 ${
                        dev.is_current ? "bg-emerald-600 text-white" : "bg-slate-200 text-slate-700"
                      }`}>
                        {isMobile ? <Smartphone className="h-4.5 w-4.5" /> : <Laptop className="h-4.5 w-4.5" />}
                      </div>
                      <div className="min-w-0">
                        <div className="flex items-center gap-1.5 flex-wrap">
                          <span className="text-xs font-black text-slate-800">
                            {dev.device_type}
                          </span>
                          {dev.is_current && (
                            <span className="text-[9px] font-black uppercase px-1.5 py-0.2 rounded bg-emerald-200 text-emerald-900 border border-emerald-300">
                              Perangkat Ini
                            </span>
                          )}
                        </div>
                        <p className="text-[11px] text-slate-500 flex items-center gap-1 mt-0.5 truncate">
                          <span>{dev.browser}</span>
                          <span>•</span>
                          <span className="font-mono text-[10px] text-slate-600">{dev.ip}</span>
                          <span>•</span>
                          <span className="font-semibold text-slate-700">{dev.user_name}</span>
                        </p>
                      </div>
                    </div>

                    <div className="text-right shrink-0 pl-2">
                      <span className="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-100/90 px-2 py-0.5 rounded-full border border-emerald-200">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        {dev.last_seen_human}
                      </span>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          <div className="flex items-center justify-between pt-3 border-t border-slate-100">
            <span className="text-[11px] text-slate-400">
              Otomatis diperbarui secara realtime
            </span>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setDevicesModalOpen(false)}
              className="text-xs font-bold rounded-xl h-8 cursor-pointer"
            >
              Tutup
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
