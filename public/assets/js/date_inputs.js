/* AMS date inputs: DD/MM/YYYY display, ISO 8601 value sent to PHP. */
(function(){
  'use strict';
  function isoToDisplay(iso){
    const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
    return m ? m[3]+'/'+m[2]+'/'+m[1] : (iso || '');
  }
  function mask(value){
    let digits=String(value || '').replace(/\D/g,'').slice(0,8);
    // Paste ISO dates gracefully.
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) digits=value.slice(8,10)+value.slice(5,7)+value.slice(0,4);
    return digits.slice(0,2)+(digits.length>2?'/'+digits.slice(2,4):'')+(digits.length>4?'/'+digits.slice(4,8):'');
  }
  function displayToIso(display){
    const m=/^(\d{2})\/(\d{2})\/(\d{4})$/.exec(display);
    if(!m) return null;
    const day=+m[1],month=+m[2],year=+m[3];
    const date=new Date(Date.UTC(year,month-1,day));
    if(year<100 || date.getUTCFullYear()!==year || date.getUTCMonth()+1!==month || date.getUTCDate()!==day) return null;
    return m[3]+'-'+m[2]+'-'+m[1];
  }
  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('input[data-ams-date],input[data-ams-date-display]').forEach(function(input){
      const displayOnly=input.hasAttribute('data-ams-date-display');
      let hidden=null;
      if(!displayOnly){
        hidden=document.createElement('input'); hidden.type='hidden'; hidden.name=input.name;
        const initial=String(input.value||'');
        hidden.value=initial;
        input.removeAttribute('name'); input.value=isoToDisplay(initial);
        input.insertAdjacentElement('afterend',hidden);
      }
      input.setAttribute('aria-label',input.getAttribute('aria-label')||'Tanggal (hari/bulan/tahun)');
      input.addEventListener('input',function(){
        const pos=input.selectionStart || 0, before=input.value.slice(0,pos).replace(/\D/g,'').length;
        const text=mask(input.value);
        input.value=text;
        let caret=0,count=0;
        while(caret<text.length && count<before){if(/\d/.test(text[caret])) count++;caret++;}
        if(document.activeElement===input) input.setSelectionRange(caret,caret);
        if(hidden) hidden.value=displayToIso(text)||'';
        input.setCustomValidity('');
      });
      if(input.form){
        input.form.addEventListener('submit',function(event){
          const display=input.value.trim(), valid=displayToIso(display);
          if(display && !valid){
            event.preventDefault();input.setCustomValidity('Masukkan tanggal valid dengan format DD/MM/YYYY.');
            input.reportValidity();input.focus();return;
          }
          input.setCustomValidity('');if(hidden) hidden.value=valid||'';
        });
      }
    });
  });
})();
