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
function previewAvatar(input,imgId){
  if(!input.files||!input.files[0]) return
  var file=input.files[0]
  var reader=new FileReader()
  reader.onload=function(e){
    var img=document.getElementById(imgId)
    if(img){ img.src=e.target.result; img.style.display='block' }
  }
  reader.readAsDataURL(file)
}
function showToast(text,type){
  if(!text) return
  var opts = (typeof type === 'object' && type) ? type : {}
  var kind = (typeof type === 'string') ? type : (opts.type||'ok')
  var center = !!opts.center
  var duration = opts.duration || 8000
  var selector = center ? '.toast-container.center' : '.toast-container'
  var box=document.querySelector(selector)
  if(!box){ box=document.createElement('div'); box.className=center?'toast-container center':'toast-container'; document.body.appendChild(box) }
  var t=document.createElement('div'); t.className='toast '+kind; t.textContent=text
  box.appendChild(t)
  setTimeout(function(){ t.style.opacity='0'; setTimeout(function(){ t.remove() },300) }, duration)
}
function openImportSummary(){var m=document.getElementById('modalImportSummary');if(m)m.style.display='flex'}
function closeImportSummary(){var m=document.getElementById('modalImportSummary');if(m)m.style.display='none'}
function openEditAluno(id,nome){var m=document.getElementById('modalEditAluno');if(m){m.style.display='flex';var f=m.querySelector('form');if(f){f.querySelector('input[name=id]').value=id;f.querySelector('input[name=nome]').value=nome}}}
function closeEditAluno(){var m=document.getElementById('modalEditAluno');if(m)m.style.display='none'}
function openEnturmarAluno(id){var m=document.getElementById('modalEnturmarAluno');if(m){m.style.display='flex';var f=m.querySelector('form');if(f){f.querySelector('input[name=aluno_id]').value=id}}}
function closeEnturmarAluno(){var m=document.getElementById('modalEnturmarAluno');if(m)m.style.display='none'}
function openFotoAluno(id){var m=document.getElementById('modalFotoAluno');if(m){m.style.display='flex';var f=m.querySelector('form');if(f){f.querySelector('input[name=id]').value=id}}}
function closeFotoAluno(){var m=document.getElementById('modalFotoAluno');if(m)m.style.display='none'}
function openDeleteAluno(id,nome){var m=document.getElementById('modalDeleteAluno');if(m){m.style.display='flex';var f=m.querySelector('form');if(f){f.querySelector('input[name=id]').value=id;var n=m.querySelector('#del_nome');if(n){n.textContent=nome}}}}
function closeDeleteAluno(){var m=document.getElementById('modalDeleteAluno');if(m)m.style.display='none'}
function openRemoveMatricula(alunoId,turmaId){var m=document.getElementById('modalRemoveMatricula');if(m){m.style.display='flex';var f=m.querySelector('form');if(f){f.querySelector('input[name=aluno_id]').value=alunoId;f.querySelector('input[name=turma_id]').value=turmaId}}}
function closeRemoveMatricula(){var m=document.getElementById('modalRemoveMatricula');if(m)m.style.display='none'}
