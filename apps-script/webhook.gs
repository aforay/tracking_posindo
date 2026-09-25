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
 * Handle Reverse Sync NIPos Tracking & Status (Bulk 2D Array)
 */
function handleNiposUpdate(data) {
  var items = data.items || [];
  if (!items.length) return { status: 'success', updated_count: 0, message: 'No items to update' };

  var resiMap = {};
  items.forEach(function(it) {
    if (it && it.resi) {
      resiMap[String(it.resi).trim().toUpperCase()] = it;
    }
  });

  var ss = getSpreadsheet(data);
  var allSheets = ss.getSheets();
  var sheetsToScan = [];

  // Prioritaskan sheet yang cocok dengan nama sheet di payload
  var hintSheetName = String(data.sheet || data.sheet_name || (items[0] && (items[0].sheet || items[0].sheet_name)) || '').trim();
  if (hintSheetName) {
    var matched = findSheetByName(ss, hintSheetName);
    if (matched) {
      sheetsToScan.push(matched);
    }
  }

  // Tambahkan seluruh sheet lainnya sebagai fallback jika resi belum ditemukan
  var scannedIds = {};
  sheetsToScan.forEach(function(sh) { scannedIds[sh.getSheetId()] = true; });
  allSheets.forEach(function(sh) {
    if (!scannedIds[sh.getSheetId()]) {
      sheetsToScan.push(sh);
    }
  });

  var totalUpdated = 0;

  for (var sIdx = 0; sIdx < sheetsToScan.length; sIdx++) {
    if (Object.keys(resiMap).length === 0) break; // Semua resi dalam payload sudah terupdate

    var sheet = sheetsToScan[sIdx];
    var hdr = findHeaderRow(sheet);
    if (!hdr) continue;

    var cResi = findCol(hdr, CONFIG.COL_RESI);
    if (!cResi) continue;

    var cPos = findCol(hdr, CONFIG.COL_STATUS_POS);
    var cKet = findCol(hdr, CONFIG.COL_KETERANGAN);
    var cSla = findCol(hdr, CONFIG.COL_SLA);
    var cFu  = findCol(hdr, CONFIG.COL_STATUS_FU);

    var lastRow = sheet.getLastRow();
    var lastCol = sheet.getLastColumn();
    var numRows = lastRow - hdr.rowIndex;
    if (numRows <= 0) continue;

    // 1. Baca HANYA 1 kolom (Kolom Resi) -> super cepat (<50ms)
    var resiVals = sheet.getRange(hdr.rowIndex + 1, cResi, numRows, 1).getValues();
    var matchedRows = [];
    var rowResiMap = {};

    for (var i = 0; i < numRows; i++) {
      var rVal = String(resiVals[i][0] || '').trim().toUpperCase();
      if (rVal && resiMap[rVal]) {
        var actualRow = hdr.rowIndex + 1 + i;
        matchedRows.push(actualRow);
        rowResiMap[actualRow] = rVal;
      }
    }

    if (matchedRows.length === 0) continue;

    // 2. Kelompokkan baris ke dalam cluster (jarak antar baris <= 100)
    var clusters = [];
    var currCluster = [];
    for (var m = 0; m < matchedRows.length; m++) {
      var rNum = matchedRows[m];
      if (currCluster.length === 0) {
        currCluster.push(rNum);
      } else {
        var lastR = currCluster[currCluster.length - 1];
        if (rNum - lastR <= 100) {
          currCluster.push(rNum);
        } else {
          clusters.push(currCluster);
          currCluster = [rNum];
        }
      }
    }
    if (currCluster.length > 0) clusters.push(currCluster);

    var maxCols = Math.min(18, lastCol);

    // 3. Update setiap cluster secara Bulk 2D Array
    for (var cl = 0; cl < clusters.length; cl++) {
      var cluster = clusters[cl];
      var cStart = cluster[0];
      var cEnd = cluster[cluster.length - 1];
      var cSpan = cEnd - cStart + 1;

      var range = sheet.getRange(cStart, 1, cSpan, maxCols);
      var vals = range.getValues();
      var bgs = range.getBackgrounds();
      var fgs = range.getFontColors();

      for (var k = 0; k < cluster.length; k++) {
        var actualRow = cluster[k];
        var rIdx = actualRow - cStart;
        var resi = rowResiMap[actualRow];
        var item = resiMap[resi];
        if (!item) continue;

        var cc = (item.color_code || 'PUTIH').toUpperCase();
        var bg = getFuBg(cc);
        var fg = getFuFg(cc);

        // 1. Status POS
        if (cPos && item.status_pos) {
          vals[rIdx][cPos - 1] = item.status_pos;
          bgs[rIdx][cPos - 1] = bg;
          fgs[rIdx][cPos - 1] = fg;
        }

        // 2. Keterangan
        if (cKet && item.keterangan) {
          vals[rIdx][cKet - 1] = item.keterangan;
        }

        // 3. SLA
        if (cSla && item.sla) {
          vals[rIdx][cSla - 1] = item.sla;
        }

        // 4. Status FU
        if (cFu) {
          var label = item.status_label || (
            cc === 'BIRU' ? 'DELIVERED (SUKSES)' :
            (cc === 'ORANGE' ? 'RETUR (RETURN)' :
            (cc === 'KUNING' ? 'FOLLOW UP' :
            (cc === 'HIJAU' ? 'SUDAH DIHUBUNGI' :
            (cc === 'BIRU_TUA' ? 'ESKALASI POS' : 'IN PROSES'))))
          );
          vals[rIdx][cFu - 1] = label;
          bgs[rIdx][cFu - 1] = bg;
          fgs[rIdx][cFu - 1] = fg;
        }

        // 5. Background Baris Data (Kolom A s/d Kolom R)
        if (cc === 'BIRU_TUA') {
          // KHUSUS FU POS: Hanya sel RESI yang berubah warna (biru tua + font putih)
          if (cResi) {
            bgs[rIdx][cResi - 1] = bg;
            fgs[rIdx][cResi - 1] = fg;
          }
        } else {
          // Status selain FU POS: Seluruh baris data diwarnai
          for (var colIdx = 0; colIdx < maxCols; colIdx++) {
            bgs[rIdx][colIdx] = bg;
          }
        }

        // 6. Tanggal FU / Eskalasi jika kolom tersedia di sheet
        var cTgl = findCol(hdr, CONFIG.COL_TANGGAL_FU);
        var fuTime = item.fu_timestamp || item.escalation_date || '';
        if (cTgl && fuTime) {
          vals[rIdx][cTgl - 1] = fmtDate(fuTime);
        }

        delete resiMap[resi];
        totalUpdated++;
      }

      // Tulis kembali sekaligus dengan Bulk Operations
      range.setValues(vals);
      range.setBackgrounds(bgs);
      range.setFontColors(fgs);
    }
  }

  return { status: 'success', updated_count: totalUpdated, message: 'Reverse sync berhasil mengupdate ' + totalUpdated + ' resi.' };
}

/**
 * Handle Follow Up Status Update dari CS Activity Log / Modal (Bulk 2D Array)
 */
function handleFuStatusUpdate(data) {
  var resiList  = data.resis || data.resi_list || [];
  var fuType    = (data.fu_type || data.color_code || 'PUTIH').toUpperCase();
  var fuLabel   = data.status_label || (
    fuType === 'BIRU' ? 'DELIVERED (SUKSES)' :
    (fuType === 'ORANGE' ? 'RETUR (RETURN)' :
    (fuType === 'KUNING' ? 'FOLLOW UP' :
    (fuType === 'HIJAU' ? 'SUDAH DIHUBUNGI' :
    (fuType === 'BIRU_TUA' ? 'ESKALASI POS' : 'IN PROSES'))))
  );
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
    var matchedRows = [];
    var rowResiMap = {};

    for (var i = 0; i < numRows; i++) {
      var rVal = String(resiVals[i][0] || '').trim().toUpperCase();
      if (rVal && resiSet[rVal]) {
        var actualRow = hdr.rowIndex + 1 + i;
        matchedRows.push(actualRow);
        rowResiMap[actualRow] = rVal;
      }
    }

    if (matchedRows.length === 0) continue;

    var clusters = [];
    var currCluster = [];
    for (var m = 0; m < matchedRows.length; m++) {
      var rNum = matchedRows[m];
      if (currCluster.length === 0) {
        currCluster.push(rNum);
      } else {
        var lastR = currCluster[currCluster.length - 1];
        if (rNum - lastR <= 100) {
          currCluster.push(rNum);
        } else {
          clusters.push(currCluster);
          currCluster = [rNum];
        }
      }
    }
    if (currCluster.length > 0) clusters.push(currCluster);

    var maxCols = Math.min(18, lastCol);

    for (var cl = 0; cl < clusters.length; cl++) {
      var cluster = clusters[cl];
      var cStart = cluster[0];
      var cEnd = cluster[cluster.length - 1];
      var cSpan = cEnd - cStart + 1;

      var range = sheet.getRange(cStart, 1, cSpan, maxCols);
      var vals = range.getValues();
      var bgs = range.getBackgrounds();
      var fgs = range.getFontColors();

      for (var k = 0; k < cluster.length; k++) {
        var actualRow = cluster[k];
        var rIdx = actualRow - cStart;
        var resi = rowResiMap[actualRow];

        if (cFu) {
          vals[rIdx][cFu - 1] = fuLabel;
          bgs[rIdx][cFu - 1] = bg;
          fgs[rIdx][cFu - 1] = fg;
        }
        if (cTgl && formattedDate) {
          vals[rIdx][cTgl - 1] = formattedDate;
        }

        if (fuType === 'BIRU_TUA') {
          // KHUSUS FU POS: Hanya sel RESI yang berubah warna (biru tua + font putih)
          if (cResi) {
            bgs[rIdx][cResi - 1] = bg;
            fgs[rIdx][cResi - 1] = fg;
          }
        } else {
          // Status selain FU POS: Seluruh baris data diwarnai
          for (var colIdx = 0; colIdx < maxCols; colIdx++) {
            bgs[rIdx][colIdx] = bg;
          }
        }

        delete resiSet[resi];
        totalUpdated++;
      }

      range.setValues(vals);
      range.setBackgrounds(bgs);
      range.setFontColors(fgs);
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
    if (matched) sheetsToScan.push(matched);
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

    var maxCols = Math.min(15, sheet.getLastColumn());
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
  }

  // 2. KHUSUS FU POS: Sel Resi atau Sel FU berwarna biru
  if (isBlue(resiHex) || isBlue(fuHex)) {
    return 'BIRU_TUA';
  }

  // 3. Cek warna di sepanjang baris (dan sel resi/FU)
  var hasYellow = isYellow(resiHex) || isYellow(fuHex);
  var hasGreen  = isGreen(resiHex) || isGreen(fuHex);
  var hasCyan   = isCyan(resiHex) || isCyan(fuHex);
  var hasRed    = isRedOrOrange(resiHex) || isRedOrOrange(fuHex);
  var hasBlue   = false;

  if (rowHexes && rowHexes.length) {
    for (var c = 0; c < rowHexes.length; c++) {
      var h = String(rowHexes[c] || '').trim().toLowerCase();
      if (!h || h === '#ffffff' || h === 'white') continue;

      if (isCyan(h)) {
        hasCyan = true;
      } else if (isBlue(h)) {
        hasBlue = true;
      } else if (isYellow(h)) {
        hasYellow = true;
      } else if (isGreen(h)) {
        hasGreen = true;
      } else if (isRedOrOrange(h)) {
        hasRed = true;
      }
    }
  }

  // Prioritas penentuan status FU:
  // 1. Biru (ON FU POS / Eskalasi KC)
  if (hasBlue) return 'BIRU_TUA';
  // 2. Kuning (SUDAH DI FU)
  if (hasYellow) return 'KUNING';
  // 3. Hijau (FU 2 Kali)
  if (hasGreen) return 'HIJAU';
  // 4. Toska / Cyan (Delivered)
  if (hasCyan) return 'BIRU';
  // 5. Merah / Orange (Retur)
  if (hasRed) return 'ORANGE';

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
