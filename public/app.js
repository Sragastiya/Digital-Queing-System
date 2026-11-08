(() => {
 let busy=false, pendingForm=null, fetching=false;
 const dialog=document.getElementById('confirm-dialog');
 document.addEventListener('click',event=>{
  const step=event.target.closest('[data-step]');
  if(step){const input=step.parentElement.querySelector('input');input.value=Math.min(65535,Math.max(1,(parseInt(input.value,10)||1)+Number(step.dataset.step)));}
  if(event.target.closest('[data-close-dialog]'))dialog.close();
  if(event.target.closest('#confirm-submit')&&pendingForm){const form=pendingForm;form.dataset.confirmed='true';dialog.close();form.requestSubmit();}
 });
 document.addEventListener('submit',event=>{
  const form=event.target;
  if(form.dataset.confirm&&!form.dataset.confirmed){event.preventDefault();pendingForm=form;document.getElementById('confirm-title').textContent=form.dataset.confirm;dialog.showModal();return;}
  if(form.method.toLowerCase()==='post'){
   if(busy){event.preventDefault();return;}busy=true;
   form.querySelectorAll('button').forEach(b=>b.disabled=true);
   setTimeout(()=>{busy=false;form.querySelectorAll('button').forEach(b=>b.disabled=false);delete form.dataset.confirmed;},15000);
  }
 });
 const ticket=document.querySelector('[data-ticket-poll]'),board=document.querySelector('[data-staff-poll]');
 async function poll(){
  if((!ticket&&!board)||busy||fetching||document.hidden||dialog.open||(board&&board.contains(document.activeElement)))return;
  fetching=true;const indicator=document.getElementById('refresh-state');
  try{
   const response=await fetch(ticket?ticket.dataset.ticketPoll:board.dataset.staffPoll,{headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(8000)});
   if(!response.ok||response.redirected)throw new Error('Refresh failed');
   const data=await response.json();
   if(ticket){
    if(data.status!==ticket.dataset.status){location.reload();return;}
    const write=(selector,value)=>{const node=document.querySelector(selector);if(node)node.textContent=value;};
    write('[data-ahead]',data.snapshot.ahead);write('[data-estimate]',data.snapshot.estimate_minutes===null?'Belum tersedia':`± ${data.snapshot.estimate_minutes} mnt`);write('[data-latest-called]',data.snapshot.latest_called||'—');
    write('#near-title',data.snapshot.near_turn?'Giliran hampir tiba':'Pantau halaman ini');write('#near-copy',data.snapshot.ahead===0?'Giliran berikutnya; tunggu panggilan staf.':'Datang saat giliranmu hampir tiba. Estimasi dapat berubah mengikuti layanan.');
   }else if(!busy&&!dialog.open&&!board.contains(document.activeElement)){board.innerHTML=data.html;}
   indicator.classList.remove('stale');indicator.textContent='● Diperbarui '+new Date().toLocaleTimeString('id-ID',{timeZone:'Asia/Makassar',hour:'2-digit',minute:'2-digit',second:'2-digit'})+' WITA';
  }catch{indicator.classList.add('stale');indicator.textContent='Pembaruan terputus. Mencoba lagi otomatis; muat ulang jika sesi berakhir.';}finally{fetching=false;}
 }
 function deadline(){document.querySelectorAll('[data-deadline]').forEach(node=>{const seconds=Math.ceil((new Date(node.dataset.deadline)-Date.now())/1000);node.textContent=seconds>0?`Sisa waktu ${Math.floor(seconds/60)} menit ${seconds%60} detik`:'Batas hadir terlewati. Hubungi staf untuk memastikan status antrean.';});}
 deadline();setInterval(deadline,1000);setInterval(poll,5000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});
})();
