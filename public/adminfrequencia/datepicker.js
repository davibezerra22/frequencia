document.addEventListener('DOMContentLoaded',function(){
  if (window.flatpickr){
    window.flatpickr.localize(window.flatpickr.l10ns.pt);
    window.flatpickr('.date',{
      locale:'pt',
      altInput:true,
      altFormat:'d/m/Y',
      dateFormat:'Y-m-d',
      allowInput:true
    });
  }
});
