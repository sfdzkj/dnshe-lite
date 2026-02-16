// assets/js/renew_progress.js
function runBatchRenew(accountIds, urlTemplate, $bar, $status){
  let idx = 0;
  $bar.css('width','0%').text('0%');
  $status.text('准备中...');
  function next(){
    if(idx >= accountIds.length){
      $status.text('完成');
      return;
    }
    const aid = accountIds[idx];
    $status.text('处理账户 #' + aid + ' (' + (idx+1) + '/' + accountIds.length + ')');
    $.getJSON(urlTemplate.replace('{id}', aid), function(resp){
      // ignore
    }).always(function(){
      idx++;
      const pct = Math.round(idx/accountIds.length*100);
      $bar.css('width', pct+'%').text(pct+'%');
      setTimeout(next, 500);
    });
  }
  next();
}
