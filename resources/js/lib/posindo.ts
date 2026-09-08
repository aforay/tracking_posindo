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
  lastTrackedAt?: string;
  statusKategori?: string;
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

export type WaTemplateType = "STANDAR" | "ANTAR_ULANG" | "KONFIRMASI_ALAMAT" | "TAHAN_RETUR";

export const WA_TEMPLATES: { id: WaTemplateType; label: string; desc: string }[] = [
  {
    id: "STANDAR",
    label: "Pengecekan Standar (Default)",
    desc: "Permohonan pengecekan status & update pengantaran kiriman",
  },
  {
    id: "ANTAR_ULANG",
    label: "Permohonan Antar Ulang",
    desc: "Penerima sudah siap di alamat / siap menerima paket",
  },
  {
    id: "KONFIRMASI_ALAMAT",
    label: "Konfirmasi Nomor HP / Alamat",
    desc: "Pembaruan detail nomor telepon aktif atau patokan alamat penerima",
  },
  {
    id: "TAHAN_RETUR",
    label: "Permintaan Tahan Retur",
    desc: "Paket mohon tidak diretur dulu, CS koordinasi 1x24 jam",
  },
];

export function resolveDestinationOffice(shipment: Shipment, postOfficeName?: string): string {
  const rawOffice = (postOfficeName || shipment.kantorTujuan || "").trim();
  const generic = [
    'KC TUJUAN', 'KANTOR POS TUJUAN', 'KC POS PENGANTARAN', 'KC PENGANTARAN',
    'POS PENGANTARAN', 'KC POS INDONESIA', 'POS INDONESIA', 'KANTOR POS TERKAIT'
  ];
  const isGeneric = !rawOffice || generic.includes(rawOffice.toUpperCase());
  const isTransitHub = /^(SPP|MPC|DC|SENTRAL|TRANSIT)\b/i.test(rawOffice);

  // If explicit postOfficeName was selected and it's not generic/transit, use it
  if (postOfficeName && !isGeneric && !isTransitHub) {
    return postOfficeName;
  }

  const city = extractCityRegency(shipment.alamat || "", isGeneric || isTransitHub ? "" : rawOffice);

  // If rawOffice is a transit hub (like SPP JAKARTA TIMUR 13400) or generic, and we have destination city (e.g. Mimika), use destination KC!
  if ((isGeneric || isTransitHub) && city && city !== "-") {
    return `KC ${city.replace(/^(Kota|Kab\.?|Kec\.?)\s+/i, "").trim().toUpperCase()}`;
  }

  if (!isGeneric && !isTransitHub) {
    return rawOffice;
  }

  if (city && city !== "-") {
    return `KC ${city.replace(/^(Kota|Kab\.?|Kec\.?)\s+/i, "").trim().toUpperCase()}`;
  }

  return rawOffice || "Kantor Pos Terkait";
}

export function generatePostOfficeWaMessage(
  shipment: Shipment,
  postOfficeName?: string,
  customNote?: string,
  template: WaTemplateType = "STANDAR"
): string {
  const office = resolveDestinationOffice(shipment, postOfficeName);

  let judulPermohonan = "Pengecekan Status & Update Pengantaran";
  let penutup = "Mohon bantuannya untuk dilakukan pengecekan status dan bantuan pengantaran ke penerima ya rekan. Terima kasih atas kerjasamanya.";

  if (template === "ANTAR_ULANG") {
    return [
      `Halo Rekan CS/Antaran Pos Indonesia ${office},`,
      ``,
      `Mohon bantuannya untuk PERMOHONAN PENGANTARAN ULANG atas kiriman berikut:`,
      ` No. Resi: ${shipment.resi}`,
      ` Penerima: ${shipment.penerima || "-"} (${shipment.telepon || "-"})`,
      ` Alamat: ${shipment.alamat || shipment.tujuan || "-"}`,
      ``,
      `Mohon berkenan dibantu jadwalkan antaran ulang oleh rekan kurir ya kak. Terima kasih.`
    ].join("\n");
  }

  const lines = [
    `Halo Rekan CS/Antaran Pos Indonesia ${office},`,
    ``,
    `Mohon bantuannya untuk *${judulPermohonan}* atas kiriman berikut:`,
    ` No. Resi: ${shipment.resi}`,
    ` Penerima: ${shipment.penerima || "-"} (${shipment.telepon || "-"})`,
    ` Alamat: ${shipment.alamat || shipment.tujuan || "-"}`,
  ];

  if (shipment.seller) {
    lines.push(` Mitra / Seller: ${shipment.seller}`);
  }

  if (shipment.nipos && shipment.nipos !== "ON PROCESS") {
    lines.push(` Status Terakhir: ${shipment.nipos}`);
  }

  if (customNote && customNote.trim()) {
    lines.push(` Catatan Tambahan: ${customNote.trim()}`);
  }

  lines.push(``, penutup);

  return lines.join("\n");

}

export type WaCustomerTemplateType =
  | "ALAMAT_PATOKAN"
  | "RUMAH_KOSONG"
  | "KONFIRMASI_COD"
  | "HOLD_SEBELUM_RETUR";

export const WA_CUSTOMER_TEMPLATES: { id: WaCustomerTemplateType; label: string; desc: string }[] = [
  {
    id: "ALAMAT_PATOKAN",
    label: "Alamat Belum Jelas / Minta Patokan",
    desc: "Kurir kesulitan menemukan alamat penerima di lapangan",
  },
  {
    id: "RUMAH_KOSONG",
    label: "Rumah Kosong / Antar Ulang",
    desc: "Penerima tidak di tempat saat kurir datang mengantar",
  },
  {
    id: "KONFIRMASI_COD",
    label: "Konfirmasi & Pengingat COD",
    desc: "Mengingatkan pembeli menyiapkan dana tunai untuk kurir POS",
  },
  {
    id: "HOLD_SEBELUM_RETUR",
    label: "Pemberitahuan Darurat Retur",
    desc: "Paket terancam diretur hari ini jika tidak ada konfirmasi pembeli",
  },
];

export function generateCustomerWaMessage(
  shipment: Shipment,
  template: WaCustomerTemplateType = "ALAMAT_PATOKAN",
  customNote?: string
): string {
  const buyerName = shipment.penerima || "Kak";
  const seller = shipment.seller || "Toko Kami";

  let header = `Halo Kak ${buyerName},`;
  let body = "";
  let closing = "Mohon balas pesan ini sesegera mungkin agar kami bisa instruksikan rekan kurir Pos Indonesia untuk segera mengantar kembali paket kakak. Terima kasih banyak! 🙏😊";

  if (template === "ALAMAT_PATOKAN") {
    body = `Kami dari Customer Service *${seller}* ingin menginformasikan terkait pesanan paket kakak dengan:\n📦 *No. Resi:* ${shipment.resi}\n📍 *Alamat:* ${shipment.alamat || shipment.tujuan || "-"}\n\nMenurut laporan rekan kurir Pos Indonesia di lapangan, kurir mengalami kendala *alamat belum ditemukan / patokan kurang jelas*. Apakah berkenan memberikan patokan rumah terdekat, warna cat/pagar, atau nomor HP aktif lainnya kak?`;
  } else if (template === "RUMAH_KOSONG") {
    body = `Kami dari tim CS *${seller}* mengabarkan bahwa kurir Pos Indonesia hari ini sudah mendatangi alamat kakak untuk mengantar paket:\n📦 *No. Resi:* ${shipment.resi}\n\nNamun status antaran tercatat *Rumah Kosong / Tidak Ada Orang di Tempat*. Apakah besok kakak ada di lokasi, atau paket boleh dititipkan ke tetangga/keluarga serumah jika kurir datang kembali?`;
  } else if (template === "KONFIRMASI_COD") {
    body = `Kami dari Customer Service *${seller}* ingin menginfokan bahwa pesanan kakak:\n📦 *No. Resi:* ${shipment.resi}\nSaat ini sudah tiba di kota tujuan dan sedang dipersiapkan untuk pengantaran oleh kurir Pos Indonesia.\n\nMohon pastikan nomor HP selalu aktif dan dana tunai COD telah disiapkan di rumah ya kak, agar proses serah terima berjalan lancar.`;
  } else if (template === "HOLD_SEBELUM_RETUR") {
    body = `⚠️ *PEMBERITAHUAN PENTING:* Paket pesanan kakak dari *${seller}*:\n📦 *No. Resi:* ${shipment.resi}\nSaat ini berada di Kantor Pos tujuan dan telah mengalami beberapa kali gagal serah, sehingga *terancam akan otomatis diretur/dikembalikan ke penjual hari ini*.\n\nJika kakak masih ingin menerima paket ini, mohon segera konfirmasi waktu penerimaan atau apakah paket bisa diambil mandiri di Kantor Pos terdekat?`;
  }

  const lines = [header, "", body];
  if (customNote && customNote.trim()) {
    lines.push("", `📝 *Catatan CS:* ${customNote.trim()}`);
  }
  lines.push("", closing);

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

  // 3. Fallback to Office Name if available (e.g. "KCU SURABAYA 60000" -> "Surabaya", "KC SUMENEP 69400" -> "Sumenep")
  if (officeName) {
    const officeClean = officeName
      .replace(/^(?:KCU|KC|KCP|MPC|DC|SPP|KANTOR\s+POS)\s+/i, "")
      .replace(/\s+\d{5}[A-Za-z0-9]*$/, "")
      .trim();
    if (officeClean && !["TUJUAN", "PENGANTARAN", "POS PENGANTARAN", "POS", "POS INDONESIA"].includes(officeClean.toUpperCase())) {
      return officeClean;
    }
  }

  // 4. Try matching direct city/region names commonly present in Indonesian addresses
  const knownCities = [
    // Jabodetabek & Jawa Barat
    "Jakarta", "Bogor", "Depok", "Tangerang", "Bekasi", "Bandung", "Cimahi", "Cirebon", "Sukabumi", "Tasikmalaya",
    "Banjar", "Ciamis", "Garut", "Cianjur", "Purwakarta", "Subang", "Karawang", "Indramayu", "Majalengka", "Kuningan",
    "Sumedang", "Pangandaran", "Serang", "Cilegon", "Lebak", "Pandeglang",
    // Jawa Tengah & DIY
    "Semarang", "Surakarta", "Solo", "Magelang", "Pekalongan", "Salatiga", "Tegal", "Banyumas", "Purwokerto", "Batang",
    "Blora", "Boyolali", "Brebes", "Cilacap", "Demak", "Grobogan", "Jepara", "Karanganyar", "Kebumen", "Kendal",
    "Klaten", "Kudus", "Pati", "Pemalang", "Purbalingga", "Purworejo", "Rembang", "Sragen", "Sukoharjo", "Temanggung",
    "Wonogiri", "Wonosobo", "Yogyakarta", "Jogja", "Bantul", "Sleman", "Kulon Progo", "Gunungkidul",
    // Jawa Timur
    "Surabaya", "Malang", "Batu", "Kediri", "Blitar", "Madiun", "Mojokerto", "Pasuruan", "Probolinggo", "Banyuwangi",
    "Bangkalan", "Sampang", "Pamekasan", "Sumenep", "Madura", "Jember", "Bondowoso", "Situbondo", "Lumajang", "Sidoarjo",
    "Gresik", "Lamongan", "Tuban", "Bojonegoro", "Ngawi", "Magetan", "Ponorogo", "Pacitan", "Trenggalek", "Tulungagung",
    "Nganjuk", "Jombang",
    // Sumatera
    "Banda Aceh", "Sabang", "Lhokseumawe", "Langsa", "Subulussalam", "Bireuen", "Takengon", "Meulaboh", "Aceh Besar",
    "Medan", "Binjai", "Tebing Tinggi", "Pematangsiantar", "Tanjungbalai", "Sibolga", "Padang Sidempuan", "Gunungsitoli",
    "Deli Serdang", "Karo", "Simalungun", "Asahan", "Labuhanbatu", "Rantauprapat", "Nias",
    "Padang", "Bukittinggi", "Padang Panjang", "Pariaman", "Payakumbuh", "Sawahlunto", "Solok", "Agam", "Pasaman",
    "Pekanbaru", "Dumai", "Bengkalis", "Kampar", "Indragiri", "Tembilahan", "Rokan Hilir", "Rokan Hulu", "Siak", "Kuantan Singingi",
    "Batam", "Tanjungpinang", "Karimun", "Bintan", "Natuna", "Anambas", "Lingga",
    "Jambi", "Sungai Penuh", "Batanghari", "Bungo", "Kerinci", "Merangin", "Muaro Jambi", "Sarolangun", "Tanjung Jabung", "Tebo",
    "Palembang", "Prabumulih", "Pagar Alam", "Lubuklinggau", "Banyuasin", "Empat Lawang", "Lahat", "Muara Enim", "Musi Banyuasin", "Musi Rawas", "Ogan Ilir", "Ogan Komering",
    "Bengkulu", "Curup", "Rejang Lebong", "Argamakmur", "Mukomuko", "Manna",
    "Bandar Lampung", "Metro", "Lampung", "Tulang Bawang", "Menggala", "Pringsewu", "Pesawaran", "Tanggamus",
    "Pangkalpinang", "Bangka", "Belitung", "Tanjung Pandan",
    // Bali & Nusa Tenggara
    "Denpasar", "Badung", "Bangli", "Buleleng", "Gianyar", "Jembrana", "Karangasem", "Klungkung", "Tabanan",
    "Mataram", "Bima", "Dompu", "Lombok", "Sumbawa",
    "Kupang", "Ende", "Flores", "Alor", "Belu", "Manggarai", "Ngada", "Rote Ndao", "Sikka", "Sumba", "Timor Tengah",
    // Kalimantan
    "Pontianak", "Singkawang", "Sambas", "Bengkayang", "Landak", "Mempawah", "Sanggau", "Ketapang", "Sintang", "Kapuas Hulu",
    "Banjarmasin", "Banjarbaru", "Banjar", "Martapura", "Barito Kuala", "Tapin", "Hulu Sungai", "Tabalong", "Tanah Laut", "Kotabaru", "Tanah Bumbu",
    "Palangka Raya", "Barito", "Muara Teweh", "Kapuas", "Katingan", "Kotawaringin", "Sampit", "Pangkalan Bun", "Lamandau", "Seruyan", "Sukamara",
    "Samarinda", "Balikpapan", "Bontang", "Kutai Kartanegara", "Tenggarong", "Kutai Barat", "Kutai Timur", "Berau", "Penajam Paser", "Paser",
    "Tarakan", "Bulungan", "Tanjung Selor", "Malinau", "Nunukan", "Tana Tidung",
    // Sulawesi
    "Makassar", "Palopo", "Parepare", "Bantaeng", "Barru", "Bone", "Bulukumba", "Enrekang", "Gowa", "Jeneponto", "Luwu", "Maros", "Pangkep", "Pinrang", "Selayar", "Sinjai", "Soppeng", "Takalar", "Tana Toraja", "Toraja Utara", "Wajo",
    "Manado", "Bitung", "Kotamobagu", "Tomohon", "Bolaang Mongondow", "Minahasa", "Sangihe", "Talaud",
    "Palu", "Banggai", "Luwuk", "Buol", "Donggala", "Morowali", "Parigi Moutong", "Poso", "Sigi", "Tojo Una-Una", "Tolitoli",
    "Kendari", "Baubau", "Bombana", "Buton", "Kolaka", "Konawe", "Muna", "Raha", "Wakatobi",
    "Gorontalo", "Boalemo", "Bone Bolango", "Pohuwato",
    "Mamuju", "Majene", "Polewali Mandar", "Pasangkayu",
    // Maluku & Papua
    "Ambon", "Tual", "Buru", "Maluku Tengah", "Masohi", "Maluku Tenggara", "Kepulauan Aru", "Seram",
    "Ternate", "Tidore", "Halmahera", "Tobelo", "Morotai", "Sula",
    "Jayapura", "Keerom", "Sarmi", "Mamberamo", "Sentani",
    "Sorong", "Raja Ampat", "Fakfak", "Kaimana", "Manokwari", "Maybrat", "Tambrauw", "Bintuni", "Wondama",
    "Merauke", "Boven Digoel", "Mappi", "Asmat",
    "Nabire", "Paniai", "Mimika", "Timika", "Intan Jaya", "Deiyai", "Dogiyai", "Puncak", "Puncak Jaya",
    "Wamena", "Jayawijaya", "Lanny Jaya", "Nduga", "Tolikara", "Yahukimo", "Yalimo", "Pegunungan Bintang", "Biak", "Yapen"
  ];
  for (const c of knownCities) {
    const reg = new RegExp(`\\b${c}\\b`, "i");
    if (reg.test(text)) {
      return c;
    }
  }

  // 5. Try matching "Kec. [Name]" if no Kab/Kota
  const kecMatch = text.match(/\b(?:Kecamatan|Kec\.?)\s+([A-Za-z\s]+?)(?=[,\.\n\r]|\s+(?:Kab|Kota|Desa|Kel|Rt|Rw|\d{5})|$)/i);
  if (kecMatch && kecMatch[1].trim()) {
    const clean = kecMatch[1].trim().replace(/\s+/g, " ");
    const words = clean.split(" ").slice(0, 2).join(" ");
    return `Kec. ${words}`;
  }

  return "-";
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
