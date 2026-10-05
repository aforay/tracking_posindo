/**
 * ============================================================
 * POSINDO TRACKING - Google Apps Script Webhook (High Performance)
 * ============================================================
 * Fitur:
 * - Super Cepat (Bulk 2D Array Write, ribuan resi dalam hitungan detik)
 * - Anti Timeout (Tidak ada lagi error "Maximum execution time exceeded")
 * - Smart Sheet Resolver (Mencari resi di seluruh tab jika tidak ditemukan)
 * - Sinkronisasi Dua Arah Warna & Status (Biru, Orange, Biru Tua, Hijau, Kuning, Putih)
 * ============================================================
 * CARA PASANG:
 * 1. Buka Spreadsheet Seller di Google Sheets
 * 2. Klik Extensions → Apps Script
 * 3. Hapus semua kode yang ada, paste seluruh kode ini
 * 4. Klik Save (Ctrl+S)
 * 5. Klik Deploy → Manage deployments (atau New Deployment)
 *    - Edit versi aktif atau buat Versi Baru (New Version)
 *    - Execute as: Me
 *    - Who has access: Anyone
 * 6. Klik Deploy → Copy URL Webhook
 * 7. Pastikan URL sudah tersimpan di Pengaturan website Posindo
 * ============================================================
 */

var CONFIG = {
  COL_RESI:       ["NO RESI", "RESI", "BARCODE", "BARCODE ITEM", "NO BARCODE", "NOMOR RESI"],
  COL_STATUS_FU:  ["STATUS FU", "FU", "FOLLOW UP", "WARNA", "STATUS CS", "SUDAH FU"],
  COL_STATUS_POS: ["STATUS POS", "TRACKING POS", "STATUS NIPOS", "STATUS AKHIR", "TRACKING"],
  COL_KETERANGAN: ["KETERANGAN", "POS KETERANGAN", "PENERIMA & KETERANGAN", "KETERANGAN K"],
  COL_SLA:        ["SLA", "SLA MASA TAHAN", "SLA DAYS"],
  COL_TANGGAL_FU: ["TANGGAL FU", "TGL FU", "WAKTU FU", "TGL FOLLOW UP"],
};

function doPost(e) {
  try {
    var data   = JSON.parse(e.postData.contents);
    var action = data.action || '';
    Logger.log("Webhook: action=" + action);
    var result = { status: 'success', updated_count: 0 };

    if (action === 'update_fu_status' || action === 'update_status') {
      result = handleFuStatusUpdate(data);
    } else if (action === 'reverse_sync_nipos') {
      result = handleNiposUpdate(data);
    } else if (action === 'pull_sheet_colors' || action === 'sync_colors' || action === 'get_colors') {
      result = handlePullColors(data);
    } else if (action === 'apply_filter') {
      result = { status: 'success', updated_count: 0, message: 'Filter received' };
    }

    return ContentService.createTextOutput(JSON.stringify(result)).setMimeType(ContentService.MimeType.JSON);
  } catch(err) {
    Logger.log("Webhook error: " + err.toString());
    return ContentService.createTextOutput(JSON.stringify({ status: 'error', message: err.toString() })).setMimeType(ContentService.MimeType.JSON);
  }
}

/**
 * Handle Reverse Sync NIPos Tracking & Status
 * ATURAN KERAS: Bot NIPOS TIDAK BOLEH merubah apa pun di Spreadsheet (warna, keterangan, SLA tetap utuh)!
 * Update NIPOS hanya dilakukan di Web Tracko.
 */
function handleNiposUpdate(data) {
  return {
    status: 'success',
    seller: 'Mitra Aliqa',
    updated_count: 0,
    message: 'Bot NIPOS hanya dilakukan di Web Tracko. Spreadsheet tidak disentuh sama sekali.'
  };
}

/**
 * Handle Follow Up Status Update dari CS Activity Log / Modal (Bulk 2D Array)
 * ATURAN KERAS: Spreadsheet CUMA BERUBAH KETIKA ADA YG PUSH WARNA FU POS (BIRU_TUA)!
 */
function handleFuStatusUpdate(data) {
  var resiList  = data.resis || data.resi_list || [];
  var fuType    = (data.fu_type || data.color_code || 'PUTIH').toUpperCase();

  // JIKA BUKAN BIRU_TUA (FU POS), JANGAN SENTUH SPREADSHEET SAMA SEKALI!
  if (fuType !== 'BIRU_TUA') {
    return {
      status: 'success',
      seller: 'Mitra Aliqa',
      updated_count: 0,
      message: 'Spreadsheet hanya boleh berubah saat push FU POS (Biru Tua). Aksi selain FU POS diabaikan.'
    };
  }

  var fuLabel   = 'ESKALASI POS';
  var fuTime    = data.fu_timestamp || data.updated_at || '';
  var resiSet   = {};
  resiList.forEach(function(r){ if(r) resiSet[String(r).trim().toUpperCase()] = true; });

  if (Object.keys(resiSet).length === 0) {
    return { status: 'success', updated_count: 0, message: 'No resis provided' };
  }

  var ss = getSpreadsheet(data);
  var allSheets = ss.getSheets();
  var sheetsToScan = [];

  var hintSheetName = String(data.sheet || data.sheet_name || '').trim();
  if (hintSheetName) {
    var matched = findSheetByName(ss, hintSheetName);
    if (matched) {
      sheetsToScan.push(matched);
    }
  }
  var scannedIds = {};
  sheetsToScan.forEach(function(sh) { scannedIds[sh.getSheetId()] = true; });
  allSheets.forEach(function(sh) {
    if (!scannedIds[sh.getSheetId()]) {
      sheetsToScan.push(sh);
    }
  });

  var totalUpdated = 0;
  var bg = getFuBg(fuType);
  var fg = getFuFg(fuType);
  var formattedDate = fuTime ? fmtDate(fuTime) : '';

  for (var sIdx = 0; sIdx < sheetsToScan.length; sIdx++) {
    if (Object.keys(resiSet).length === 0) break;

    var sheet = sheetsToScan[sIdx];
    var hdr = findHeaderRow(sheet);
    if (!hdr) continue;

    var cResi = findCol(hdr, CONFIG.COL_RESI);
    if (!cResi) continue;

    var cFu  = findCol(hdr, CONFIG.COL_STATUS_FU);
    var cTgl = findCol(hdr, CONFIG.COL_TANGGAL_FU);

    var lastRow = sheet.getLastRow();
    var lastCol = sheet.getLastColumn();
    var numRows = lastRow - hdr.rowIndex;
    if (numRows <= 0) continue;

    var resiVals = sheet.getRange(hdr.rowIndex + 1, cResi, numRows, 1).getValues();

    for (var i = 0; i < numRows; i++) {
      var rVal = String(resiVals[i][0] || '').trim().toUpperCase();
      if (rVal && resiSet[rVal]) {
        var actualRow = hdr.rowIndex + 1 + i;

        // Pewarnaan di Spreadsheet oleh Admin HANYA untuk status FU POS (BIRU_TUA)!
        // HANYA SEL NOMOR RESI YANG DIWARNAI (1 sel tunggal, kolom lain seperti Nama, Alamat, Status, Keterangan, SLA TIDAK disentuh)
        if (cResi && fuType === 'BIRU_TUA') {
          sheet.getRange(actualRow, cResi).setBackground(bg).setFontColor(fg);
        }

        delete resiSet[rVal];
        totalUpdated++;
      }
    }
  }

  return { status: 'success', updated_count: totalUpdated, message: 'FU ' + fuLabel + ' berhasil diupdate untuk ' + totalUpdated + ' resi.' };
}

function findHeaderRow(sheet) {
  for (var r = 1; r <= Math.min(5, sheet.getLastRow()); r++) {
    var row = sheet.getRange(r, 1, 1, sheet.getLastColumn()).getValues()[0].map(function(v) { return String(v).toUpperCase(); });
    for (var c = 0; c < row.length; c++) {
      if (CONFIG.COL_RESI.some(function(k) { return row[c].includes(k); })) {
        return { rowIndex: r, headers: row };
      }
    }
  }
  return null;
}

function findCol(hdr, keywords) {
  for (var c = 0; c < hdr.headers.length; c++) {
    var h = hdr.headers[c].trim();
    if (keywords.some(function(k) { return h === k || h.includes(k); })) {
      return c + 1;
    }
  }
  return null;
}

function getFuBg(ft) {
  return {
    BIRU: '#40e4b4',      // Delivered (#40e4b4)
    ORANGE: '#ff0000',    // Retur (#ff0000)
    KUNING: '#ffff00',    // FU Kuning (#ffff00)
    PUTIH: '#FFFFFF',     // White (Belum di FU / In Process)
    HIJAU: '#93C47D',     // Soft Green (Sudah Dihubungi / FU 2x)
    BIRU_TUA: '#1F4E79'   // Dark Navy Blue (FU POS / Eskalasi)
  }[ft] || '#FFFFFF';
}

function getFuFg(ft) { 
  return (ft === 'ORANGE' || ft === 'BIRU_TUA') ? '#FFFFFF' : '#000000'; 
}

function fmtDate(s) { 
  try {
    var d = new Date(s);
    var p = function(n) { return n < 10 ? '0' + n : n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
  } catch(e) {
    return s;
  }
}

function getSpreadsheet(data) {
  var targetId = data.spreadsheet_id || data.sheet_id || '';
  if (targetId && String(targetId).trim()) {
    try {
      return SpreadsheetApp.openById(String(targetId).trim());
    } catch(e) {
      Logger.log("Failed openById " + targetId + ": " + e.toString());
    }
  }
  return SpreadsheetApp.getActiveSpreadsheet();
}

function findSheetByName(ss, hintSheetName) {
  if (!hintSheetName) return null;
  var targetUpper = String(hintSheetName).trim().toUpperCase();
  var allSheets = ss.getSheets();

  // 1. Exact match
  for (var i = 0; i < allSheets.length; i++) {
    if (allSheets[i].getName().trim().toUpperCase() === targetUpper) {
      return allSheets[i];
    }
  }

  // 2. Substring match
  for (var i = 0; i < allSheets.length; i++) {
    var n = allSheets[i].getName().trim().toUpperCase();
    if (n.indexOf(targetUpper) !== -1 || targetUpper.indexOf(n) !== -1) {
      return allSheets[i];
    }
  }

  // 3. Month keyword match
  var months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];
  for (var m = 0; m < months.length; m++) {
    if (targetUpper.indexOf(months[m]) !== -1) {
      for (var i = 0; i < allSheets.length; i++) {
        if (allSheets[i].getName().trim().toUpperCase().indexOf(months[m]) !== -1) {
          return allSheets[i];
        }
      }
    }
  }

  return null;
}

/**
 * Otomatis mencari atau MEMBUAT SHEET BULAN BARU (misal SEPTEMBER 2026 / SEPTEMBER (ZAHERBA)) jika belum ada
 */
function getOrCreateSheet(ss, hintSheetName) {
  if (!hintSheetName) return null;

  var targetName = String(hintSheetName).trim();
  var targetUpper = targetName.toUpperCase();
  var allSheets = ss.getSheets();

  // 1. Cari jika sheet sudah ada (case-insensitive)
  for (var i = 0; i < allSheets.length; i++) {
    var nameUpper = allSheets[i].getName().trim().toUpperCase();
    if (nameUpper === targetUpper || nameUpper.indexOf(targetUpper) !== -1 || targetUpper.indexOf(nameUpper) !== -1) {
      return allSheets[i];
    }
  }

  // 2. Jika belum ada, duplikat otomatis dari template atau sheet bulan sebelumnya (misal AGUSTUS)
  try {
    var templateSheet = null;
    for (var j = allSheets.length - 1; j >= 0; j--) {
      var sName = allSheets[j].getName().toUpperCase();
      if (sName.indexOf('TEMPLATE') !== -1 || sName.indexOf('AGUSTUS') !== -1 || sName.indexOf('JULI') !== -1 || sName.indexOf('JUNI') !== -1) {
        templateSheet = allSheets[j];
        break;
      }
    }
    if (!templateSheet && allSheets.length > 0) {
      templateSheet = allSheets[allSheets.length - 1];
    }

    if (templateSheet) {
      var newSheet = templateSheet.copyTo(ss);
      newSheet.setName(targetName);

      // Bersihkan baris data lama tapi pertahankan Header Row & Struktur
      var hdr = findHeaderRow(newSheet);
      var headerRowIndex = hdr ? hdr.rowIndex : 1;
      var lastRow = newSheet.getLastRow();
      if (lastRow > headerRowIndex) {
        newSheet.getRange(headerRowIndex + 1, 1, lastRow - headerRowIndex, newSheet.getLastColumn()).clearContent();
      }

      Logger.log("Auto-created missing month sheet: " + targetName);
      return newSheet;
    }
  } catch (e) {
    Logger.log("Error creating sheet " + targetName + ": " + e.toString());
  }

  return null;
}

/**
 * Tarik Data Warna FU (Two-Way Sync: Google Sheets → TRACKO Sistem)
 * Membaca background color kolom RESI dan baris data untuk mendeteksi:
 * - BIRU_TUA: FU POS (Eskalasi KC/KCU) - Khusus sel Resi berwarna biru tua/navy
 * - KUNING: Sudah di FU (1x) - Seluruh baris atau sel resi berwarna kuning
 * - HIJAU: FU 2 Kali - Seluruh baris atau sel resi berwarna hijau
 * - BIRU: Delivered / Sukses - Seluruh baris berwarna biru muda/toska
 * - ORANGE: Retur / Gagal Serah - Seluruh baris berwarna merah/orange
 * - PUTIH: Belum di FU / In Process - Seluruh baris putih/tanpa warna
 */
function handlePullColors(data) {
  var ss = getSpreadsheet(data);
  var sheetsToScan = [];
  var hintSheetName = String(data.sheet || data.sheet_name || '').trim();

  if (hintSheetName && hintSheetName.toUpperCase() !== 'ALL') {
    var matched = findSheetByName(ss, hintSheetName);
    if (matched) {
      sheetsToScan.push(matched);
    } else {
      return {
        status: 'success',
        total_found: 0,
        colors: [],
        message: 'Sheet [' + hintSheetName + '] tidak ditemukan.'
      };
    }
  }
  if (sheetsToScan.length === 0) {
    sheetsToScan = ss.getSheets();
  }

  var colorResults = [];

  for (var sIdx = 0; sIdx < sheetsToScan.length; sIdx++) {
    var sheet = sheetsToScan[sIdx];
    var hdr = findHeaderRow(sheet);
    if (!hdr) continue;

    var cResi = findCol(hdr, CONFIG.COL_RESI);
    if (!cResi) continue;

    var cFu = findCol(hdr, CONFIG.COL_STATUS_FU);

    var lastRow = sheet.getLastRow();
    var numRows = lastRow - hdr.rowIndex;
    if (numRows <= 0) continue;

    var resiVals = sheet.getRange(hdr.rowIndex + 1, cResi, numRows, 1).getValues();
    var resiBgs  = sheet.getRange(hdr.rowIndex + 1, cResi, numRows, 1).getBackgrounds();
    var fuVals   = cFu ? sheet.getRange(hdr.rowIndex + 1, cFu, numRows, 1).getValues() : null;
    var fuBgs    = cFu ? sheet.getRange(hdr.rowIndex + 1, cFu, numRows, 1).getBackgrounds() : null;

    for (var i = 0; i < numRows; i++) {
      var rVal = String(resiVals[i][0] || '').trim().toUpperCase();
      if (!rVal || rVal.length < 8) continue;

      var resiHex = String(resiBgs[i][0] || '#ffffff').trim().toLowerCase();
      var fuHex   = fuBgs ? String(fuBgs[i][0] || '#ffffff').trim().toLowerCase() : '#ffffff';
      var fuText  = fuVals ? String(fuVals[i][0] || '').trim().toUpperCase() : '';

      var colorCode = classifyRowColor(resiHex, fuHex, fuText);
      if (colorCode !== 'PUTIH' || data.include_white) {
        colorResults.push({
          resi: rVal,
          color: colorCode
        });
      }
    }
  }

  return {
    status: 'success',
    total_found: colorResults.length,
    colors: colorResults
  };
}

function classifyRowColor(resiHex, fuHex, fuText) {
  // 1. Cek teks eksplisit pada kolom FU jika ada
  if (fuText) {
    if (fuText.indexOf('ESKALASI') !== -1 || fuText.indexOf('FU POS') !== -1 || fuText.indexOf('FUPOS') !== -1) return 'BIRU_TUA';
    if (fuText.indexOf('SUDAH FU') !== -1 || fuText.indexOf('SUDAH DI FU') !== -1 || fuText.indexOf('FU 1') !== -1 || fuText.indexOf('FU1') !== -1 || fuText === 'KUNING') return 'KUNING';
    if (fuText.indexOf('DELIVERED') !== -1 || fuText.indexOf('SUKSES') !== -1) return 'BIRU';
    if (fuText.indexOf('RETUR') !== -1 || fuText.indexOf('RETURN') !== -1) return 'ORANGE';
    if (fuText.indexOf('BELUM') !== -1 || fuText === 'PUTIH') return 'PUTIH';
  }

  // 2. KHUSUS MITRA ALIQA: HANYA WARNA PADA SEL RESI
  // - Biru Tua / Navy / Blue -> ON FU POS (Eskalasi KC/KCU)
  if (isBlue(resiHex) || isBlue(fuHex)) {
    return 'BIRU_TUA';
  }

  // - Kuning -> SUDAH DI FU
  if (isYellow(resiHex) || isYellow(fuHex)) {
    return 'KUNING';
  }

  // - Merah / Orange -> RETUR
  if (isRedOrOrange(resiHex) || isRedOrOrange(fuHex)) {
    return 'ORANGE';
  }

  // - Cyan / Toska -> PAKET SUKSES (DELIVERED)
  if (isCyan(resiHex) || isCyan(fuHex)) {
    return 'BIRU';
  }

  // Tidak ada warna / Putih -> BELUM DI FU (BLM DI FU)
  // (CATATAN: Di Mitra Aliqa TIDAK ADA format FU 2 Kali / Hijau)
  return 'PUTIH';
}

function hexToRgb(hex) {
  if (!hex || hex === '#ffffff' || hex === 'white') return null;
  hex = hex.replace('#', '');
  if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
  if (hex.length !== 6) return null;
  var num = parseInt(hex, 16);
  return { r: (num >> 16) & 255, g: (num >> 8) & 255, b: num & 255 };
}

function isBlue(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return false;
  // Deteksi warna biru (navy, dark blue, royal blue, google blue, dsb):
  // Komponen Blue dominan di atas Green & Red, dan minimal bernilai 75
  return rgb.b > rgb.g && rgb.b > (rgb.r * 1.15) && rgb.b > 75;
}

function isDarkBlue(hex) {
  return isBlue(hex);
}

function isYellow(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return false;
  // Yellow / Pastel Yellow: red & green tinggi, blue jauh lebih rendah
  // Contoh: #ffff00, #fff2cc, #ffe599, #ffd966, #f1c232
  return rgb.r > 180 && rgb.g > 160 && (rgb.r - rgb.b) > 25 && (rgb.g - rgb.b) > 15;
}

function isGreen(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return false;
  // Green / Pastel Green: green dominan di atas red & blue
  // Contoh: #93c47d, #d9ead3, #6aa84f, #38761d
  return rgb.g > rgb.r && rgb.g > rgb.b && rgb.g > 100 && (rgb.g - rgb.r) > 10;
}

function isCyan(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return false;
  // Cyan/Toska: green & blue tinggi, red rendah
  // Contoh: #40e4b4, #46bdc6, #32b8c8
  return rgb.g > 140 && rgb.b > 140 && rgb.g >= rgb.b && rgb.r < 130;
}

function isRedOrOrange(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return false;
  // Red/Orange: red dominan
  // Contoh: #ff0000, #ff9900, #fbbc04, #f6b26b, #ea9999
  return (rgb.r > 190 && rgb.b < 140 && (rgb.r - rgb.g) > 15) || (rgb.r > 200 && rgb.b < 160 && rgb.g < 170);
}
