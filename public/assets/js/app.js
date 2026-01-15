function toggleSidebar(){
  const el = document.getElementById('sidebar');
  if(!el) return;
  el.classList.toggle('hidden');
}
