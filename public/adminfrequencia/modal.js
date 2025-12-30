function openConfig(){var m=document.getElementById('modalConfig');if(m)m.style.display='flex'}
function closeConfig(){var m=document.getElementById('modalConfig');if(m)m.style.display='none'}
document.addEventListener('click',function(e){
  if(e.target.classList.contains('tab')){
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    e.target.classList.add('active');
    var pane=e.target.getAttribute('data-tab');
    var panes=['pane-escola','pane-ano','tab-series','tab-turmas','tab-cad','tab-editar','tab-enturmar'];
    panes.forEach(id=>{var el=document.getElementById(id); if(el){el.style.display='none'}});
    var target=document.getElementById(pane);
    if(target){target.style.display='block'}
  }
});
function openPeriod(id){var m=document.getElementById('modal-periodo-'+id);if(m)m.style.display='flex'}
function closePeriod(id){var m=document.getElementById('modal-periodo-'+id);if(m)m.style.display='none'}
function openTurmas(){var m=document.getElementById('modalTurmas');if(m)m.style.display='flex'}
function closeTurmas(){var m=document.getElementById('modalTurmas');if(m)m.style.display='none'}
function openAlunos(){var m=document.getElementById('modalAlunos');if(m)m.style.display='flex'}
function closeAlunos(){var m=document.getElementById('modalAlunos');if(m)m.style.display='none'}
function openUser(){var m=document.getElementById('modalUser');if(m)m.style.display='flex'}
function closeUser(){var m=document.getElementById('modalUser');if(m)m.style.display='none'}
function openSchool(){var m=document.getElementById('modalSchool');if(m)m.style.display='flex'}
function closeSchool(){var m=document.getElementById('modalSchool');if(m)m.style.display='none'}
