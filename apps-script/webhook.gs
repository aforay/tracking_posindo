/**
 * ============================================================
 * POSINDO TRACKING - Google Apps Script Webhook
 * ============================================================
 * CARA PASANG:
 * 1. Buka Spreadsheet Seller di Google Sheets
 * 2. Klik Extensions → Apps Script
 * 3. Hapus semua kode yang ada, paste kode ini
 * 4. Klik Save (Ctrl+S)
 * 5. Klik Deploy → New Deployment
 *    - Type: Web App
 *    - Execute as: Me
 *    - Who has access: Anyone
 * 6. Klik Deploy → Copy URL webhook
 * 7. Paste URL ke Settings website Posindo (Google Sheet Webhook URL)
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
    }

    return ContentService.createTextOutput(JSON.stringify(result)).setMimeType(ContentService.MimeType.JSON);
  } catch(err) {
    return ContentService.createTextOutput(JSON.stringify({status:'error',message:err.toString()})).setMimeType(ContentService.MimeType.JSON);
  }
}

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
  var updated   = 0;
  var resiSet   = {};
  resiList.forEach(function(r){ if(r) resiSet[String(r).trim().toUpperCase()] = true; });

  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheetsToScan = [];
  var specificSheetName = data.sheet || data.sheet_name || '';
  if (specificSheetName) {
    var sh = ss.getSheetByName(specificSheetName);
    if (sh) sheetsToScan.push(sh);
  }
  if (sheetsToScan.length === 0) {
    sheetsToScan = ss.getSheets();
  }

  sheetsToScan.forEach(function(sheet){
    var hdr = findHeaderRow(sheet); if(!hdr) return;
    var cResi = findCol(hdr, CONFIG.COL_RESI);     if(!cResi) return;
    var cFu   = findCol(hdr, CONFIG.COL_STATUS_FU);
    var cTgl  = findCol(hdr, CONFIG.COL_TANGGAL_FU);
    var last  = sheet.getLastRow();
    if(last <= hdr.rowIndex) return;

    // Baca HANYA 1 kolom (Kolom Resi) untuk efisiensi kecepatan
    var resiVals = sheet.getRange(hdr.rowIndex+1, cResi, last-hdr.rowIndex, 1).getValues();
    resiVals.forEach(function(row, i){
      var resi = String(row[0]||'').trim().toUpperCase();
      if(!resiSet[resi]) return;
      var actualRow = hdr.rowIndex + 1 + i;
      
      // Update Status FU beserta Warna
      if(cFu){ 
        var cell = sheet.getRange(actualRow, cFu); 
        cell.setValue(fuLabel); 
        cell.setBackground(getFuBg(fuType)); 
        cell.setFontColor(getFuFg(fuType)); 
      }
      if(cTgl && fuTime){ 
        sheet.getRange(actualRow, cTgl).setValue(fmtDate(fuTime)); 
      }

      // Warnai Baris Data (Kolom C s/d Kolom J)
      try {
        var startCol = Math.min(3, cResi);
        var numCols = Math.min(8, sheet.getLastColumn() - startCol + 1);
        if (numCols > 0) {
          sheet.getRange(actualRow, startCol, 1, numCols).setBackground(getFuBg(fuType));
        }
      } catch (eColor) {}

      updated++;
    });
  });
  return { status:'success', updated_count:updated, message:'FU '+fuLabel+' updated for '+updated+' resi' };
}

function handleNiposUpdate(data) {
  var items = data.items || []; if(!items.length) return {status:'success',updated_count:0};
  var resiMap = {}; items.forEach(function(it){ if(it && it.resi) resiMap[String(it.resi).trim().toUpperCase()] = it; });
  var updated = 0;

  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheetsToScan = [];

  // Optimasi Kecepatan: Jika payload menyebutkan nama sheet (misal: "AGUSTUS 2026 (FP ALIQA)"), hanya scan sheet tersebut
  var specificSheetName = data.sheet || data.sheet_name || (items[0] && (items[0].sheet || items[0].sheet_name)) || '';
  if (specificSheetName) {
    var sh = ss.getSheetByName(specificSheetName);
    if (sh) {
      sheetsToScan.push(sh);
    }
  }

  // Jika tidak ditemukan atau tidak ditentukan, scan semua sheet
  if (sheetsToScan.length === 0) {
    sheetsToScan = ss.getSheets();
  }

  sheetsToScan.forEach(function(sheet){
    var hdr = findHeaderRow(sheet); if(!hdr) return;
    var cResi = findCol(hdr, CONFIG.COL_RESI);   if(!cResi) return;
    var cPos  = findCol(hdr, CONFIG.COL_STATUS_POS);
    var cKet  = findCol(hdr, CONFIG.COL_KETERANGAN);
    var cSla  = findCol(hdr, CONFIG.COL_SLA);
    var cFu   = findCol(hdr, CONFIG.COL_STATUS_FU);
    var last  = sheet.getLastRow();
    if(last <= hdr.rowIndex) return;

    // Baca HANYA 1 kolom (Kolom Resi) sehingga super cepat (kurang dari 100ms per sheet)
    var resiVals = sheet.getRange(hdr.rowIndex+1, cResi, last-hdr.rowIndex, 1).getValues();
    resiVals.forEach(function(row, i){
      var resi = String(row[0]||'').trim().toUpperCase();
      var item = resiMap[resi]; if(!item) return;
      var actualRow = hdr.rowIndex + 1 + i;
      var cc = (item.color_code || 'PUTIH').toUpperCase();
      
      // 1. Update Kolom Status POS (TRACKING) beserta Background & Warna Teks
      if(cPos && item.status_pos){ 
        var posCell = sheet.getRange(actualRow, cPos);
        posCell.setValue(item.status_pos); 
        posCell.setBackground(getFuBg(cc));
        posCell.setFontColor(getFuFg(cc));
      }
      
      // 2. Update Kolom Keterangan
      if(cKet && item.keterangan){ 
        sheet.getRange(actualRow, cKet).setValue(item.keterangan); 
      }
      
      // 3. Update Kolom SLA
      if(cSla && item.sla){ 
        sheet.getRange(actualRow, cSla).setValue(item.sla); 
      }
      
      // 4. Update Kolom Status FU beserta Background & Warna Teks
      if(cFu){
        var cell = sheet.getRange(actualRow, cFu);
        var label = item.status_label || (
          cc === 'BIRU' ? 'DELIVERED (SUKSES)' :
          (cc === 'ORANGE' ? 'RETUR (RETURN)' :
          (cc === 'KUNING' ? 'FOLLOW UP' :
          (cc === 'HIJAU' ? 'SUDAH DIHUBUNGI' :
          (cc === 'BIRU_TUA' ? 'ESKALASI POS' : 'IN PROSES'))))
        );
        cell.setValue(label);
        cell.setBackground(getFuBg(cc));
        cell.setFontColor(getFuFg(cc));
      }

      // 5. Warnai Baris Data (Kolom A s/d Kolom R / batas data utama)
      try {
        var numCols = Math.min(18, sheet.getLastColumn());
        if (numCols > 0) {
          sheet.getRange(actualRow, 1, 1, numCols).setBackground(getFuBg(cc));
        }
      } catch (errColor) {}

      updated++;
    });
  });
  return { status:'success', updated_count:updated };
}

function findHeaderRow(sheet) {
  for(var r=1;r<=Math.min(5,sheet.getLastRow());r++){
    var row=sheet.getRange(r,1,1,sheet.getLastColumn()).getValues()[0].map(function(v){return String(v).toUpperCase();});
    for(var c=0;c<row.length;c++){
      if(CONFIG.COL_RESI.some(function(k){return row[c].includes(k);})) return {rowIndex:r,headers:row};
    }
  }
  return null;
}

function findCol(hdr, keywords) {
  for(var c=0;c<hdr.headers.length;c++){
    var h=hdr.headers[c].trim();
    if(keywords.some(function(k){return h===k||h.includes(k);})) return c+1;
  }
  return null;
}

function getFuBg(ft){
  return {
    BIRU: '#32B8C8',      // Cyan-Teal / Posindo Blue (Paket Sukses)
    ORANGE: '#FFB719',    // Orange/Amber (Paket Retur)
    KUNING: '#FFFF00',    // Bright Yellow (Sudah di FU)
    PUTIH: '#FFFFFF',     // White (Belum di FU / In Process)
    HIJAU: '#93C47D',     // Soft Green (Sudah Dihubungi)
    BIRU_TUA: '#1F4E79'   // Dark Navy Blue (FU POS)
  }[ft] || '#FFFFFF';
}

function getFuFg(ft){ 
  return (ft==='PUTIH'||ft==='KUNING'||ft==='HIJAU'||ft==='ORANGE') ? '#000000' : '#FFFFFF'; 
}

function fmtDate(s){ 
  try{
    var d=new Date(s);
    var p=function(n){return n<10?'0'+n:n;}; 
    return p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+d.getFullYear()+' '+p(d.getHours())+':'+p(d.getMinutes());
  }catch(e){
    return s;
  } 
}
