(function(){
 const input=document.getElementById('avatarInput'),editor=document.getElementById('avatarEditor'),canvas=document.getElementById('avatarCanvas'),ctx=canvas.getContext('2d');
 const zoom=document.getElementById('avatarZoom'), preview=document.getElementById('profilePreview');
 let image=null,ox=0,oy=0,down=null,chosen=null;
 function draw(){if(!image)return;const size=320;ctx.clearRect(0,0,size,size);ctx.fillStyle='#fff';ctx.fillRect(0,0,size,size);const scale=Math.max(size/image.width,size/image.height)*Number(zoom.value);ctx.save();ctx.beginPath();ctx.arc(160,160,159,0,Math.PI*2);ctx.clip();ctx.drawImage(image,(size-image.width*scale)/2+ox,(size-image.height*scale)/2+oy,image.width*scale,image.height*scale);ctx.restore();ctx.strokeStyle='#e5e7eb';ctx.lineWidth=2;ctx.beginPath();ctx.arc(160,160,159,0,Math.PI*2);ctx.stroke();}
 function cancel(){editor.hidden=true;if(!chosen)input.value='';}
 input.addEventListener('change',()=>{
   const file=input.files[0];if(!file)return;
   if(file.size>3*1024*1024){alert('Maksimal 3 MB');input.value='';return;}
   const url=URL.createObjectURL(file),img=new Image();img.onload=()=>{URL.revokeObjectURL(url);image=img;ox=oy=0;zoom.value='1';editor.hidden=false;draw();};img.onerror=()=>{URL.revokeObjectURL(url);alert('Gambar tidak terbaca.');input.value='';};img.src=url;
 });
 zoom.addEventListener('input',draw);
 canvas.addEventListener('pointerdown',e=>{down={x:e.clientX,y:e.clientY,ox,oy};canvas.setPointerCapture(e.pointerId);});
 canvas.addEventListener('pointermove',e=>{if(!down)return;const r=canvas.getBoundingClientRect();ox=down.ox+(e.clientX-down.x)*320/r.width;oy=down.oy+(e.clientY-down.y)*320/r.height;draw();});
 canvas.addEventListener('pointerup',()=>down=null);canvas.addEventListener('pointercancel',()=>down=null);
 document.getElementById('cancelAvatar').addEventListener('click',cancel);
 document.getElementById('applyAvatar').addEventListener('click',()=>{
   canvas.toBlob(blob=>{if(!blob){alert('Gagal memproses foto');return;}
     const file=new File([blob],'profile.jpg',{type:'image/jpeg'}),dt=new DataTransfer();dt.items.add(file);input.files=dt.files;
     preview.replaceChildren();const img=new Image();img.alt='Foto profil baru';img.src=URL.createObjectURL(blob);preview.append(img);
     chosen=true;document.getElementById('removeAvatar').value='0';editor.hidden=true;
   },'image/jpeg',0.88);
 });
 document.getElementById('removeAvatarButton').addEventListener('click',()=>{document.getElementById('removeAvatar').value='1';input.value='';preview.innerHTML='<span>●</span>';chosen=false;});
 document.getElementById('profileSign').addEventListener('change',e=>{const file=e.target.files[0];if(!file)return;const url=URL.createObjectURL(file),img=new Image();img.onload=()=>URL.revokeObjectURL(url);img.src=url;img.alt='Preview tanda tangan';document.getElementById('signPreview').replaceChildren(img);});
})();
