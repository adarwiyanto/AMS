function isMobileSidebar(){
  return window.matchMedia('(max-width: 920px)').matches;
}

function setMenuButton(open){
  const btn = document.querySelector('.menu-toggle');
  if(btn){
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
}

function openSidebar(){
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  if(!sidebar) return;

  if(isMobileSidebar()){
    sidebar.classList.remove('hidden');
    sidebar.classList.add('open');
    if(backdrop) backdrop.classList.add('open');
    document.body.classList.add('sidebar-lock');
    setMenuButton(true);
  }else{
    sidebar.classList.remove('hidden');
    setMenuButton(true);
  }
}

function closeSidebar(){
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  if(!sidebar) return;

  if(isMobileSidebar()){
    sidebar.classList.remove('open');
    if(backdrop) backdrop.classList.remove('open');
    document.body.classList.remove('sidebar-lock');
    setMenuButton(false);
  }else{
    sidebar.classList.add('hidden');
    setMenuButton(false);
  }
}

function toggleSidebar(){
  const sidebar = document.getElementById('sidebar');
  if(!sidebar) return;

  if(isMobileSidebar()){
    if(sidebar.classList.contains('open')) closeSidebar();
    else openSidebar();
  }else{
    sidebar.classList.toggle('hidden');
    setMenuButton(!sidebar.classList.contains('hidden'));
  }
}

document.addEventListener('DOMContentLoaded', function(){
  const sidebar = document.getElementById('sidebar');
  if(!sidebar) return;

  if(isMobileSidebar()){
    sidebar.classList.remove('hidden');
    sidebar.classList.remove('open');
    setMenuButton(false);
  }else{
    setMenuButton(!sidebar.classList.contains('hidden'));
  }

  sidebar.querySelectorAll('.side-nav a').forEach(function(link){
    link.addEventListener('click', function(){
      if(isMobileSidebar()) closeSidebar();
    });
  });
});

document.addEventListener('keydown', function(e){
  if(e.key === 'Escape' && isMobileSidebar()) closeSidebar();
});

window.addEventListener('resize', function(){
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  if(!sidebar) return;

  if(isMobileSidebar()){
    sidebar.classList.remove('hidden');
    sidebar.classList.remove('open');
    if(backdrop) backdrop.classList.remove('open');
    document.body.classList.remove('sidebar-lock');
    setMenuButton(false);
  }else{
    sidebar.classList.remove('open');
    if(backdrop) backdrop.classList.remove('open');
    document.body.classList.remove('sidebar-lock');
    setMenuButton(!sidebar.classList.contains('hidden'));
  }
});
