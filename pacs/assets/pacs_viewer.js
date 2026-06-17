(function(){
  const shell = document.querySelector('.pacs-viewer-shell');
  if (!shell) return;
  const apiUrl = shell.dataset.apiUrl;
  const list = document.getElementById('pacsSeriesList');
  const canvas = document.getElementById('pacsCanvas');
  const ctx = canvas.getContext('2d');
  const range = document.getElementById('pacsSliceRange');
  const statusEl = document.getElementById('pacsStatus');
  const overlay = document.getElementById('pacsOverlay');
  let data = null, activeSeries = null, activeIndex = 0, invert = false;
  let windowCenter = 40, windowWidth = 400;
  let lastDicom = null;

  function esc(s){ return String(s ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c])); }
  function setStatus(s, bad){ statusEl.innerHTML = bad ? '<span style="color:#991b1b">'+esc(s)+'</span>' : esc(s); }
  function u16(d,o){ return o+2<=d.byteLength ? d.getUint16(o,true) : 0; }
  function i16(d,o){ return o+2<=d.byteLength ? d.getInt16(o,true) : 0; }
  function u32(d,o){ return o+4<=d.byteLength ? d.getUint32(o,true) : 0; }
  function clean(s){ return String(s||'').replace(/\0/g,'').replace(/\^/g,' ').trim(); }
  function str(bytes){ return clean(new TextDecoder('latin1').decode(bytes)); }

  function parseDicom(buf){
    const d = new DataView(buf);
    const bytes = new Uint8Array(buf);
    let off = 0;
    if (buf.byteLength >= 132 && str(bytes.slice(128,132)) === 'DICM') off = 132;
    const meta = {}, longVr = {OB:1,OD:1,OF:1,OL:1,OW:1,SQ:1,UC:1,UR:1,UT:1,UN:1};
    let pixelOffset = -1, pixelLength = 0, loops = 0, transferSyntax = '', datasetExplicit = true;
    const tags = {
      '0002,0010':'TransferSyntaxUID','0028,0010':'Rows','0028,0011':'Columns','0028,0100':'BitsAllocated','0028,0101':'BitsStored','0028,0103':'PixelRepresentation','0028,1050':'WindowCenter','0028,1051':'WindowWidth','0028,1052':'RescaleIntercept','0028,1053':'RescaleSlope','0028,0004':'PhotometricInterpretation','0020,0013':'InstanceNumber','0008,0060':'Modality','0008,103E':'SeriesDescription','0010,0010':'PatientName','0010,0020':'PatientID'
    };
    const intTags = {'0028,0010':1,'0028,0011':1,'0028,0100':1,'0028,0101':1,'0028,0103':1,'0020,0013':1};
    function readElement(o, explicit){
      if (o + 8 > buf.byteLength) return null;
      const group = u16(d, o), elem = u16(d, o+2);
      let vr = '', header = 8, vl = 0;
      if (explicit) {
        vr = str(bytes.slice(o+4, o+6));
        if (!/^[A-Z]{2}$/.test(vr)) return null;
        if (longVr[vr]) { header = 12; if (o + 12 > buf.byteLength) return null; vl = u32(d, o+8); }
        else { header = 8; vl = u16(d, o+6); }
      } else {
        vl = u32(d, o+4); header = 8;
      }
      return {group, elem, tag:group.toString(16).padStart(4,'0').toUpperCase()+','+elem.toString(16).padStart(4,'0').toUpperCase(), vr, header, vl, valueOffset:o+header};
    }
    while (off + 8 <= buf.byteLength && loops++ < 300000) {
      const peekGroup = u16(d, off);
      const explicit = (peekGroup === 0x0002) ? true : datasetExplicit;
      let el = readElement(off, explicit);
      if (!el && peekGroup !== 0x0002 && datasetExplicit) {
        datasetExplicit = false;
        el = readElement(off, false);
      }
      if (!el) break;
      if (el.tag === '7FE0,0010') { pixelOffset = off + el.header; pixelLength = el.vl; break; }
      if (el.vl === 0xffffffff) break;
      if (el.vl > buf.byteLength || el.valueOffset > buf.byteLength) break;
      if (tags[el.tag] && el.vl > 0 && el.valueOffset + Math.min(el.vl, 4096) <= buf.byteLength) {
        if (intTags[el.tag] && el.vl <= 4) meta[tags[el.tag]] = u16(d, el.valueOffset);
        else meta[tags[el.tag]] = str(bytes.slice(el.valueOffset, el.valueOffset + Math.min(el.vl, 4096))).split('\\')[0];
        if (el.tag === '0002,0010') transferSyntax = String(meta.TransferSyntaxUID || '').trim();
      }
      const step = el.header + el.vl + (el.vl % 2);
      if (step <= 0) break;
      off += step;
      if (peekGroup === 0x0002 && off + 4 <= buf.byteLength && u16(d, off) !== 0x0002) {
        datasetExplicit = transferSyntax !== '1.2.840.10008.1.2';
      }
    }
    if (pixelOffset < 0) throw new Error('Pixel Data tidak ditemukan. Metadata mungkin terbaca, tetapi pixel data tidak ditemukan pada file ini.');
    const rows = Number(meta.Rows||0), cols = Number(meta.Columns||0), bits = Number(meta.BitsAllocated||16);
    if (!rows || !cols) throw new Error('Rows/Columns tidak terbaca. Kemungkinan transfer syntax/metadata belum didukung.');
    if (![8,16].includes(bits)) throw new Error('BitsAllocated ' + bits + ' belum didukung');
    if (pixelLength === 0xffffffff) {
      const ts = meta.TransferSyntaxUID ? (' TransferSyntaxUID: ' + meta.TransferSyntaxUID + '.') : '';
      throw new Error('Compressed/encapsulated DICOM belum didukung viewer internal.' + ts);
    }
    if (pixelOffset + Math.min(pixelLength, rows * cols * (bits/8)) > buf.byteLength) {
      throw new Error('Pixel Data tidak lengkap atau file terpotong. Upload ulang study bila file fisik rusak.');
    }
    return {meta, rows, cols, bits, signed:Number(meta.PixelRepresentation||0)===1, pixelOffset, pixelLength, transferSyntax};
  }

  function renderDicom(parsed, buf){
    lastDicom = parsed;
    const rows = parsed.rows, cols = parsed.cols;
    canvas.width = cols; canvas.height = rows;
    const out = ctx.createImageData(cols, rows);
    const d = new DataView(buf);
    const slope = parseFloat(parsed.meta.RescaleSlope || '1') || 1;
    const intercept = parseFloat(parsed.meta.RescaleIntercept || '0') || 0;
    const wc = Number.isFinite(windowCenter) ? windowCenter : parseFloat(parsed.meta.WindowCenter || '40');
    const ww = Math.max(1, Number.isFinite(windowWidth) ? windowWidth : parseFloat(parsed.meta.WindowWidth || '400'));
    const photo = String(parsed.meta.PhotometricInterpretation || '').toUpperCase();
    const monoInvert = photo.includes('MONOCHROME1');
    const n = rows * cols;
    let p = parsed.pixelOffset;
    for (let i=0, j=0; i<n; i++, j+=4) {
      let v;
      if (parsed.bits === 8) { v = d.getUint8(p++); }
      else { v = parsed.signed ? d.getInt16(p, true) : d.getUint16(p, true); p += 2; }
      v = v * slope + intercept;
      let g = Math.round(((v - (wc - 0.5)) / (ww - 1) + 0.5) * 255);
      if (g < 0) g = 0; else if (g > 255) g = 255;
      if (invert ^ monoInvert) g = 255 - g;
      out.data[j] = out.data[j+1] = out.data[j+2] = g; out.data[j+3] = 255;
    }
    ctx.putImageData(out,0,0);
    const inst = activeSeries.instances[activeIndex];
    overlay.textContent = 'Series: ' + (activeSeries.series_number||'') + ' ' + (activeSeries.series_desc||'') + '\nSlice: ' + (activeIndex+1) + '/' + activeSeries.instances.length + '  Instance: ' + (inst.instance_number||'') + '\nWL: ' + wc + ' / WW: ' + ww + '\n' + cols + ' x ' + rows;
    setStatus('Slice tampil. Drag belum aktif; gunakan slider/tombol slice dan preset WL.');
  }

  async function loadSlice(i){
    if (!activeSeries) return;
    activeIndex = Math.max(0, Math.min(activeSeries.instances.length-1, i));
    range.value = activeIndex;
    const inst = activeSeries.instances[activeIndex];
    setStatus('Memuat slice ' + (activeIndex+1) + '/' + activeSeries.instances.length + '...');
    try {
      if (inst.file_exists === false) {
        throw new Error('File DICOM fisik tidak ditemukan di storage. Upload ulang study dengan patch restore duplikat ini agar file dipulihkan.');
      }
      const res = await fetch(inst.wado_url, {credentials:'same-origin'});
      if (!res.ok) {
        let msg = '';
        try { msg = await res.text(); } catch(_e) {}
        throw new Error('HTTP ' + res.status + (msg ? ' - ' + msg.slice(0, 160) : ''));
      }
      const buf = await res.arrayBuffer();
      const parsed = parseDicom(buf);
      if (!Number.isFinite(windowCenter)) windowCenter = parseFloat(parsed.meta.WindowCenter || '40') || 40;
      if (!Number.isFinite(windowWidth)) windowWidth = parseFloat(parsed.meta.WindowWidth || '400') || 400;
      renderDicom(parsed, buf);
    } catch(e) {
      ctx.clearRect(0,0,canvas.width,canvas.height);
      overlay.textContent = 'Tidak dapat menampilkan pixel data.\n' + e.message + '\nGunakan tombol Native App/WADO untuk DICOM compressed, atau upload ulang bila file fisik hilang.';
      setStatus('Viewer internal belum bisa membaca slice ini: ' + e.message, true);
    }
  }

  function selectSeries(s, idx){
    activeSeries = s; activeIndex = 0;
    document.querySelectorAll('.pacs-series-item').forEach((el,n)=>el.classList.toggle('active', n===idx));
    range.max = Math.max(0, s.instances.length-1); range.value = 0;
    loadSlice(0);
  }

  function renderSeries(){
    list.innerHTML = '';
    data.series.forEach((s, idx) => {
      const div = document.createElement('div');
      div.className = 'pacs-series-item';
      div.innerHTML = '<div class="pacs-series-title">' + esc((s.series_number || '-') + ' · ' + (s.modality || '')) + '</div><div class="pacs-series-meta">' + esc(s.series_desc || s.series_uid) + '<br>' + esc(s.instances.length) + ' instance</div>';
      div.addEventListener('click', () => selectSeries(s, idx));
      list.appendChild(div);
    });
    if (data.series.length) selectSeries(data.series[0], 0);
    else list.innerHTML = '<div class="muted">Series kosong.</div>';
  }

  document.getElementById('pacsPrevSlice').onclick = () => loadSlice(activeIndex - 1);
  document.getElementById('pacsNextSlice').onclick = () => loadSlice(activeIndex + 1);
  document.getElementById('pacsFit').onclick = () => { canvas.style.width=''; canvas.style.height=''; };
  document.getElementById('pacsWlSoft').onclick = () => { windowCenter = 40; windowWidth = 400; loadSlice(activeIndex); };
  document.getElementById('pacsWlLung').onclick = () => { windowCenter = -600; windowWidth = 1500; loadSlice(activeIndex); };
  document.getElementById('pacsInvert').onclick = () => { invert = !invert; loadSlice(activeIndex); };
  range.addEventListener('input', () => loadSlice(parseInt(range.value||'0',10)));

  fetch(apiUrl, {credentials:'same-origin'}).then(r=>r.json()).then(j=>{
    if (!j.ok) throw new Error(j.error || 'Gagal memuat study');
    data = j; renderSeries();
  }).catch(e=>{ list.innerHTML = '<div class="alert err">'+esc(e.message)+'</div>'; setStatus(e.message, true); });
})();
