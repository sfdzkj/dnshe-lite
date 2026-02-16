// assets/js/app.js
function confirmAction(message){
  return confirm(message || '确认执行该操作？');
}

// 续期链接：复制完整 URL（避免 & 被转义成 &amp;）
function _b64urlToB64(s){
  s = (s||'').replace(/-/g,'+').replace(/_/g,'/');
  const pad = s.length % 4;
  if(pad) s += '='.repeat(4-pad);
  return s;
}
async function copyUrlB64(b64u){
  if(!b64u) return;
  const url = atob(_b64urlToB64(b64u));
  try{
    if(navigator.clipboard && window.isSecureContext){
      await navigator.clipboard.writeText(url);
      alert('已复制到剪贴板');
      return;
    }
  }catch(e){}
  try{
    const ta=document.createElement('textarea');
    ta.value=url; ta.setAttribute('readonly','');
    ta.style.position='fixed'; ta.style.left='-9999px'; ta.style.top='0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    alert('已复制到剪贴板');
  }catch(e){
    window.prompt('复制下面链接：', url);
  }
}
