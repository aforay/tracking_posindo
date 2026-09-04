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
  var fuType    = data.fu_type || data.color_code || '';
  var fuLabel   = data.status_label || fuType;
  var fuTime    = data.fu_timestamp || data.updated_at || '';
  var updated   = 0;
  var resiSet   = {};
  resiList.forEach(function(r){ resiSet[r.trim().toUpperCase()] = true; });

  SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(sheet){
    var hdr = findHeaderRow(sheet); if(!hdr) return;
    var cResi = findCol(hdr, CONFIG.COL_RESI);     if(!cResi) return;
    var cFu   = findCol(hdr, CONFIG.COL_STATUS_FU);
    var cTgl  = findCol(hdr, CONFIG.COL_TANGGAL_FU);
    var last  = sheet.getLastRow();
    if(last <= hdr.rowIndex) return;
    var vals  = sheet.getRange(hdr.rowIndex+1,1,last-hdr.rowIndex,sheet.getLastColumn()).getValues();
    vals.forEach(function(row, i){
      var resi = String(row[cResi-1]||'').trim().toUpperCase();
      if(!resiSet[resi]) return;
      var actualRow = hdr.rowIndex + 1 + i;
      if(cFu){ var cell=sheet.getRange(actualRow,cFu); cell.setValue(fuLabel); cell.setBackground(getFuBg(fuType)); cell.setFontColor(getFuFg(fuType)); }
      if(cTgl && fuTime){ sheet.getRange(actualRow,cTgl).setValue(fmtDate(fuTime)); }
      updated++;
    });
  });
  return { status:'success', updated_count:updated, message:'FU '+fuLabel+' updated for '+updated+' resi' };
}

function handleNiposUpdate(data) {
  var items = data.items || []; if(!items.length) return {status:'success',updated_count:0};
  var resiMap = {}; items.forEach(function(it){ resiMap[String(it.resi||'').toUpperCase()] = it; });
  var updated = 0;

  SpreadsheetApp.getActiveSpreadsheet().getSheets().forEach(function(sheet){
    var hdr = findHeaderRow(sheet); if(!hdr) return;
    var cResi = findCol(hdr, CONFIG.COL_RESI);   if(!cResi) return;
    var cPos  = findCol(hdr, CONFIG.COL_STATUS_POS);
    var cKet  = findCol(hdr, CONFIG.COL_KETERANGAN);
    var cSla  = findCol(hdr, CONFIG.COL_SLA);
    var cFu   = findCol(hdr, CONFIG.COL_STATUS_FU);
    var last  = sheet.getLastRow();
    if(last <= hdr.rowIndex) return;
    var vals  = sheet.getRange(hdr.rowIndex+1,1,last-hdr.rowIndex,sheet.getLastColumn()).getValues();
    vals.forEach(function(row, i){
      var resi = String(row[cResi-1]||'').trim().toUpperCase();
      var item = resiMap[resi]; if(!item) return;
      var actualRow = hdr.rowIndex + 1 + i;
      var cc = item.color_code || 'PUTIH';
      if(cPos && item.status_pos){ sheet.getRange(actualRow,cPos).setValue(item.status_pos); }
      if(cKet && item.keterangan){ sheet.getRange(actualRow,cKet).setValue(item.keterangan); }
      if(cSla && item.sla){ sheet.getRange(actualRow,cSla).setValue(item.sla); }
      // Update kolom FU hanya jika NIPOS konfirmasi DELIVERED/RETUR
      if(cFu && (cc==='BIRU'||cc==='ORANGE')){
        var cell=sheet.getRange(actualRow,cFu);
        cell.setValue(item.status_label||cc);
        cell.setBackground(getFuBg(cc));
        cell.setFontColor(getFuFg(cc));
      }
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
  return {BIRU:'#1565C0',ORANGE:'#E65100',KUNING:'#F9A825',HIJAU:'#2E7D32',BIRU_TUA:'#0D47A1'}[ft]||'#FFFFFF';
}
function getFuFg(ft){ return (ft==='PUTIH'||ft==='KUNING')?'#000000':'#FFFFFF'; }
function fmtDate(s){ try{var d=new Date(s);var p=function(n){return n<10?'0'+n:n;}; return p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+d.getFullYear()+' '+p(d.getHours())+':'+p(d.getMinutes());}catch(e){return s;} }
