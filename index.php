<?php
require __DIR__ . '/includes/bootstrap.php';
$base_url = '';
$page_title = setting('site_title');

$campaigns = db_all(
    'SELECT c.*,
            (SELECT COALESCE(SUM(amount),0) FROM donations d WHERE d.campaign_id = c.id AND d.status = "verified") AS total,
            (SELECT COUNT(*)              FROM donations d WHERE d.campaign_id = c.id AND d.status = "verified") AS donors
       FROM campaigns c
   ORDER BY c.status = "active" DESC, c.created_at DESC'
);

require __DIR__ . '/includes/header.php';
?>
<?php if (!$campaigns): ?>
  <div class="empty">ยังไม่มีแคมเปญในตอนนี้</div>
<?php else: ?>
  <div class="campaign-grid">
    <?php foreach ($campaigns as $c): ?>
      <a class="campaign-card <?= $c['status'] === 'closed' ? 'is-closed' : '' ?>"
         href="campaign.php?slug=<?= e($c['slug']) ?>">
        <?php if (!empty($c['hero_image'])): ?>
          <div class="card-hero" style="background-image:url('<?= e($c['hero_image']) ?>')"></div>
        <?php else: ?>
          <div class="card-hero card-hero-placeholder">✦</div>
        <?php endif; ?>
        <div class="card-body">
          <h3><?= e($c['deceased_name']) ?></h3>
          <?php if (!empty($c['relation'])): ?>
            <p class="card-relation"><?= e($c['relation']) ?></p>
          <?php endif; ?>
          <div class="card-stats">
            <div>
              <span class="stat-num">฿<?= thb($c['total']) ?></span>
              <span class="stat-label">ยอดร่วมทำบุญ</span>
            </div>
            <div>
              <span class="stat-num"><?= (int)$c['donors'] ?></span>
              <span class="stat-label">รายการ</span>
            </div>
          </div>
          <?php if ($c['status'] === 'closed'): ?>
            <span class="badge badge-closed">ปิดรับแล้ว</span>
          <?php else: ?>
            <span class="card-cta">คลิกเพื่อร่วมทำบุญ →</span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
