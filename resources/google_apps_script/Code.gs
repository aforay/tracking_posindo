/**
 * POSINDO TRACKING TWO-WAY SYNC GOOGLE APPS SCRIPT WEBHOOK & EXTENSION
 * 
 * Khusus Mewarnai Rentang Kolom C sampai Kolom J (Kolom 3 s/d 10).
 */

const COLOR_HEX_MAP = {
  'BIRU': '#32B8C8',      // Cyan-Teal (Paket Sukses)
  'ORANGE': '#FFB719',    // Orange/Amber (Paket Retur)
  'KUNING': '#FFFF00',    // Bright Yellow (Sudah di FU)
  'PUTIH': '#FFFFFF',     // White (Blm di FU / Reset)
  'HIJAU': '#93C47D',     // Soft Green (FU 2 Kali)
  'BIRU_TUA': '#1F4E79'   // Dark Navy Blue (FU POS)
};

/**
 * MENU EKSTENSI DI SPREADSHEET
 */
function onOpen() {
  const ui = SpreadsheetApp.getUi();
  ui.createMenu('📦 Posindo Tools')
    .addItem('Warnai Baris Terpilih - BIRU (Sukses)', 'colorSelectedBiru')
    .addItem('Warnai Baris Terpilih - ORANGE (Retur)', 'colorSelectedOrange')
    .addItem('Warnai Baris Terpilih - KUNING (FU 1)', 'colorSelectedKuning')
    .addItem('Warnai Baris Terpilih - HIJAU (FU 2)', 'colorSelectedHijau')
    .addItem('Warnai Baris Terpilih - BIRU TUA (FU POS)', 'colorSelectedBiruTua')
    .addSeparator()
    .addItem('Reset Warna (PUTIH)', 'colorSelectedPutih')
    .addToUi();
}

function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return ContentService.createTextOutput(JSON.stringify({
        status: 'error',
        message: 'No POST data received'
      })).setMimeType(ContentService.MimeType.JSON);
    }

    const data = JSON.parse(e.postData.contents);
    const action = data.action || 'update_status';
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const sheets = ss.getSheets();

    // ACTION 1: APPLY FILTER TO GOOGLE SPREADSHEET
    if (action === 'apply_filter' || action === 'filter') {
      const targetSeller = String(data.seller || 'ALL').trim().toUpperCase();
      const targetMonth = String(data.month || 'ALL').trim().toUpperCase();
      const targetColor = String(data.color_code || data.status_color || 'ALL').trim().toUpperCase();
      const targetSearch = String(data.search || '').trim().toUpperCase();

      const isReset = (targetSeller === 'ALL' || targetSeller === 'SEMUA SELLER') && targetColor === 'ALL' && !targetSearch;
      let totalFilteredRows = 0;

      sheets.forEach(function(sheet) {
        const lastRow = sheet.getLastRow();
        if (lastRow < 2) return;

        const sheetTitle = sheet.getName().toUpperCase();
        if (targetMonth !== 'ALL' && sheetTitle.indexOf(targetMonth) !== -1) {
          ss.setActiveSheet(sheet);
        }

        if (isReset) {
          sheet.showRows(1, lastRow);
          return;
        }

        const dataRange = sheet.getDataRange();
        const values = dataRange.getValues();
        const backgrounds = dataRange.getBackgrounds();

        let sellerCol = -1;
        let resiCol = -1;
        let statusCol = -1;

        for (let hRow = 0; hRow < Math.min(3, values.length); hRow++) {
          const headers = values[hRow].map(function(h) {
            return String(h).trim().toLowerCase().replace(/[^a-z0-9]/g, '');
          });

          headers.forEach(function(h, idx) {
            if (['seller', 'mitra', 'namaseller', 'cs', 'namacs'].indexOf(h) !== -1 && sellerCol === -1) sellerCol = idx;
            if (['resi', 'noresi', 'barcode', 'awb', 'barcodeitem'].indexOf(h) !== -1 && resiCol === -1) resiCol = idx;
            if (['trackingpos', 'statuspos', 'status'].indexOf(h) !== -1 && statusCol === -1) statusCol = idx;
          });
        }

        if (sellerCol === -1) sellerCol = 6;
        if (resiCol === -1) resiCol = 4;
        if (statusCol === -1) statusCol = 11;

        const targetHex = COLOR_HEX_MAP[targetColor] || null;

        for (let r = 1; r < values.length; r++) {
          const rowNum = r + 1;
          let match = true;

          if (targetSeller !== 'ALL' && targetSeller !== 'SEMUA SELLER') {
            const cleanTarget = targetSeller.replace('MITRA ', '');
            const rowSeller = String(values[r][sellerCol] || '').toUpperCase();
            if (rowSeller.indexOf(cleanTarget) === -1 && sheetTitle.indexOf(cleanTarget) === -1) {
              match = false;
            }
          }

          if (match && targetColor !== 'ALL') {
            const rowBg = String(backgrounds[r][2] || backgrounds[r][0] || '').toUpperCase();
            const rowStatus = String(values[r][statusCol] || '').toUpperCase();

            let colorMatch = false;
            if (targetHex && rowBg === targetHex.toUpperCase()) {
              colorMatch = true;
            } else if (targetColor === 'BIRU' && (rowStatus.indexOf('DELIVERED') !== -1 && rowStatus.indexOf('RETURN') === -1)) {
              colorMatch = true;
            } else if (targetColor === 'ORANGE' && (rowStatus.indexOf('RETURN') !== -1 || rowStatus.indexOf('RETUR') !== -1)) {
              colorMatch = true;
            } else if (targetColor === 'KUNING' && (rowStatus.indexOf('FOLLOW UP') !== -1 || rowStatus.indexOf('GAGAL') !== -1)) {
              colorMatch = true;
            } else if (targetColor === 'PUTIH' && (rowStatus.indexOf('ON PROCESS') !== -1 || rowStatus.indexOf('RUNSHEET') !== -1 || !rowStatus)) {
              colorMatch = true;
            } else if (targetColor === 'HIJAU' || targetColor === 'BIRU_TUA') {
              colorMatch = (rowBg === targetHex.toUpperCase());
            }

            if (!colorMatch) match = false;
          }

          if (match && targetSearch) {
            const rowText = values[r].join(' ').toUpperCase();
            if (rowText.indexOf(targetSearch) === -1) {
              match = false;
            }
          }

          if (match) {
            sheet.showRows(rowNum, 1);
            totalFilteredRows++;
          } else {
            sheet.hideRows(rowNum, 1);
          }
        }
      });

      return ContentService.createTextOutput(JSON.stringify({
        status: 'success',
        message: `Filter applied: Seller [${targetSeller}], Color [${targetColor}]`,
        matched_rows: totalFilteredRows
      })).setMimeType(ContentService.MimeType.JSON);
    }

    // ACTION 2: REVERSE SYNC NIPOS (AUTO-UPDATE STATUS NIPOS, KETERANGAN & SLA TO GOOGLE SHEETS)
    if (action === 'reverse_sync_nipos' || action === 'sync_nipos_status' || action === 'reverse_sync') {
      const items = Array.isArray(data.items) ? data.items : [];
      const itemMap = {};

      items.forEach(function(it) {
        if (it && it.resi) {
          itemMap[String(it.resi).trim().toUpperCase()] = it;
        }
      });

      if (Object.keys(itemMap).length === 0 && data.resis) {
        (data.resis || []).forEach(function(r) {
          if (r) {
            itemMap[String(r).trim().toUpperCase()] = {
              resi: r,
              status_pos: data.status_pos || data.status || 'DELIVERED',
              keterangan: data.keterangan || 'DITERIMA YANG BERSANGKUTAN',
              color_code: data.color_code || data.status_color || 'BIRU',
              sla_days: data.sla_days || data.sla || 2
            };
          }
        });
      }

      if (Object.keys(itemMap).length === 0) {
        return ContentService.createTextOutput(JSON.stringify({
          status: 'error',
          message: 'No reverse sync items provided'
        })).setMimeType(ContentService.MimeType.JSON);
      }

      let updatedCount = 0;
      const updatedResis = [];

      sheets.forEach(function(sheet) {
        const dataRange = sheet.getDataRange();
        const values = dataRange.getValues();
        if (values.length < 2) return;

        const sheetNameUpper = sheet.getName().toUpperCase();
        const isAliqaSheet = sheetNameUpper.indexOf('ALIQA') !== -1 || String(data.seller || '').toUpperCase().indexOf('ALIQA') !== -1;

        let resiCol = -1;
        let trackingPosCol = -1;
        let keteranganCol = -1;
        let slaCol = -1;

        // Scan first 3 rows to locate header columns
        for (let hRow = 0; hRow < Math.min(3, values.length); hRow++) {
          const headers = values[hRow].map(function(h) {
            return String(h).trim().toLowerCase().replace(/[^a-z0-9]/g, '');
          });

          headers.forEach(function(h, idx) {
            if (['resi', 'noresi', 'barcode', 'awb', 'barcodeitem'].indexOf(h) !== -1 && resiCol === -1) {
              resiCol = idx;
            } else if (['trackingpos', 'statuspos', 'status', 'nipos', 'statusnipos', 'statusniposl', 'statusakhir', 'tracking'].indexOf(h) !== -1 && trackingPosCol === -1) {
              trackingPosCol = idx;
            } else if (['keterangan', 'note', 'alasan', 'penerimaketerangank', 'penerima', 'penerimaketerangan', 'posketerangan'].indexOf(h) !== -1 && keteranganCol === -1) {
              keteranganCol = idx;
            } else if (['sla', 'slamasatahan', 'sladays'].indexOf(h) !== -1 && slaCol === -1) {
              slaCol = idx;
            }
          });
        }

        if (isAliqaSheet) {
          if (resiCol === -1) resiCol = 2;              // Kolom C (Resi, index 2)
          if (keteranganCol === -1) keteranganCol = 15; // Kolom P (Keterangan, index 15)
          if (trackingPosCol === -1) trackingPosCol = 16; // Kolom Q (Tracking POS, index 16)
          if (slaCol === -1) slaCol = 17;                // Kolom R (SLA, index 17)
        } else {
          if (resiCol === -1) resiCol = 4;              // Default Kolom E (Resi, index 4)
          if (keteranganCol === -1) keteranganCol = 10; // Default Kolom K (Keterangan, index 10)
          if (trackingPosCol === -1) trackingPosCol = 11; // Default Kolom L (Tracking POS, index 11)
          if (slaCol === -1) slaCol = 12;                // Default Kolom M (SLA, index 12)
        }

        for (let r = 1; r < values.length; r++) {
          let cellResi = String(values[r][resiCol] || '').trim().toUpperCase();
          if ((!cellResi || !itemMap[cellResi]) && values[r][2]) {
            cellResi = String(values[r][2]).trim().toUpperCase();
          }
          if ((!cellResi || !itemMap[cellResi]) && values[r][4]) {
            cellResi = String(values[r][4]).trim().toUpperCase();
          }

          if (cellResi && itemMap[cellResi]) {
            const item = itemMap[cellResi];
            const rowNumber = r + 1;

            const colorCode = String(item.color_code || item.status_color || 'PUTIH').toUpperCase();
            const hexColor = COLOR_HEX_MAP[colorCode] || '#FFFFFF';

            // 1. Mewarnai Baris (Aliqa: Kolom C-R 16 kolom; Zaherba: Kolom C-J 8 kolom)
            if (isAliqaSheet) {
              sheet.getRange(rowNumber, 3, 1, 16).setBackground(hexColor);
            } else {
              sheet.getRange(rowNumber, 3, 1, 8).setBackground(hexColor);
            }

            // 2. Update Sel Kolom 'Status NIPOS' (Aliqa: Q / Zaherba: L)
            const statusPos = item.status_pos || item.status_nipos || item.status;
            if (trackingPosCol >= 0 && statusPos) {
              sheet.getRange(rowNumber, trackingPosCol + 1).setValue(String(statusPos));
            }

            // 3. Update Sel Kolom 'Keterangan' (Aliqa: P / Zaherba: K)
            const keterangan = item.keterangan || item.note;
            if (keteranganCol >= 0 && keterangan) {
              sheet.getRange(rowNumber, keteranganCol + 1).setValue(String(keterangan));
            }

            // 4. Update Sel Kolom 'SLA' (Aliqa: R / Zaherba: M)
            const slaVal = item.sla_days || item.sla;
            if (slaCol >= 0 && slaVal !== undefined && slaVal !== null) {
              const formattedSla = (typeof slaVal === 'number' || !isNaN(slaVal)) ? (Math.round(Number(slaVal))) : String(slaVal);
              sheet.getRange(rowNumber, slaCol + 1).setValue(formattedSla);
            }

            updatedCount++;
            updatedResis.push(cellResi);
          }
        }
      });

      return ContentService.createTextOutput(JSON.stringify({
        status: 'success',
        message: `Reverse Sync success: Updated ${updatedCount} resis in Google Sheet`,
        action: 'reverse_sync_nipos',
        updated_count: updatedCount,
        updated_resis: updatedResis,
        timestamp: new Date().toISOString()
      })).setMimeType(ContentService.MimeType.JSON);
    }

    // ACTION 3: UPDATE RESI STATUS & CS FOLLOW-UP (TWO-WAY SYNC)
    const resiList = data.resis || data.resi_list || (data.no_resi ? [data.no_resi] : []);
    const statusColor = (data.status_color || data.color_code || 'PUTIH').toUpperCase();
    const statusLabel = data.status_label || statusColor;
    const note = data.note || '';
    const escalationDate = data.escalation_date || '';

    if (resiList.length === 0) {
      return ContentService.createTextOutput(JSON.stringify({
        status: 'error',
        message: 'No resi provided'
      })).setMimeType(ContentService.MimeType.JSON);
    }

    const hexColor = COLOR_HEX_MAP[statusColor] || '#FFFFFF';
    let updatedCount = 0;
    const updatedResis = [];
    const targetResiMap = {};
    resiList.forEach(function(r) {
      if (r) targetResiMap[String(r).trim().toUpperCase()] = true;
    });

    sheets.forEach(function(sheet) {
      const dataRange = sheet.getDataRange();
      const values = dataRange.getValues();

      if (values.length < 2) return;

      const sheetNameUpper = sheet.getName().toUpperCase();
      const isAliqaSheet = sheetNameUpper.indexOf('ALIQA') !== -1 || String(data.seller || '').toUpperCase().indexOf('ALIQA') !== -1;

      let resiCol = -1;
      let trackingPosCol = -1;
      let keteranganCol = -1;
      let fuPosDateCol = -1;

      for (let hRow = 0; hRow < Math.min(3, values.length); hRow++) {
        const headers = values[hRow].map(function(h) {
          return String(h).trim().toLowerCase().replace(/[^a-z0-9]/g, '');
        });

        headers.forEach(function(h, idx) {
          if (['resi', 'noresi', 'barcode', 'awb', 'barcodeitem'].indexOf(h) !== -1 && resiCol === -1) {
            resiCol = idx;
          } else if (['trackingpos', 'statuspos', 'status', 'nipos', 'statusnipos', 'statusniposl', 'statusakhir', 'tracking'].indexOf(h) !== -1 && trackingPosCol === -1) {
            trackingPosCol = idx;
          } else if (['keterangan', 'note', 'alasan', 'penerimaketerangank', 'penerima', 'penerimaketerangan', 'posketerangan'].indexOf(h) !== -1 && keteranganCol === -1) {
            keteranganCol = idx;
          } else if (['fubycs', 'fuposdate', 'tglfu', 'escalationdate'].indexOf(h) !== -1 && fuPosDateCol === -1) {
            fuPosDateCol = idx;
          }
        });
      }

      if (isAliqaSheet) {
        if (resiCol === -1) resiCol = 2; // Kolom C (Resi, index 2)
        if (keteranganCol === -1) keteranganCol = 15; // Kolom P (Keterangan, index 15)
        if (trackingPosCol === -1) trackingPosCol = 16; // Kolom Q (Tracking POS, index 16)
      } else {
        if (resiCol === -1) resiCol = 4; // Kolom E (Resi, index 4)
        if (keteranganCol === -1) keteranganCol = 10; // Kolom K (Keterangan, index 10)
        if (trackingPosCol === -1) trackingPosCol = 11; // Kolom L (Tracking POS, index 11)
      }

      for (let r = 1; r < values.length; r++) {
        let cellResi = String(values[r][resiCol] || '').trim().toUpperCase();
        if ((!cellResi || !targetResiMap[cellResi]) && values[r][2]) {
          cellResi = String(values[r][2]).trim().toUpperCase();
        }
        if ((!cellResi || !targetResiMap[cellResi]) && values[r][4]) {
          cellResi = String(values[r][4]).trim().toUpperCase();
        }

        if (cellResi && targetResiMap[cellResi]) {
          const rowNumber = r + 1;

          if (isAliqaSheet) {
            sheet.getRange(rowNumber, 3, 1, 16).setBackground(hexColor);
          } else {
            sheet.getRange(rowNumber, 3, 1, 8).setBackground(hexColor);
          }

          if (trackingPosCol >= 0) {
            sheet.getRange(rowNumber, trackingPosCol + 1).setValue(statusLabel);
          }

          if (keteranganCol >= 0 && note) {
            sheet.getRange(rowNumber, keteranganCol + 1).setValue(note);
          }

          if (fuPosDateCol >= 0 && escalationDate) {
            sheet.getRange(rowNumber, fuPosDateCol + 1).setValue(escalationDate);
          }

          updatedCount++;
          updatedResis.push(cellResi);
        }
      }
      }
    });

    return ContentService.createTextOutput(JSON.stringify({
      status: 'success',
      message: `Updated ${updatedCount} resis to ${statusColor}`,
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
    message: 'Posindo Two-Way Sync Running'
  })).setMimeType(ContentService.MimeType.JSON);
}

function applyColorToSelection(colorHex) {
  const sheet = SpreadsheetApp.getActiveSheet();
  const range = sheet.getActiveRange();
  if (!range) return;

  const startRow = range.getRow();
  const numRows = range.getNumRows();

  for (let i = 0; i < numRows; i++) {
    const rowNum = startRow + i;
    if (rowNum > 1) {
      sheet.getRange(rowNum, 3, 1, 8).setBackground(colorHex);
    }
  }
}

function colorSelectedBiru() { applyColorToSelection(COLOR_HEX_MAP['BIRU']); }
function colorSelectedOrange() { applyColorToSelection(COLOR_HEX_MAP['ORANGE']); }
function colorSelectedKuning() { applyColorToSelection(COLOR_HEX_MAP['KUNING']); }
function colorSelectedHijau() { applyColorToSelection(COLOR_HEX_MAP['HIJAU']); }
function colorSelectedBiruTua() { applyColorToSelection(COLOR_HEX_MAP['BIRU_TUA']); }
function colorSelectedPutih() { applyColorToSelection(COLOR_HEX_MAP['PUTIH']); }