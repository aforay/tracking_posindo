/**
 * POSINDO TRACKING TWO-WAY SYNC GOOGLE APPS SCRIPT WEBHOOK
 * 
 * Petunjuk Pemasangan:
 * 1. Buka Google Spreadsheet Anda.
 * 2. Klik menu "Extensions" (Ekstensi) -> "Apps Script".
 * 3. Hapus seluruh kode bawaan, lalu Tempelkan (Paste) kode di bawah ini.
 * 4. Klik tombol "Deploy" (Terapkan) -> "New deployment" (Terapkan baru).
 * 5. Pilih tipe: "Web app".
 * 6. Execute as: "Me" (Email Anda).
 * 7. Who has access: "Anyone" (Siapa saja).
 * 8. Klik "Deploy", lalu Berikan Izin (Grant Access).
 * 9. Salin "Web App URL" yang dihasilkan.
 * 10. Tempelkan URL tersebut di file `.env` Laravel:
 *     GOOGLE_SHEET_WEBHOOK_URL="https://script.google.com/macros/s/..."
 *     atau melalui Modal "Sync Google Sheets" di UI Admin.
 */

// Color Palette Definition matching Pos Indonesia Specification
const COLOR_HEX_MAP = {
  'BIRU': '#93C5FD',      // Light Blue (Sukses / Delivered)
  'ORANGE': '#FDBA74',    // Orange (Retur / Return)
  'KUNING': '#FEF08A',    // Yellow (Sudah di FU / Follow-Up)
  'HIJAU': '#86EFAC',     // Green (FU 2 Kali)
  'BIRU_TUA': '#60A5FA',  // Dark Blue (FU Pos)
  'PUTIH': '#FFFFFF'      // White (On Process / In Transit)
};

function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return ContentService.createTextOutput(JSON.stringify({
        status: 'error',
        message: 'No POST data received'
      })).setMimeType(ContentService.MimeType.JSON);
    }

    const data = JSON.parse(e.postData.contents);
    const resiList = data.resis || data.resi_list || (data.no_resi ? [data.no_resi] : []);
    const statusColor = (data.status_color || data.color_code || 'PUTIH').toUpperCase();
    const statusLabel = data.status_label || statusColor;
    const note = data.note || '';
    const escalationDate = data.escalation_date || '';

    if (resiList.length === 0) {
      return ContentService.createTextOutput(JSON.stringify({
        status: 'error',
        message: 'No resi provided in resis/resi_list array'
      })).setMimeType(ContentService.MimeType.JSON);
    }

    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const sheets = ss.getSheets();
    const hexColor = COLOR_HEX_MAP[statusColor] || '#FFFFFF';

    let updatedCount = 0;
    const updatedResis = [];

    // Convert resi list to uppercase string set for fast matching
    const targetResiMap = {};
    resiList.forEach(function(r) {
      if (r) {
        targetResiMap[String(r).trim().toUpperCase()] = true;
      }
    });

    // Iterate across all sheets (Januari - Agustus, Master, etc.)
    sheets.forEach(function(sheet) {
      const dataRange = sheet.getDataRange();
      const values = dataRange.getValues();

      if (values.length < 2) return;

      // Find column indexes dynamically from header row
      const headers = values[0].map(function(h) {
        return String(h).trim().toLowerCase().replace(/[^a-z0-9]/g, '');
      });
      let resiCol = -1;
      let trackingPosCol = -1;
      let keteranganCol = -1;
      let fuPosDateCol = -1;

      headers.forEach(function(h, idx) {
        if (['resi', 'noresi', 'barcode', 'awb', 'barcodeitem'].indexOf(h) !== -1 && resiCol === -1) {
          resiCol = idx;
        } else if (['trackingpos', 'statuspos', 'status', 'nipos'].indexOf(h) !== -1 && trackingPosCol === -1) {
          trackingPosCol = idx;
        } else if (['keterangan', 'note', 'alasan', 'penerimaketerangank'].indexOf(h) !== -1 && keteranganCol === -1) {
          keteranganCol = idx;
        } else if (['fubycs', 'fuposdate', 'tglfu', 'escalationdate'].indexOf(h) !== -1 && fuPosDateCol === -1) {
          fuPosDateCol = idx;
        }
      });

      // Default fallback indexes if header not found
      if (resiCol === -1) resiCol = 4; // Column E
      if (trackingPosCol === -1) trackingPosCol = 11; // Column L
      if (keteranganCol === -1) keteranganCol = 10; // Column K

      // Scan rows in this sheet
      for (let r = 1; r < values.length; r++) {
        const cellResi = String(values[r][resiCol] || '').trim().toUpperCase();
        if (cellResi && targetResiMap[cellResi]) {
          const rowNumber = r + 1; // 1-indexed row number in Google Sheets
          const rangeToColor = sheet.getRange(rowNumber, 1, 1, values[r].length);

          // 1. Update Background Color of Row
          rangeToColor.setBackground(hexColor);

          // 2. Update Status Text in trackingPosCol
          if (trackingPosCol >= 0) {
            sheet.getRange(rowNumber, trackingPosCol + 1).setValue(statusLabel);
          }

          // 3. Update Note in keteranganCol if note provided
          if (keteranganCol >= 0 && note) {
            sheet.getRange(rowNumber, keteranganCol + 1).setValue(note);
          }

          // 4. Update Escalation Date if provided
          if (fuPosDateCol >= 0 && escalationDate) {
            sheet.getRange(rowNumber, fuPosDateCol + 1).setValue(escalationDate);
          }

          updatedCount++;
          updatedResis.push(cellResi);
        }
      }
    });

    return ContentService.createTextOutput(JSON.stringify({
      status: 'success',
      message: `Successfully updated ${updatedCount} resis to ${statusColor}`,
      updated_count: updatedCount,
      updated_resis: updatedResis,
      color_code: statusColor,
      hex_color: hexColor
    })).setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({
      status: 'error',
      message: err.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

function doGet(e) {
  return ContentService.createTextOutput(JSON.stringify({
    status: 'online',
    message: 'Posindo Two-Way Sync Apps Script Webhook Endpoint is Running active!'
  })).setMimeType(ContentService.MimeType.JSON);
}
