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
    let pixelOffset = -1, pixelLength = 0, explicit = true, loops = 0;
    const tags = {
      '0028,0010':'Rows','0028,0011':'Columns','0028,0100':'BitsAllocated','0028,0101':'BitsStored','0028,0103':'PixelRepresentation','0028,1050':'WindowCenter','0028,1051':'WindowWidth','0028,1052':'RescaleIntercept','0028,1053':'RescaleSlope','0028,0004':'PhotometricInterpretation','0020,0013':'InstanceNumber','0008,0060':'Modality','0008,103E':'SeriesDescription','0010,0010':'PatientName','0010,0020':'PatientID'
    };
    while (off + 8 <= buf.byteLength && loops++ < 200000) {
      const group = u16(d, off), elem = u16(d, off+2);
      const tag = group.toString(16).padStart(4,'0').toUpperCase()+','+elem.toString(16).padStart(4,'0').toUpperCase();
      let vr = str(bytes.slice(off+4, off+6));
      let header = 8, vl = 0;
      if (/^[A-Z]{2}$/.test(vr)) {
        if (longVr[vr]) { header = 12; vl = u32(d, off+8); }
        else { header = 8; vl = u16(d, off+6); }
      } else {
        explicit = false; vr = ''; header = 8; vl = u32(d, off+4);
      }
      if (tag === '7FE0,0010') { pixelOffset = off + header; pixelLength = vl; break; }
      if (vl === 0xffffffff || vl > buf.byteLength) break;
      const vo = off + header;
      if (tags[tag] && vo + Math.min(vl, 4096) <= buf.byteLength) {
        if (['0028,0010','0028,0011','0028,0100','0028,0101','0028,0103','0020,0013'].includes(tag) && vl <= 4) meta[tags[tag]] = u16(d, vo);
        else meta[tags[tag]] = str(bytes.slice(vo, vo + Math.min(vl, 4096))).split('\\')[0];
      }
      const step = header + vl + (vl % 2);
      if (step <= 0) break;
      off += step;
    }
    if (pixelOffset < 0) throw new Error('Pixel Data tidak ditemukan');
    const rows = Number(meta.Rows||0), cols = Number(meta.Columns||0), bits = Number(meta.BitsAllocated||16);
    if (!rows || !cols) throw new Error('Rows/Columns tidak terbaca');
    if (![8,16].includes(bits)) throw new Error('BitsAllocated ' + bits + ' belum didukung');
    if (pixelLength === 0xffffffff) throw new Error('Compressed/encapsulated DICOM belum didukung viewer internal');
    return {meta, rows, cols, bits, signed:Number(meta.PixelRepresentation||0)===1, pixelOffset, pixelLength};
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
      const res = await fetch(inst.wado_url, {credentials:'same-origin'});
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const buf = await res.arrayBuffer();
      const parsed = parseDicom(buf);
      if (!Number.isFinite(windowCenter)) windowCenter = parseFloat(parsed.meta.WindowCenter || '40') || 40;
      if (!Number.isFinite(windowWidth)) windowWidth = parseFloat(parsed.meta.WindowWidth || '400') || 400;
      renderDicom(parsed, buf);
    } catch(e) {
      ctx.clearRect(0,0,canvas.width,canvas.height);
      overlay.textContent = 'Tidak dapat menampilkan pixel data.\n' + e.message + '\nGunakan tombol Native App/WADO untuk file compressed.';
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
