<?php
require_once __DIR__ . '/functions.php';

$page_title = '動物ニュース';
$page_description = '飼い主向けの最新ニュースまとめ';
$pdo = get_pdo();

// Try to show cached items first
$items = [];
try{
  $items = $pdo->query('SELECT nc.*, nf.title AS feed_title FROM news_cache nc LEFT JOIN news_feeds nf ON nc.feed_id = nf.feed_id ORDER BY nc.pub_date DESC LIMIT 50')->fetchAll();
} catch (Exception $e){
  $items = [];
}
// If cache empty, fall back to sample
if (empty($items)) {
  $local = __DIR__ . '/data/news_sample.json';
  if (file_exists($local)) {
    $j = json_decode(file_get_contents($local), true);
    if (is_array($j)) {
      $items = array_slice($j, 0, 10);
    }
  }
}

include __DIR__ . '/templates/header.php';
?>
<div class="card">
  <h2>飼い主向けニュースまとめ</h2>
  <p style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <span>外部のニュースフィードをまとめて表示します。最新の項目を上位に表示します。</span>
    <form id="news-filter-form" method="get" style="display:flex;align-items:center;gap:8px;">
      <?php
        $available_feeds = $pdo->query('SELECT feed_id, title FROM news_feeds WHERE enabled = 1')->fetchAll(PDO::FETCH_ASSOC);
        $selected_lang = $_GET['lang'] ?? 'all';
        $selected_feed = isset($_GET['feed_id']) ? (int)$_GET['feed_id'] : 0;
        $q = trim($_GET['q'] ?? '');
      ?>
      <label>言語:
        <select name="lang">
          <option value="all" <?php echo $selected_lang === 'all' ? 'selected' : ''; ?>>すべて</option>
          <option value="ja" <?php echo $selected_lang === 'ja' ? 'selected' : ''; ?>>日本語</option>
          <option value="en" <?php echo $selected_lang === 'en' ? 'selected' : ''; ?>>English</option>
        </select>
      </label>
      <label>フィード:
        <select name="feed_id">
          <option value="0">すべて</option>
          <?php foreach ($available_feeds as $af): ?>
            <option value="<?php echo e($af['feed_id']); ?>" <?php echo $selected_feed === (int)$af['feed_id'] ? 'selected' : ''; ?>><?php echo e($af['title']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <input type="search" name="q" placeholder="キーワード" value="<?php echo e($q); ?>" />
      <button type="submit">絞り込む</button>
    </form>
  </p>
  <?php if (count($items) === 0): ?>
    <p>現在、表示できるニュースがありません。</p>
  <?php else: ?>
    <?php
      function detect_lang($text) {
          if (!$text) return 'en';
          if (preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $text)) return 'ja';
          return 'en';
      }
      function is_animal_related($text){
          if (!$text) return false;
          $hay = mb_strtolower($text);
          $keywords = [
            'dog','dogs','cat','cats','pet','pets','animal','animals','puppy','kitten','rabbit','hamster','fish','bird','parrot','vet','veterinary','rescue','shelter',
            '犬','猫','ペット','動物','犬種','保護','里親','里親募集','獣医','動物病院','迷子'
          ];
          foreach ($keywords as $kw){
            if (mb_stripos($hay, $kw) !== false) return true;
          }
          return false;
      }

          // load admin keywords and threshold
          $allow_keywords = [];
          $block_keywords = [];
          try {
            $krows = $pdo->query("SELECT keyword, type FROM news_keywords")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($krows as $kr) {
              if ($kr['type'] === 'block') $block_keywords[] = $kr['keyword'];
              else $allow_keywords[] = $kr['keyword'];
            }
          } catch (Exception $e) {
            // ignore
          }
            $threshold = 1;
            try {
            $t = $pdo->query("SELECT `value` FROM news_settings WHERE `key` = 'animal_score_threshold' LIMIT 1")->fetchColumn();
            if ($t !== false) $threshold = (int)$t;
          } catch (Exception $e) {}
            $override_show_all = isset($_GET['show_all']) && $_GET['show_all'] === '1';

          $norm = [];
        foreach ($items as $it){
          $title = $it['title'] ?? '';
          $summary = $it['summary'] ?? '';
          $detected = '';
          // detect_lang() exists below; ensure it's available by defining earlier
          if (function_exists('detect_lang')) {
            $detected = detect_lang($title . ' ' . $summary);
          } else {
            $detected = preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $title . ' ' . $summary) ? 'ja' : 'en';
          }
          // feed filter
          $feed_id = isset($it['feed_id']) ? (int)$it['feed_id'] : 0;
          if ($selected_feed && $feed_id !== $selected_feed) continue;
          // language filter:
          // - when "all": include all
          // - when "en": include only detected English
          // - when "ja": include both Japanese and non-Japanese (non-Japanese will be translated later)
          if ($selected_lang === 'en' && $detected !== 'en') continue;
          // keyword filter
          if ($q !== '') {
            $hay = mb_strtolower($title . ' ' . $summary . ' ' . ($it['feed_title'] ?? ''));
            if (mb_strpos($hay, mb_strtolower($q)) === false) continue;
          }
          // scoring by admin keywords
          $combined = $title . ' ' . $summary . ' ' . ($it['feed_title'] ?? '');
          $score = 0;
          foreach ($allow_keywords as $kw) {
            if ($kw === '') continue;
            if (mb_stripos($combined, $kw) !== false) $score++;
          }
          foreach ($block_keywords as $kw) {
            if ($kw === '') continue;
            if (mb_stripos($combined, $kw) !== false) $score--;
          }
          // fallback to built-in animal keyword detection when no keywords defined
          if (empty($allow_keywords)) {
            if (is_animal_related($combined)) $score++; 
          }
          // require score >= threshold unless a free keyword search is provided
          if ($q === '' && !$override_show_all && $score < $threshold) continue;
            $norm[] = [
            'item_id' => $it['item_id'] ?? null,
            'title' => $title,
            'link' => $it['link'] ?? '',
            'date' => $it['pub_date'] ?? ($it['date'] ?? null),
            'feed' => $it['feed_title'] ?? null,
            'feed_id' => $feed_id,
            'summary' => $summary,
            'lang' => $detected,
            ];
        }
          // If user requested Japanese, try to translate non-Japanese items and cache translations
          if ($selected_lang === 'ja' && !empty($norm)) {
            foreach ($norm as &$entry) {
              if ($entry['lang'] === 'ja') continue;
              if (empty($entry['item_id'])) continue;
              try {
                $stmt = $pdo->prepare('SELECT title_translated_ja, summary_translated_ja FROM news_cache WHERE item_id = ? LIMIT 1');
                $stmt->execute([$entry['item_id']]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $t_title = $row['title_translated_ja'] ?? null;
                $t_summary = $row['summary_translated_ja'] ?? null;
                if ($t_title) {
                  $entry['title'] = $t_title;
                } else {
                  $translated = translate_text($entry['title'], 'ja');
                  if ($translated) {
                    $entry['title'] = $translated;
                    $pdo->prepare('UPDATE news_cache SET title_translated_ja = ? WHERE item_id = ?')->execute([$translated, $entry['item_id']]);
                  }
                }
                if ($t_summary) {
                  $entry['summary'] = $t_summary;
                } else {
                  $translated_s = translate_text($entry['summary'], 'ja');
                  if ($translated_s) {
                    $entry['summary'] = $translated_s;
                    $pdo->prepare('UPDATE news_cache SET summary_translated_ja = ? WHERE item_id = ?')->execute([$translated_s, $entry['item_id']]);
                  }
                }
              } catch (Exception $e) {
                // ignore translation failures, show original
              }
            }
            unset($entry);
          }

          usort($norm, function($a,$b){
          $ta = $a['date'] ? strtotime($a['date']) : 0;
          $tb = $b['date'] ? strtotime($b['date']) : 0;
          return $tb <=> $ta;
      });
      $lead = array_shift($norm);
    ?>
    <div class="news-board">
      <?php if ($lead): ?>
        <div class="news-lead">
          <?php $lead_lang = detect_lang(($lead['title'] ?? '') . ' ' . ($lead['summary'] ?? '')); ?>
          <h3>
            <a href="<?php echo e($lead['link']); ?>" target="_blank" lang="<?php echo e($lead_lang); ?>"><?php echo e($lead['title']); ?></a>
            <?php /* translation link removed per request */ ?>
          </h3>
          <?php if ($lead['feed']): ?><div class="news-feed"><?php echo e($lead['feed']); ?></div><?php endif; ?>
          <?php if ($lead['summary']): ?><p class="news-summary" lang="<?php echo e($lead_lang); ?>"><?php echo nl2br(e(mb_strimwidth($lead['summary'],0,500,'...'))); ?></p><?php endif; ?>
          <div class="news-meta"><?php echo e($lead['date'] ? date('Y-m-d H:i', strtotime($lead['date'])) : ''); ?></div>
        </div>
      <?php endif; ?>
      <div class="news-grid">
        <?php foreach ($norm as $r): ?>
          <div class="news-card">
            <?php $r_lang = detect_lang(($r['title'] ?? '') . ' ' . ($r['summary'] ?? '')); ?>
            <h4>
              <a href="<?php echo e($r['link']); ?>" target="_blank" lang="<?php echo e($r_lang); ?>"><?php echo e($r['title']); ?></a>
              <?php /* translation link removed per request */ ?>
            </h4>
            <?php if ($r['feed']): ?><div class="news-feed small"><?php echo e($r['feed']); ?></div><?php endif; ?>
            <?php if ($r['summary']): ?><p class="news-summary small" lang="<?php echo e($r_lang); ?>"><?php echo nl2br(e(mb_strimwidth($r['summary'],0,180,'...'))); ?></p><?php endif; ?>
            <div class="news-meta small"><?php echo e($r['date'] ? date('Y-m-d H:i', strtotime($r['date'])) : ''); ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
<script>
  (function(){
    try{
      var form = document.getElementById('news-filter-form');
      if (!form) return;
      var sel = form.querySelector('select[name="lang"]');
      var feed = form.querySelector('select[name="feed_id"]');
      var q = form.querySelector('input[name="q"]');
      var submit = function(){ form.submit(); };
      if (sel) sel.addEventListener('change', submit);
      if (feed) feed.addEventListener('change', submit);
      // Enter in search should submit (default)
    }catch(e){/* ignore */}
  })();
</script>
