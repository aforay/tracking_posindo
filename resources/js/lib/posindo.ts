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
  namaCs?: string;
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
  kantorPosPhone2?: string;
  kantorPosTelegram?: string;
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
  phone_wa_2?: string;
  telegram_handle?: string;
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

export const DISTRICT_TO_KC: Record<string, string> = {
  'HINAI': 'KC BINJAI',
  'LANGKAT': 'KC BINJAI',
  'STABAT': 'KC BINJAI',
  'TANJUNG PURA': 'KC BINJAI',
  'TANJUNGPURA': 'KC BINJAI',
  'PANGKALAN BRANDAN': 'KC BINJAI',
  'PANGKALANBRANDAN': 'KC BINJAI',
  'BRANDAN': 'KC BINJAI',
  'BESITANG': 'KC BINJAI',
  'SECANGGANG': 'KC BINJAI',
  'GEBANG': 'KC BINJAI',
  'BABALAN': 'KC BINJAI',
  '20854': 'KC BINJAI',
  '20855': 'KC BINJAI',
  '20853': 'KC BINJAI',
  '20857': 'KC BINJAI',
  '20859': 'KC BINJAI',
  '20811': 'KC BINJAI',
  'MANDAU': 'KC DUMAI',
  'DURI': 'KC DUMAI',
  'BENGKALIS': 'KC DUMAI',
  'PINGGIR': 'KC DUMAI',
  'BATHIN SOLAPAN': 'KC DUMAI',
  'ROKAN HILIR': 'KC DUMAI',
  'BAGANSIAPIAPI': 'KC DUMAI',
  'BAGAN SIAPIAPI': 'KC DUMAI',
  'ROHIL': 'KC DUMAI',
  '28784': 'KC DUMAI',
  '28782': 'KC DUMAI',
  '28783': 'KC DUMAI',
  '28785': 'KC DUMAI',
  '28786': 'KC DUMAI',
  '28800': 'KC DUMAI',
  'ROKAN HULU': 'KC BANGKINANG',
  'ROHUL': 'KC BANGKINANG',
  'PASIRPENGARAIAN': 'KC BANGKINANG',
  'PASIR PENGARAIAN': 'KC BANGKINANG',
  'DALUDALU': 'KC BANGKINANG',
  'DALU DALU': 'KC BANGKINANG',
  'TAMBUSAI': 'KC BANGKINANG',
  'TAMBUSAI UTARA': 'KC BANGKINANG',
  'BANGKINANG': 'KC BANGKINANG',
  'KAMPAR': 'KC BANGKINANG',
  '28558': 'KC BANGKINANG',
  '28511': 'KC BANGKINANG',
  '28500': 'KC BANGKINANG',
  'BAHODOPI': 'KCU PALU',
  'FATUFIA': 'KCU PALU',
  'MOROWALI': 'KCU PALU',
  'POSO': 'KCU PALU',
  'BUNGKU': 'KCU PALU',
  '94951': 'KCU PALU',
  '94600': 'KCU PALU',
  'KALIORANG': 'KC BONTANG',
  'RANTAUPULUNG': 'KC BONTANG',
  'RANTAU PULUNG': 'KC BONTANG',
  'TEPIAN INDAH': 'KC BONTANG',
  'SANGATTA': 'KC BONTANG',
  'KUTAI TIMUR': 'KC BONTANG',
  'KUTIM': 'KC BONTANG',
  'BENGALON': 'KC BONTANG',
  'KONGBENG': 'KC BONTANG',
  'WAHAU': 'KC BONTANG',
  '75652': 'KC BONTANG',
  '75600': 'KC BONTANG',
  'MUARAANCALONG': 'KC SAMARINDA',
  'MUARA ANCALONG': 'KC SAMARINDA',
  'MUARABENGKAL': 'KC SAMARINDA',
  'MUARA BENGKAL': 'KC SAMARINDA',
  '75556': 'KC SAMARINDA',
  '75554': 'KC SAMARINDA',
  'MANOKWARI': 'KC MANOKWARI',
  'BINTUNI': 'KC MANOKWARI',
  'BABO': 'KC MANOKWARI',
  'TOFOI': 'KC MANOKWARI',
  '98300': 'KC MANOKWARI',
  '98364': 'KC MANOKWARI',
  '98572': 'KC MANOKWARI',
  'MUARASABAK': 'KCU JAMBI',
  'MUARA SABAK': 'KCU JAMBI',
  'BENUAKAYONG': 'KC KETAPANG',
  'BENUA KAYONG': 'KC KETAPANG',
  'ALOR BARAT': 'KCU KUPANG',
  'SERDANG': 'KCU SERANG 42100',
  'KRAKATAU': 'KCU SERANG 42100',
  '42161': 'KCU SERANG 42100',
  'LABUAN': 'KCU SERANG 42100',
  'PANDEGLANG': 'KCU SERANG 42100',
  'DAYEUHKOLOT': 'KCU BANDUNG 40000',
  'SEKEJATI': 'KCU BANDUNG 40000',
  'SITUSAEUR': 'KCU BANDUNG 40000',
  'TELLOBARU': 'KCU MAKASSAR 90000',
  'SUNGGUMINASA': 'KCU MAKASSAR 90000',
  'JONGAYA': 'KCU MAKASSAR 90000',
  'DAYA': 'KCU MAKASSAR 90000',
  'MAROS': 'KCU MAKASSAR 90000',
  'PRAMBANAN': 'KCU YOGYAKARTA 55000',
  'DEMAK': 'KCU SEMARANG 50000',
  'MRANGGEN': 'KCU SEMARANG 50000',
  'JUANDA': 'KCU SURABAYA 60000',
  'SURABAYA UTARA': 'KCU SURABAYA 60000',
  'SAWAHANNGANJUK': 'KC KEDIRI 64100',
  'KRAS': 'KC KEDIRI 64100',
  'GUCIALIT': 'KC PROBOLINGGO',
  'PARON': 'KCU Madiun 63100',
  'MANTINGAN': 'KCU Madiun 63100',
  'KEDUNGGALAR': 'KCU Madiun 63100',
  'GEMARANG': 'KCU Madiun 63100',
  'NGAWI': 'KCU Madiun 63100',
  'BARAT': 'KCU Madiun 63100',
  'GENENG': 'KCU Madiun 63100',
  'JOGOROGO': 'KCU Madiun 63100',
  'SINE': 'KCU Madiun 63100',
  'NGRAMBE': 'KCU Madiun 63100',
  'WIDODAREN': 'KCU Madiun 63100',
  'WALIKUKUN': 'KCU Madiun 63100',
  'GENTENG': 'KC JEMBER 68100',
  'SAMBENG': 'KC GRESIK 61100',
  'TIKUNG': 'KC GRESIK 61100',
  'SUMBER': 'KCU CIREBON 45100',
  'SEMBALUN': 'KCU MATARAM 83000',
  'LUBUKBASUNG': 'KC BUKITTINGGI 26100',
  'PEDAN': 'KCU SOLO 57100',
  'WAWONDULA': 'KC PALOPO',
  'PAJUKUKANG': 'KC BULUKUMBA',
  'PESANGGARAN': 'KC JEMBER 68100',
  'SAMBEREJO': 'KC BENGKULU 38000',
  'PADANG BULAN': 'KCU JAYAPURA 99000',
  'PADANGBULAN': 'KCU JAYAPURA 99000',
  'HEDAM': 'KCU JAYAPURA 99000',
  'PADANG BATUNG': 'KC BANJARMASIN 70000',
  'KOLONEDALE': 'KC LUWUK 94700',
  'KOLONODALE': 'KC LUWUK 94700',
  'BELITANG': 'KCU PALEMBANG 30000',
  'MUARADUA': 'KCU PALEMBANG 30000',
  'BOJONGGEDE': 'KC CIBINONG',
  'RANAU': 'KCU PALEMBANG 30000',
  'KEMBAYAN': 'KC SANGGAU 78500',
  'SANGGAU': 'KC SANGGAU 78500',
  'SEKADAU': 'KC SANGGAU 78500',
  'ENTIKONG': 'KC SANGGAU 78500',
  'TAYAN': 'KC SANGGAU 78500',
  'BEDUAI': 'KC SANGGAU 78500',
  'KASIPUTE': 'KCU KENDARI 93000',
  'BOMBANA': 'KCU KENDARI 93000',
  'HUKAEA': 'KCU KENDARI 93000',
  'RUMBIA': 'KCU KENDARI 93000',
  'POLEANG': 'KCU KENDARI 93000',
  'RAROWATU': 'KCU KENDARI 93000',
  'NABIRE': 'KC NABIRE 98800',
  'PANIAI': 'KC NABIRE 98800',
  'ENAROTALI': 'KC NABIRE 98800',
  'DEIYAI': 'KC NABIRE 98800',
  'DOGIYAI': 'KC NABIRE 98800',
  'WANGGAR': 'KC NABIRE 98800',
  'ORO ORO DOWO': 'KCU MALANG 65100',
  'ORO-ORO DOWO': 'KCU MALANG 65100',
  'PASTINA': 'KC TOBELO 97762',
  'SITUJUH': 'KC PAYAKUMBUH 26200',
  'SITUJUAH': 'KC PAYAKUMBUH 26200',
};

export function isAddressText(str?: string): boolean {
  if (!str) return false;
  const s = str.trim().toUpperCase();
  if (s.length > 35) return true;
  if (/\b(JL|JLN|JALAN|GG|GANG|RT|RW|BLOK|NO\b|NOMOR|DESA|DUSUN|KEL\b|KELURAHAN|KAMPUNG|KP\b|PERUM|PERUMAHAN|KOMPLEK|PATOKAN|CUSTOMER|DISHERLOCK|SEBELAH|DEPAN|BELAKANG|DEKAT)\b/i.test(s)) return true;
  if (/\d+\s*[\/\-]\s*\d+/.test(s)) return true;
  return false;
}

export function resolveDestinationOffice(shipment: Shipment, postOfficeName?: string): string {
  const rawOffice = (postOfficeName || shipment.kantorTujuan || "").replace(/\bMPS\b/gi, "SPP").trim();
  const generic = [
    'KC TUJUAN', 'KC POS TUJUAN', 'KANTOR POS TUJUAN', 'KC POS PENGANTARAN', 'KC PENGANTARAN',
    'POS PENGANTARAN', 'KC POS INDONESIA', 'POS INDONESIA', 'KANTOR POS TERKAIT',
    'SEDANG MEMBACA NIPOS...', 'SEDANG MEMBACA NIPOS'
  ];
  const isGeneric = !rawOffice || generic.includes(rawOffice.toUpperCase()) || isAddressText(rawOffice);
  // SPP IS VALID FOR FOLLOW UP (KC, KCU, and SPP are the only 3 allowed). Only MPC, DC, SENTRAL, TRANSIT are invalid transit hubs.
  const isTransitHub = /^(MPC|DC\b|SENTRAL|TRANSIT)\b/i.test(rawOffice);
  const isKcp = /\bKCP\b/i.test(rawOffice) || /\b\d{5}B\d\b/i.test(rawOffice);
  const isDc = /\bDC\b/i.test(rawOffice);
  const isFakeKc = /^KC\s+(?:KEC\b|KECAMATAN\b|HINAI|SECANGGANG|KALIORANG|MUARASABAK)/i.test(rawOffice) || isAddressText(rawOffice);

  // If explicit postOfficeName was selected and it's not generic/transit/kcp/isDc/fakeKc, use it
  if (postOfficeName && !isGeneric && !isTransitHub && !isKcp && !isDc && !isFakeKc) {
    return postOfficeName;
  }

  // 1. Direct district / KCP to KC dictionary match
  const combined = `${rawOffice} ${shipment.alamat || ""} ${shipment.tujuan || ""}`.toUpperCase();
  for (const [key, kc] of Object.entries(DISTRICT_TO_KC)) {
    if (combined.includes(key)) {
      return kc;
    }
  }

  const city = extractCityRegency(shipment.alamat || "", (isGeneric || isTransitHub || isKcp || isDc || isFakeKc) ? "" : rawOffice);

  // KCP and DC cannot handle follow-ups -> redirect to KC / KCU
  if (isKcp || isDc || isFakeKc) {
    if (city && city !== "-" && !city.startsWith("Kec.")) {
      return `KC ${city.replace(/^(Kota|Kab\.?)\s+/i, "").trim().toUpperCase()}`;
    }
    return "";
  }

  if (!isGeneric && !isTransitHub && !isKcp && !isDc && !isFakeKc && !isAddressText(rawOffice)) {
    return rawOffice;
  }

  if (city && city !== "-" && !city.startsWith("Kec.")) {
    return `KC ${city.replace(/^(Kota|Kab\.?)\s+/i, "").trim().toUpperCase()}`;
  }

  return "";
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
  // Do NOT use KCP village names as cities
  if (officeName && !/\bKCP\b/i.test(officeName) && !/\b\d{5}B\d\b/i.test(officeName) && !/^KC\s+(?:HINAI|SECANGGANG|KALIORANG|MUARASABAK)/i.test(officeName)) {
    const officeClean = officeName
      .replace(/^(?:KCU|KC|MPC|DC|SPP|KANTOR\s+POS)\s+/i, "")
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
    "Medan", "Binjai", "Langkat", "Stabat", "Tebing Tinggi", "Pematangsiantar", "Tanjungbalai", "Sibolga", "Padang Sidempuan", "Gunungsitoli",
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
  BIRU: { label: "PAKET SUKSES", bg: "#46BDC6", fg: "#000000", short: "BIRU" },
  ORANGE: { label: "PAKET RETUR", bg: "#FBBC04", fg: "#000000", short: "ORANGE" },
  KUNING: { label: "SUDAH DI FU", bg: "#ffff00", fg: "#000000", short: "KUNING" },
  HIJAU: { label: "FU 2 KALI", bg: "#93C47D", fg: "#14532D", short: "HIJAU" },
  BIRU_TUA: { label: "FU POS", bg: "#1C4587", fg: "#FFFFFF", short: "BIRU TUA" },
};

export const FU_ORDER: FuStatus[] = ["BIRU", "ORANGE", "KUNING", "PUTIH", "HIJAU", "BIRU_TUA"];

// Konfigurasi Khusus Mitra Aliqa sesuai catatan resmi:
// 1. HIJAU TOSKA / MINT (#40e4b4) -> PAKET SUKSES
// 2. MERAH (#ff0000) -> PAKET RETUR
// 3. KUNING (#ffff00) -> SUDAH DI FU
// 4. PUTIH -> BLM DI FU
// 5. BIRU TUA -> ON FU POS
export const FU_META_ALIQA: Record<
  FuStatus,
  { label: string; bg: string; fg: string; short: string }
> = {
  PUTIH: { label: "BLM DI FU", bg: "#FFFFFF", fg: "#1E293B", short: "PUTIH" },
  BIRU: { label: "PAKET SUKSES", bg: "#40e4b4", fg: "#000000", short: "HIJAU TOSKA" },
  ORANGE: { label: "PAKET RETUR", bg: "#ff0000", fg: "#FFFFFF", short: "MERAH" },
  KUNING: { label: "SUDAH DI FU", bg: "#ffff00", fg: "#000000", short: "KUNING" },
  HIJAU: { label: "BLM DI FU", bg: "#FFFFFF", fg: "#1E293B", short: "PUTIH" },
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
  const datePart = iso.includes("T") ? iso.split("T")[0] : (iso.includes(" ") ? iso.split(" ")[0] : iso);
  const parts = datePart.split("-");
  if (parts.length !== 3) return iso;
  const [y, m, d] = parts;
  return `${d}/${m}/${y}`;
}

export function nf(n?: number | null) {
  if (n === null || n === undefined || isNaN(Number(n))) return "0";
  return Number(n).toLocaleString("id-ID");
}
