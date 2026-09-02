export type FuStatus = "PUTIH" | "BIRU" | "ORANGE" | "KUNING" | "HIJAU" | "BIRU_TUA";

export type NiposStatus =
  | "DELIVERED"
  | "RETURN"
  | "IN LOCATION"
  | "RUNSHEET"
  | "OUT FOR DELIVERY";

export interface Shipment {
  id: string;
  resi: string;
  seller: string;
  tanggalKirim: string; // ISO
  tujuan: string;
  penerima: string;
  telepon: string;
  alamat: string;
  keterangan: string;
  nipos: NiposStatus;
  sla: number;
  fu: FuStatus;
  note?: string;
  escalationDate?: string;
  kantorTujuan?: string;
  kantorPosPhone?: string;
  kantorPosPic?: string;
  lastLocation?: string;
}

export interface PostOffice {
  id: number | string;
  code?: string;
  name: string;
  city?: string;
  province?: string;
  phone_wa: string;
  pic_name?: string;
  notes?: string;
}

export function generatePostOfficeWaMessage(
  shipment: Shipment,
  postOfficeName?: string,
  customNote?: string
): string {
  const office = postOfficeName || shipment.kantorTujuan || "Kantor Pos Tujuan";
  const lines = [
    `Halo Rekan CS/Antaran Pos Indonesia ${office},`,
    ``,
    `Mohon bantuannya untuk pengecekan / follow-up kiriman berikut:`,
    `📦 *No. Resi:* ${shipment.resi}`,
    `👤 *Penerima:* ${shipment.penerima || "-"} (${shipment.telepon || "-"})`,
    `📍 *Alamat:* ${shipment.alamat || shipment.tujuan || "-"}`,
    `🏪 *Seller / Mitra:* ${shipment.seller || "Pos Indonesia"}`,
    `📊 *Status NIPOS:* ${shipment.nipos || "ON PROCESS"}`,
    `📝 *Keterangan:* ${shipment.keterangan || "-"}`,
  ];

  if (customNote && customNote.trim()) {
    lines.push(``, `⚠️ *Catatan Tambahan:* ${customNote.trim()}`);
  } else {
    lines.push(``, `Mohon bantuannya agar dapat segera diantar / diklarifikasi ke penerima agar paket sukses terkirim dan tidak terjadi komplain ya kak. Terima kasih banyak atas kerjasamanya! 🙏✨`);
  }

  return lines.join("\n");
}


export const SELLERS = [
  "Mitra Aliqa",
  "Mitra Zaherba",
  "Mitra Herbal",
  "Mitra Nusantara",
  "Mitra Barokah",
];

export const MONTHS = [
  "Jan",
  "Feb",
  "Mar",
  "Apr",
  "Mei",
  "Jun",
  "Jul",
  "Agu",
  "Sep",
  "Okt",
  "Nov",
  "Des",
];

export const FU_META: Record<
  FuStatus,
  { label: string; bg: string; fg: string; short: string }
> = {
  PUTIH: { label: "BLM DI FU", bg: "#FFFFFF", fg: "#1E293B", short: "PUTIH" },
  BIRU: { label: "PAKET SUKSES", bg: "#46BDC6", fg: "#083344", short: "BIRU" },
  ORANGE: { label: "PAKET RETUR", bg: "#FBBC04", fg: "#451A03", short: "ORANGE" },
  KUNING: { label: "SUDAH DI FU", bg: "#FFFF00", fg: "#422006", short: "KUNING" },
  HIJAU: { label: "FU 2 KALI", bg: "#93C47D", fg: "#14532D", short: "HIJAU" },
  BIRU_TUA: { label: "FU POS", bg: "#1C4587", fg: "#FFFFFF", short: "BIRU TUA" },
};

export const FU_ORDER: FuStatus[] = ["BIRU", "ORANGE", "KUNING", "PUTIH", "HIJAU", "BIRU_TUA"];

const CITIES = [
  "Jakarta Selatan",
  "Bandung",
  "Surabaya",
  "Semarang",
  "Yogyakarta",
  "Medan",
  "Makassar",
  "Denpasar",
  "Bekasi",
  "Purwokerto",
  "Tangerang",
  "Palembang",
];

const NAMES = [
  "Siti Rohmah",
  "Budi Santoso",
  "Ahmad Fauzi",
  "Dewi Lestari",
  "Rina Marlina",
  "Agus Prasetyo",
  "Nur Aini",
  "Joko Susilo",
  "Indah Permata",
  "Rizky Ramadhan",
];

const KETERANGAN = [
  "Penerima tidak di tempat",
  "Alamat kurang lengkap",
  "Sudah diterima keluarga",
  "Dalam pengantaran kurir",
  "Nomor telepon tidak aktif",
  "Tiba di kantor tujuan",
  "Reschedule pengantaran besok",
];

function lcg(seed: number) {
  let s = seed >>> 0;
  return () => ((s = (s * 1664525 + 1013904223) >>> 0) / 4294967296);
}

function pick<T>(arr: T[], r: number): T {
  return arr[Math.floor(r * arr.length)] as T;
}

function pad(n: number, len = 2) {
  return String(n).padStart(len, "0");
}

export function generateShipments(count = 4800): Shipment[] {
  const rnd = lcg(20260821);
  const out: Shipment[] = [];
  for (let i = 0; i < count; i++) {
    const month = Math.floor(rnd() * 12);
    const day = 1 + Math.floor(rnd() * 28);
    const r = rnd();
    const nipos: NiposStatus =
      r < 0.62
        ? "DELIVERED"
        : r < 0.74
          ? "RETURN"
          : r < 0.84
            ? "OUT FOR DELIVERY"
            : r < 0.93
              ? "RUNSHEET"
              : "IN LOCATION";
    const fr = rnd();
    const fu: FuStatus =
      nipos === "DELIVERED"
        ? "BIRU"
        : nipos === "RETURN"
          ? "ORANGE"
          : fr < 0.55
            ? "PUTIH"
            : fr < 0.78
              ? "KUNING"
              : fr < 0.93
                ? "HIJAU"
                : "BIRU_TUA";
    out.push({
      id: `s${i}`,
      resi: `PCP${pad(month + 1)}${pad(day)}${pad(1000000 + Math.floor(rnd() * 8999999), 7)}ID`,
      seller: pick(SELLERS, rnd()),
      tanggalKirim: `2026-${pad(month + 1)}-${pad(day)}`,
      tujuan: pick(CITIES, rnd()),
      penerima: pick(NAMES, rnd()),
      telepon: `08${Math.floor(1000000000 + rnd() * 8999999999)}`.slice(0, 13),
      alamat: `Jl. Merdeka No.${1 + Math.floor(rnd() * 200)}, RT0${1 + Math.floor(rnd() * 8)}`,
      keterangan: pick(KETERANGAN, rnd()),
      nipos,
      sla: 1 + Math.floor(rnd() * 9),
      fu,
    });
  }
  return out;
}

export function formatDate(iso?: string | null) {
  if (!iso || typeof iso !== "string") return "-";
  const parts = iso.split("-");
  if (parts.length !== 3) return iso;
  const [y, m, d] = parts;
  return `${d}/${m}/${y}`;
}

export function nf(n?: number | null) {
  if (n === null || n === undefined || isNaN(Number(n))) return "0";
  return Number(n).toLocaleString("id-ID");
}
