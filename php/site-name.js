(function(){
  function setSiteName(name){
    if (!name) return;
    try { document.title = name; } catch(_) {}
    try {
      document.querySelectorAll('[data-bind="site_name"], .site-name').forEach(function(el){
        el.textContent = name;
      });
    } catch(_) {}
  }
  try {
    fetch('/admin/u.php', { credentials: 'same-origin' })
      .then(function(res){ return res.json(); })
      .then(function(data){ setSiteName((data && data.site_name) || ''); })
      .catch(function(){ /* 静默失败 */ });
  } catch(_) {
    // 老浏览器兜底：不执行
  }
})();