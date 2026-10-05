/**
 * ============================================================
 * TRACKO SYSTEM - Google Apps Script Webhook (KHUSUS MITRA ZAHERBA)
 * ============================================================
 * Fitur:
 * - Warna Otomatis Sesuai Standar Mitra Zaherba:
 *   1. BIRU TOSKA (#46BDC6)  -> DELIVERED / SUKSES (Kolom C s/d J)
 *   2. ORANGE (#FF9900)      -> RETUR / GAGAL SERAH (Kolom C s/d J)
 *   3. KUNING (#FFFF00)      -> SUDAH DI FU / CS 1X (Kolom C s/d J)
 *   4. HIJAU (#93C47D)       -> FU 2 KALI (Kolom C s/d J)
 *   5. BIRU TUA (#1F4E79)    -> FU POS (KHUSUS HANYA SEL RESI + FONT PUTIH)
 *   6. PUTIH (#FFFFFF)       -> BLM DI FU / IN PROCESS (Reset)
 * - Super Cepat (Bulk 2D Array Processing, tanpa timeout)
 * - Menu Ekstensi "📦 Posindo Tools (Zaherba)" di toolbar Google Sheets
 * - Mendukung Two-Way Sync (Web TRACKO ↔ Google Sheets Zaherba)
 * ============================================================
 * CARA PASANG DI SPREADSHEET MITRA ZAHERBA:
 * 1. Buka Google Spreadsheet Mitra Zaherba.
 * 2. Klik menu "Extensions" (Ekstensi) → "Apps Script".
 * 3. Hapus seluruh kode yang ada di editor, lalu paste seluruh isi file ini.
 * 4. Klik ikon "Save" (Ctrl + S).
 * 5. Klik tombol biru "Deploy" (Terapkan) di pojok kanan atas → "New deployment" (Penerapan baru).
 *    - Pilih type: "Web app" (ikon bola dunia).
 *    - Description: "Webhook Zaherba v1.0".
 *    - Execute as: "Me" (email Anda).
 *    - Who has access: "Anyone" (Siapa saja, penting agar web TRACKO bisa mengirim update).
 * 6. Klik "Deploy" → Berikan izin (Authorize access jika diminta).
 * 7. Salin "Web app URL" (akhiran /exec).
 * 8. Masukkan Web app URL tersebut ke modal Settings (ikon ⚙️) di web TRACKO!
 * ============================================================
 */

// KONFIGURASI WARNA KHUSUS MITRA ZAHERBA
var ZAHERBA_COLORS = {
  BIRU:     '#46BDC6',  // Paket Sukses (#46bdc6)
  ORANGE:   '#FBBC04',  // Paket Retur (#fbbc04)
  KUNING:   '#FFFF00',  // Sudah di FU (#ffff00)
  HIJAU:    '#93C47D',  // FU 2 Kali (#93c47d)
  BIRU_TUA: '#1C4587',  // FU POS (#1c4587)
  PUTIH:    '#FFFFFF'   // Belum di FU (#ffffff)
};

var CONFIG = {
  COL_RESI:       ["RESI", "NO RESI", "BARCODE", "BARCODE ITEM", "NOMOR RESI", "AWB"],
  COL_STATUS_POS: ["TRACKING POS", "STATUS POS", "STATUS NIPOS", "STATUS AKHIR", "STATUS", "TRACKING"],
  COL_KETERANGAN: ["KETERANGAN", "POS KETERANGAN", "PENERIMA & KETERANGAN", "KETERANGAN K", "ALASAN"],
  COL_SLA:        ["SLA (MASA TAHAN)", "SLA", "SLA DAYS", "MASA TAHAN"],
  COL_STATUS_FU:  ["STATUS FU", "FU", "FOLLOW UP", "WARNA", "STATUS CS"],
  COL_TANGGAL_FU: ["TANGGAL FU", "TGL FU", "WAKTU FU", "TGL FOLLOW UP"]
};

/**
 * 1. MENU EKSTENSI SPREADSHEET (Muncul otomatis saat spreadsheet dibuka)
 */
function onOpen() {
  var ui = SpreadsheetApp.getUi();
  ui.createMenu('📦 Posindo Tools (Zaherba)')
    .addItem('Warnai Baris: BIRU TOSKA (Sukses)', 'colorSelectedBiru')
    .addItem('Warnai Baris: ORANGE (Retur)', 'colorSelectedOrange')
    .addItem('Warnai Baris: KUNING (Sudah FU)', 'colorSelectedKuning')
    .addItem('Warnai Baris: HIJAU (FU 2x)', 'colorSelectedHijau')
    .addItem('Warnai Resi: BIRU TUA (FU POS)', 'colorSelectedBiruTua')
    .addSeparator()
    .addItem('Reset Baris: PUTIH (Blm FU)', 'colorSelectedPutih')
    .addToUi();
}

/**
 * Handler Tombol Menu Ekstensi
 */
function colorSelectedBiru()    { applyColorToSelection('BIRU'); }
function colorSelectedOrange()  { applyColorToSelection('ORANGE'); }
function colorSelectedKuning()  { applyColorToSelection('KUNING'); }
function colorSelectedHijau()   { applyColorToSelection('HIJAU'); }
function colorSelectedBiruTua() { applyColorToSelection('BIRU_TUA'); }
function colorSelectedPutih()   { applyColorToSelection('PUTIH'); }

function applyColorToSelection(colorType) {
  var sheet = SpreadsheetApp.getActiveSheet();
  var range = sheet.getActiveRange();
  if (!range) return;

  var startRow = range.getRow();
  var numRows = range.getNumRows();
  var hdr = findHeaderRow(sheet);
  var cResi = hdr ? findCol(hdr, CONFIG.COL_RESI) : 5; // Default Kolom E
  if (!cResi) cResi = 5;

  var hex = ZAHERBA_COLORS[colorType] || '#FFFFFF';
  var fg = (colorType === 'BIRU_TUA') ? '#FFFFFF' : '#000000';

  for (var i = 0; i < numRows; i++) {
    var r = startRow + i;
    if (r <= (hdr ? hdr.rowIndex : 2)) continue; // Lewati header

    // HANYA kolom Resi yang diwarnai (Kolom selain RESI tidak berubah warna)
    sheet.getRange(r, cResi).setBackground(hex).setFontColor(fg);
  }
}

/**
 * 2. ENDPOINT GET (Pemeriksaan status koneksi Webhook)
 */
function doGet(e) {
  return ContentService.createTextOutput(JSON.stringify({
    status: 'online',
    seller: 'Mitra Zaherba',
    version: '1.0',
    message: 'Webhook Google Sheets Zaherba aktif & terhubung dengan TRACKO.'
  })).setMimeType(ContentService.MimeType.JSON);
}

/**
 * 3. ENDPOINT POST (Menerima update dari TRACKO Web)
 */
function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return ContentService.createTextOutput(JSON.stringify({ status: 'error', message: 'No POST data' }))
        .setMimeType(ContentService.MimeType.JSON);
    }

    var data = JSON.parse(e.postData.contents);
    var action = data.action || 'update_status';
    Logger.log("Webhook Zaherba: action=" + action);

    var result = { status: 'success', updated_count: 0 };

    if (action === 'reverse_sync_nipos') {
      result = handleNiposUpdate(data);
    } else if (action === 'update_fu_status' || action === 'update_status') {
      result = handleFuStatusUpdate(data);
    } else if (action === 'pull_sheet_colors' || action === 'sync_colors' || action === 'get_colors') {
      result = handlePullColors(data);
    } else if (action === 'apply_filter' || action === 'filter') {
      result = { status: 'success', message: 'Filter received' };
    }

    return ContentService.createTextOutput(JSON.stringify(result))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    Logger.log("Webhook error: " + err.toString());
    return ContentService.createTextOutput(JSON.stringify({ status: 'error', message: err.toString() }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

/**
 * Update Bulk Status Bot Tracking NIPOS
 * ATURAN KERAS: Bot NIPOS TIDAK BOLEH merubah apa pun di Spreadsheet (warna, keterangan, SLA tetap utuh)!
 * Update NIPOS hanya dilakukan di Web Tracko.
 */
function handleNiposUpdate(data) {
  return {
    status: 'success',
    seller: 'Mitra Zaherba',
    updated_count: 0,
    message: 'Bot NIPOS hanya dilakukan di Web Tracko. Spreadsheet tidak disentuh sama sekali.'
  };
}

/**
 * Update Status Follow Up CS dari Dashboard Modal / Button
 * ATURAN KERAS: Spreadsheet CUMA BERUBAH KETIKA ADA YG PUSH WARNA FU POS (BIRU_TUA)!
 */
function handleFuStatusUpdate(data) {
  var resiList = data.resis || data.resi_list || [];
  var fuType   = (data.fu_type || data.color_code || 'PUTIH').toUpperCase();
  var hex      = ZAHERBA_COLORS[fuType] || '#FFFFFF';
  var fg       = (fuType === 'BIRU_TUA') ? '#FFFFFF' : '#000000';

  // JIKA BUKAN BIRU_TUA (FU POS), JANGAN SENTUH SPREADSHEET SAMA SEKALI!
  if (fuType !== 'BIRU_TUA') {
    return {
      status: 'success',
      seller: 'Mitra Zaherba',
      updated_count: 0,
      message: 'Spreadsheet hanya boleh berubah saat push FU POS (Biru Tua). Aksi selain FU POS diabaikan.'
    };
  }

  var resiSet = {};
  resiList.forEach(function(r) {
    if (r) resiSet[String(r).trim().toUpperCase()] = true;
  });

  if (Object.keys(resiSet).length === 0) {
    return { status: 'success', updated_count: 0, message: 'Tidak ada resi yang diberikan.' };
  }

  var ss = getSpreadsheet(data);
  var allSheets = ss.getSheets();
  var sheetsToScan = [];

  var hintSheet = String(data.sheet || data.sheet_name || '').trim();
  if (hintSheet) {
    var matched = findSheetByName(ss, hintSheet);
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

  for (var sIdx = 0; sIdx < sheetsToScan.length; sIdx++) {
    if (Object.keys(resiSet).length === 0) break;

    var sheet = sheetsToScan[sIdx];
    var hdr = findHeaderRow(sheet);
    if (!hdr) continue;

    var cResi = findCol(hdr, CONFIG.COL_RESI) || 5;
    var lastRow = sheet.getLastRow();
    var numRows = lastRow - hdr.rowIndex;
    if (numRows <= 0) continue;

    var resiVals = sheet.getRange(hdr.rowIndex + 1, cResi, numRows, 1).getValues();

    for (var i = 0; i < numRows; i++) {
      var rVal = String(resiVals[i][0] || '').trim().toUpperCase();
      if (rVal && resiSet[rVal]) {
        var rowNum = hdr.rowIndex + 1 + i;

        // Pewarnaan di Spreadsheet oleh Admin HANYA untuk status FU POS (BIRU_TUA)!
        // Warna Retur dan Sukses adalah hak/wewenang Seller di Spreadsheet.
        if (fuType === 'BIRU_TUA') {
          sheet.getRange(rowNum, cResi).setBackground(hex).setFontColor(fg);
        }

        delete resiSet[rVal];
        totalUpdated++;
      }
    }
  }

  return {
    status: 'success',
    seller: 'Mitra Zaherba',
    updated_count: totalUpdated,
    message: 'Berhasil mengupdate status warna Zaherba untuk ' + totalUpdated + ' resi.'
  };
}

/**
 * Mencari baris Header otomatis di 5 baris pertama
 */
function findHeaderRow(sheet) {
  var maxSearch = Math.min(5, sheet.getLastRow());
  for (var r = 1; r <= maxSearch; r++) {
    var row = sheet.getRange(r, 1, 1, sheet.getLastColumn()).getValues()[0];
    var upper = row.map(function(v) { return String(v).toUpperCase().trim(); });
    for (var c = 0; c < upper.length; c++) {
      if (CONFIG.COL_RESI.some(function(k) { return upper[c].indexOf(k) !== -1; })) {
        return { rowIndex: r, headers: upper };
      }
    }
  }
  // Fallback khusus Mitra Zaherba jika tidak ada baris label "No Resi":
  // Baris 1 adalah judul Form, Kolom E (index 5) adalah kolom No Resi
  return { rowIndex: 1, headers: [] };
}

/**
 * Mencari nomor kolom (1-indexed) berdasarkan keyword
 */
function findCol(hdr, keywords) {
  if (!hdr || !hdr.headers || !hdr.headers.length) return null;
  for (var c = 0; c < hdr.headers.length; c++) {
    var h = hdr.headers[c];
    if (keywords.some(function(k) { return h === k || h.indexOf(k) !== -1; })) {
      return c + 1;
    }
  }
  return null;
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

    var cResi = findCol(hdr, CONFIG.COL_RESI) || 5;
    var cFu = findCol(hdr, CONFIG.COL_STATUS_FU);

    var lastRow = sheet.getLastRow();
    var numRows = lastRow - hdr.rowIndex;
    if (numRows <= 0) continue;

    var maxCols = Math.min(12, sheet.getLastColumn());
    var resiVals = sheet.getRange(hdr.rowIndex + 1, cResi, numRows, 1).getValues();
    var allBgs   = sheet.getRange(hdr.rowIndex + 1, 1, numRows, maxCols).getBackgrounds();
    var fuVals   = cFu ? sheet.getRange(hdr.rowIndex + 1, cFu, numRows, 1).getValues() : null;

    for (var i = 0; i < numRows; i++) {
      var rVal = String(resiVals[i][0] || '').trim().toUpperCase();
      if (!rVal || rVal.length < 8) continue;

      var resiHex = String(allBgs[i][cResi - 1] || '#ffffff').trim().toLowerCase();
      var fuHex   = cFu ? String(allBgs[i][cFu - 1] || '#ffffff').trim().toLowerCase() : '#ffffff';
      var rowHexes = allBgs[i];
      var fuText  = fuVals ? String(fuVals[i][0] || '').trim().toUpperCase() : '';

      var colorCode = classifyRowColor(resiHex, fuHex, rowHexes, fuText);
      if (colorCode && colorCode !== 'PUTIH') {
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

function classifyRowColor(resiHex, fuHex, rowHexes, fuText) {
  // 1. Cek teks eksplisit pada kolom FU jika ada
  if (fuText) {
    if (fuText.indexOf('ESKALASI') !== -1 || fuText.indexOf('FU POS') !== -1 || fuText.indexOf('FUPOS') !== -1) return 'BIRU_TUA';
    if (fuText.indexOf('SUDAH FU') !== -1 || fuText.indexOf('SUDAH DI FU') !== -1 || fuText.indexOf('FU 1') !== -1 || fuText.indexOf('FU1') !== -1 || fuText === 'KUNING') return 'KUNING';
    if (fuText.indexOf('FU 2') !== -1 || fuText.indexOf('FU2') !== -1 || fuText.indexOf('2 KALI') !== -1 || fuText.indexOf('2X') !== -1 || fuText === 'HIJAU') return 'HIJAU';
    if (fuText.indexOf('DELIVERED') !== -1 || fuText.indexOf('SUKSES') !== -1) return 'BIRU';
    if (fuText.indexOf('RETUR') !== -1 || fuText.indexOf('RETURN') !== -1) return 'ORANGE';
    if (fuText.indexOf('BELUM') !== -1 || fuText === 'PUTIH') return 'PUTIH';
  }

  // 2. KHUSUS FU POS ZAHERBA: Hanya sel Resi (atau FU) yang berwarna Biru Tua Gelap (#1C4587)
  if (isDarkBlue(resiHex) || isDarkBlue(fuHex)) {
    return 'BIRU_TUA';
  }

  // 3. Scan warna pada seluruh sel baris (kolom C s/d J)
  var hasCyan = false;     // #46BDC6 (Paket Sukses)
  var hasRed = false;      // #FBBC04 (Paket Retur)
  var hasYellow = false;   // #FFFF00 (Sudah di FU)
  var hasGreen = false;    // #93C47D (FU 2 Kali)
  var hasDarkBlue = false; // #1C4587 (FU POS)

  if (rowHexes && rowHexes.length) {
    for (var c = 0; c < rowHexes.length; c++) {
      var h = String(rowHexes[c] || '').trim().toLowerCase();
      if (!h || h === '#ffffff' || h === 'white' || h === '#f0f0f0') continue;

      if (isCyan(h)) {
        hasCyan = true;
      } else if (isDarkBlue(h)) {
        hasDarkBlue = true;
      } else if (isRedOrOrange(h)) {
        hasRed = true;
      } else if (isYellow(h)) {
        hasYellow = true;
      } else if (isGreen(h)) {
        hasGreen = true;
      }
    }
  }

  // Prioritas Penentuan Warna Sesuai Standar Zaherba:
  // 1. Biru Tua Gelap (#1C4587) -> FU POS
  if (hasDarkBlue || isDarkBlue(resiHex)) return 'BIRU_TUA';
  // 2. Biru Toska (#46BDC6) -> Paket Sukses
  if (hasCyan) return 'BIRU';
  // 3. Orange/Amber (#FBBC04) -> Paket Retur
  if (hasRed) return 'ORANGE';
  // 4. Kuning Terang (#FFFF00) -> Sudah di FU
  if (hasYellow) return 'KUNING';
  // 5. Hijau Muda (#93C47D) -> FU 2 Kali
  if (hasGreen) return 'HIJAU';

  return 'PUTIH';
}

function hexToRgb(hex) {
  if (!hex || hex === '#ffffff' || hex === 'white' || hex === '#f0f0f0') return null;
  hex = hex.replace('#', '');
  if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
  if (hex.length !== 6) return null;
  var num = parseInt(hex, 16);
  return { r: (num >> 16) & 255, g: (num >> 8) & 255, b: num & 255 };
}

// 1. FU POS Zaherba: #1C4587 (r=28, g=69, b=135) - Biru Tua Gelap
function isDarkBlue(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return false;
  return rgb.b > 85 && rgb.b > (rgb.r * 1.5) && rgb.b > (rgb.g * 1.1) && rgb.r < 75 && rgb.g < 115;
}

function isBlue(hex) {
  return isDarkBlue(hex);
}

// 2. Paket Sukses Zaherba: #46BDC6 (r=70, g=189, b=198) - Biru Toska / Cyan Terang
function isCyan(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return false;
  return rgb.b > 140 && rgb.g > 140 && rgb.r < 120;
}

// 3. Paket Retur Zaherba: #FBBC04 (r=251, g=188, b=4) - Amber / Kuning Emas / Orange
function isRedOrOrange(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return false;
  return rgb.r > 190 && rgb.b < 70 && (rgb.r - rgb.g) >= 30;
}

// 4. Sudah di FU Zaherba: #FFFF00 (r=255, g=255, b=0) - Kuning Terang Murni
function isYellow(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return false;
  return rgb.r > 190 && rgb.g > 190 && rgb.b < 70 && Math.abs(rgb.r - rgb.g) < 25;
}

// 5. FU 2 Kali Zaherba: #93C47D (r=147, g=196, b=125) - Hijau Muda Lembut
function isGreen(hex) {
  var rgb = hexToRgb(hex);
  if (!rgb) return false;
  return rgb.g > 140 && rgb.g > (rgb.r + 20) && rgb.g > (rgb.b + 20);
}
