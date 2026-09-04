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

export function extractCityRegency(address?: string, officeName?: string): string {
  if (!address && !officeName) return "-";
  const text = (address || "").trim();

  // 1. Try matching "Kota [Name]" or "Kotamadya [Name]"
  const kotaMatch = text.match(/\b(?:Kota|Kotamadya)\s+([A-Za-z\s]+?)(?=[,\.\n\r]|\s+(?:Kec|Desa|Kel|Rt|Rw|Prov|Jawa|Sumatera|Kalimantan|Sulawesi|Bali|Papua|\d{5})|$)/i);
  if (kotaMatch && kotaMatch[1].trim()) {
    const clean = kotaMatch[1].trim().replace(/\s+/g, " ");
    const words = clean.split(" ").slice(0, 3).join(" ");
    return `Kota ${words}`;
  }

  // 2. Try matching "Kab. [Name]" or "Kabupaten [Name]" or "Kab [Name]"
  const kabMatch = text.match(/\b(?:Kabupaten|Kab\.?)\s+([A-Za-z\s]+?)(?=[,\.\n\r]|\s+(?:Kec|Desa|Kel|Rt|Rw|Prov|Jawa|Sumatera|Kalimantan|Sulawesi|Bali|Papua|\d{5})|$)/i);
  if (kabMatch && kabMatch[1].trim()) {
    const clean = kabMatch[1].trim().replace(/\s+/g, " ");
    const words = clean.split(" ").slice(0, 3).join(" ");
    return `Kab. ${words}`;
  }

  // 3. Try matching "Kec. [Name]" if no Kab/Kota
  const kecMatch = text.match(/\b(?:Kecamatan|Kec\.?)\s+([A-Za-z\s]+?)(?=[,\.\n\r]|\s+(?:Kab|Kota|Desa|Kel|Rt|Rw|\d{5})|$)/i);
  if (kecMatch && kecMatch[1].trim()) {
    const clean = kecMatch[1].trim().replace(/\s+/g, " ");
    const words = clean.split(" ").slice(0, 2).join(" ");
    return `Kec. ${words}`;
  }

  // 4. Fallback to Office Name if available (e.g. "KCU SURABAYA 60000" -> "Surabaya", "KC SUMENEP 69400" -> "Sumenep")
  if (officeName) {
    const officeClean = officeName.replace(/^(?:KCU|KC|KCP|KANTOR\s+POS)\s+/i, "").replace(/\s+\d{5}$/, "").trim();
    if (officeClean && officeClean !== "TUJUAN") {
      return officeClean;
    }
  }

  // 5. Short snippet of destination if short
  if (text.length <= 35) {
    return text;
  }

  const firstComma = text.split(",")[0].trim();
  if (firstComma.length > 0 && firstComma.length <= 30) {
    return firstComma;
  }

  return text.substring(0, 28) + "...";
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

// Konfigurasi Khusus Mitra Aliqa sesuai catatan resmi:
// 1. HIJAU TOSKA -> PAKET SUKSES
// 2. MERAH -> PAKET RETUR
// 3. KUNING -> SUDAH DI FU
// 4. PUTIH -> BLM DI FU
// 5. BIRU TUA -> ON FU POS
export const FU_META_ALIQA: Record<
  FuStatus,
  { label: string; bg: string; fg: string; short: string }
> = {
  PUTIH: { label: "BLM DI FU", bg: "#FFFFFF", fg: "#1E293B", short: "PUTIH" },
  BIRU: { label: "PAKET SUKSES", bg: "#38D9A9", fg: "#000000", short: "HIJAU TOSKA" },
  ORANGE: { label: "PAKET RETUR", bg: "#E8A29A", fg: "#000000", short: "MERAH" },
  KUNING: { label: "SUDAH DI FU", bg: "#FFFF00", fg: "#000000", short: "KUNING" },
  HIJAU: { label: "FU 2 KALI", bg: "#93C47D", fg: "#14532D", short: "HIJAU" },
  BIRU_TUA: { label: "ON FU POS", bg: "#1C4587", fg: "#FFFFFF", short: "BIRU TUA" },
};

export const FU_ORDER_ALIQA: FuStatus[] = ["BIRU", "ORANGE", "KUNING", "PUTIH", "BIRU_TUA"];

export function getSellerFuMeta(seller?: string): Record<FuStatus, { label: string; bg: string; fg: string; short: string }> {
  const isAliqa = typeof seller === "string" && seller.toUpperCase().includes("ALIQA");
  return isAliqa ? FU_META_ALIQA : FU_META;
}

export function getSellerFuOrder(seller?: string): FuStatus[] {
  const isAliqa = typeof seller === "string" && seller.toUpperCase().includes("ALIQA");
  return isAliqa ? FU_ORDER_ALIQA : FU_ORDER;
}

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
