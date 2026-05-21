</main>
<div id="lightbox" class="lightbox" hidden>
  <button type="button" class="lightbox-close" aria-label="ปิด">×</button>
  <img id="lightboxImg" src="" alt="">
  <a id="lightboxOpen" href="#" target="_blank" class="btn btn-sm">เปิดในแท็บใหม่</a>
</div>
<script>
(function(){
  var lb = document.getElementById('lightbox');
  if (!lb) return;
  var img = document.getElementById('lightboxImg');
  var openLink = document.getElementById('lightboxOpen');
  function open(src){
    img.src = src;
    if (openLink) openLink.href = src;
    lb.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function close(){
    lb.hidden = true;
    img.src = '';
    document.body.style.overflow = '';
  }
  document.querySelectorAll('.slip-link').forEach(function(b){
    b.addEventListener('click', function(){ open(b.dataset.slip); });
  });
  lb.addEventListener('click', function(e){
    if (e.target === lb || e.target.classList.contains('lightbox-close')) close();
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && !lb.hidden) close();
  });
})();

// kebab dropdown menu
(function(){
  function closeAll(except){
    document.querySelectorAll('.kebab.open').forEach(function(k){
      if (k !== except) k.classList.remove('open');
    });
  }
  function positionMenu(trigger, menu){
    var r = trigger.getBoundingClientRect();
    // ใช้ fixed coords — top อยู่ใต้ trigger, right ชิดกรอบ trigger
    menu.style.top   = (r.bottom + 4) + 'px';
    menu.style.right = (window.innerWidth - r.right) + 'px';
    menu.style.left  = 'auto';
    // ถ้าจะหลุดจอด้านล่าง → flip ขึ้นด้านบนแทน
    var mh = menu.offsetHeight;
    if (r.bottom + mh + 8 > window.innerHeight) {
      menu.style.top = Math.max(8, r.top - mh - 4) + 'px';
    }
  }
  document.querySelectorAll('.kebab-trigger').forEach(function(t){
    t.addEventListener('click', function(e){
      e.stopPropagation();
      var k = t.closest('.kebab');
      var menu = k.querySelector('.kebab-menu');
      var willOpen = !k.classList.contains('open');
      closeAll(k);
      k.classList.toggle('open', willOpen);
      if (willOpen && menu) {
        // ต้องเปิดก่อนถึงวัดความสูงได้ → set position หลัง toggle
        positionMenu(t, menu);
      }
    });
  });
  document.addEventListener('click', function(e){
    if (!e.target.closest('.kebab-menu')) closeAll(null);
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeAll(null);
  });
  // ปิดเมื่อ scroll/resize เพราะตำแหน่ง fixed จะไม่ตามไปกับ trigger
  window.addEventListener('scroll', function(){ closeAll(null); }, true);
  window.addEventListener('resize', function(){ closeAll(null); });
})();
</script>
</body>
</html>
