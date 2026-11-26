/* 4K – řádkový výpis: místo 50px náhledu (/3/) načíst 220px (/0/) */
(function () {
  function fix() {
    var imgs = document.querySelectorAll('#ProductListCustom1 img.miniThumbnail');
    for (var k = 0; k != imgs.length; k++) {
      var i = imgs[k];
      ['data-src', 'src'].forEach(function (a) {
        var v = i.getAttribute(a);
        if (v) { var n = v.replace('/3/', '/0/'); if (n != v) i.setAttribute(a, n); }
      });
    }
  }
  fix();
  new MutationObserver(fix).observe(document.documentElement, { childList: true, subtree: true });
})();
