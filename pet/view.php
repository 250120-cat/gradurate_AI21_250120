<?php
require_once __DIR__ . '/../functions.php';

$page_title = '個体表示';
$page_description = 'ペットのコードで検索し、詳細情報や記録を確認できます。';

$pdo = get_pdo();
$code = $_GET['code'] ?? null;
$user = current_user();

$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$pet = null;
if ($code) {
    $stmt = $pdo->prepare('SELECT p.*, s.name as shop_name, o.name as owner_name FROM pets p LEFT JOIN shops s ON p.shop_id = s.shop_id LEFT JOIN owners o ON p.owner_id = o.owner_id WHERE p.code = ? LIMIT 1');
    $stmt->execute([$code]);
    $pet = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        echo 'CSRF token mismatch';
        exit;
    }

    if (isset($_POST['add_health'])) {
        $stmt = $pdo->prepare('INSERT INTO health_records (pet_id, clinic_id, record_date, details, weight) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['pet_id'],
            null,
            $_POST['record_date'],
            $_POST['details'],
            $_POST['weight'] !== '' ? $_POST['weight'] : null
        ]);

        header('Location: ?code=' . urlencode($code));
        exit;
    }

    if (isset($_POST['add_vaccine'])) {
        $stmt = $pdo->prepare('INSERT INTO vaccinations (pet_id, clinic_id, vaccine_name, vaccinated_date, next_due_date, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['pet_id'],
            null,
            trim($_POST['vaccine_name'] ?? ''),
            $_POST['vaccinated_date'] ?? null,
            $_POST['next_due_date'] ?: null,
            trim($_POST['vaccine_notes'] ?? '')
        ]);

        if (function_exists('add_audit_log')) {
            add_audit_log('vaccine_created', 'pet', (int)$_POST['pet_id'], 'ワクチン履歴を追加しました');
        }

        header('Location: ?code=' . urlencode($code));
        exit;
    }

    if (isset($_POST['add_surgery'])) {
        $stmt = $pdo->prepare('INSERT INTO surgeries (pet_id, clinic_id, surgery_name, surgery_date, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['pet_id'],
            null,
            trim($_POST['surgery_name'] ?? ''),
            $_POST['surgery_date'] ?? null,
            trim($_POST['surgery_notes'] ?? '')
        ]);

        if (function_exists('add_audit_log')) {
            add_audit_log('surgery_created', 'pet', (int)$_POST['pet_id'], '手術履歴を追加しました');
        }

        header('Location: ?code=' . urlencode($code));
        exit;
    }

    if (isset($_POST['add_allergy'])) {
        $stmt = $pdo->prepare('INSERT INTO allergies (pet_id, allergy_name, severity, notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $_POST['pet_id'],
            trim($_POST['allergy_name'] ?? ''),
            trim($_POST['severity'] ?? ''),
            trim($_POST['allergy_notes'] ?? '')
        ]);

        if (function_exists('add_audit_log')) {
            add_audit_log('allergy_created', 'pet', (int)$_POST['pet_id'], 'アレルギー情報を追加しました');
        }

        header('Location: ?code=' . urlencode($code));
        exit;
    }

    if (isset($_POST['add_training'])) {
        $stmt = $pdo->prepare('INSERT INTO training_logs (pet_id, log_date, behavior, score, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['pet_id'],
            $_POST['log_date'],
            $_POST['behavior'],
            $_POST['score'] !== '' ? $_POST['score'] : null,
            $_POST['notes']
        ]);

        header('Location: ?code=' . urlencode($code));
        exit;
    }

    if (isset($_POST['report_lost'])) {
        $stmt = $pdo->prepare('INSERT INTO lost_reports (pet_id, report_date, location, status, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['pet_id'],
            $_POST['report_date'],
            $_POST['location'],
            '迷子',
            $_POST['notes']
        ]);

        $lost_pet = $pet;
        if (!$lost_pet && !empty($_POST['pet_id'])) {
            $stmt = $pdo->prepare('SELECT p.*, o.name as owner_name FROM pets p LEFT JOIN owners o ON p.owner_id = o.owner_id WHERE p.pet_id = ? LIMIT 1');
            $stmt->execute([$_POST['pet_id']]);
            $lost_pet = $stmt->fetch();
        }

        $notification_result = notify_lost_report([
            'pet_name' => $lost_pet['name'] ?? '不明',
            'code' => $lost_pet['code'] ?? $code ?? '不明',
            'reporter_name' => current_user()['username'] ?? '飼い主',
            'reporter_phone' => '（ログインユーザーからの報告）',
            'location' => $_POST['location'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'owner_name' => $lost_pet['owner_name'] ?? '',
            'pet_link' => get_app_url('pet/view.php?code=' . urlencode($lost_pet['code'] ?? $code ?? '')),
        ]);

        $flash_msg = '迷子報告を登録しました。';
        $flash_msg .= ' サイト内受信箱に ' . $notification_result['sent'] . ' 件通知しました。';

        $_SESSION['flash'] = $flash_msg;
        header('Location: ?code=' . urlencode($code));
        exit;
    }
}
?>

<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="card">
  <form method="get" class="form-grid">
    <div class="form-group">
      <label>個体コードを入力してください</label>
      <input type="text" name="code" value="<?php echo e($code ?? ''); ?>" placeholder="例: P1234567890">
    </div>
    <button type="submit" class="button">表示</button>
  </form>
</div>

<?php if (!empty($pet)): ?>
  <?php if (!empty($flash)): ?>
    <div class="notice"><?php echo e($flash); ?></div>
  <?php endif; ?>

  <div class="card-list">
    <div class="card-light">
      <h3>
        <?php echo e($pet['name'] ?: '名前未設定'); ?>
        <span class="badge"><?php echo e($pet['species'] ?? '種類未登録'); ?></span>
    </h3>

  <?php if (!empty($pet['photo_path'])): ?>
    <div style="margin: 12px 0;">
      <img
        src="<?php echo BASE_URL . '/' . e($pet['photo_path']); ?>"
        alt="<?php echo e($pet['name'] ?: 'ペット写真'); ?>"
        style="max-width: 100%; width: 260px; border-radius: 12px; display: block;"
      >
    </div>
  <?php endif; ?>

  <p>コード: <strong><?php echo e($pet['code']); ?></strong></p>
  <p>誕生日: <?php echo e($pet['birthday'] ?? '未登録'); ?></p>
  <?php if (has_role('admin')): ?>
    <p><a class="button-link" href="<?php echo BASE_URL; ?>/admin/edit_pet.php?id=<?php echo urlencode($pet['pet_id']); ?>">ペット情報を編集</a></p>
  <?php endif; ?>
  <?php if ($user): ?>
  <p>販売店: <?php echo e($pet['shop_name'] ?? '未登録'); ?></p>
  <p>飼い主: <?php echo e($pet['owner_name'] ?? '未登録'); ?></p>
  <p>マイクロチップ: <?php echo e($pet['microchip'] ?? '未登録'); ?></p>
  <?php else: ?>
    <p>飼い主情報: 管理者のみ確認できます。</p>
  <?php endif; ?>
</div>

    <div class="card-light">
      <h3>QRコード</h3>
      <?php $lost_url = get_app_url('lost.php?code=' . urlencode($pet['code'])); ?>

      <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode($lost_url); ?>" alt="QRコード">

      <p>
        <a href="<?php echo e($lost_url); ?>" target="_blank">
          <?php echo e($lost_url); ?>
        </a>
      </p>

      <p>QRコードを読み取ると、迷子発見報告ページに移動します。</p>
    </div>
  </div>

  <?php
    $relatedStmt = $pdo->prepare('SELECT ct.thread_id, ct.title, ct.status, ct.updated_at FROM consultation_threads ct WHERE ct.pet_id = ? ORDER BY ct.updated_at DESC LIMIT 5');
    $relatedStmt->execute([$pet['pet_id']]);
    $relatedThreads = $relatedStmt->fetchAll();
  ?>
  <div class="card">
    <h2>相談掲示板連携</h2>
    <p>このペットに関する相談スレッドを作成・確認できます。</p>
    <p><a class="button-link" href="<?php echo BASE_URL; ?>/consultation.php?pet_code=<?php echo urlencode($pet['code']); ?>">このペットで相談を作成</a></p>
    <?php if (count($relatedThreads) === 0): ?>
      <p>現在、このペットに紐づく相談はありません。</p>
    <?php else: ?>
      <ul class="record-list">
        <?php foreach ($relatedThreads as $thread): ?>
          <li>
            <strong><?php echo e($thread['title']); ?></strong> <span class="badge"><?php echo e($thread['status']); ?></span><br>
            <small>更新: <?php echo e($thread['updated_at']); ?></small><br>
            <a href="<?php echo BASE_URL; ?>/consultation_thread.php?id=<?php echo e($thread['thread_id']); ?>">相談を見る</a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <?php if ($user): ?>
      <div class="card-list">
    <div class="card-light">
      <h3>ワクチン履歴を追加</h3>

      <form method="post" class="form-grid">
        <input type="hidden" name="pet_id" value="<?php echo e($pet['pet_id']); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

        <div class="form-group">
          <label>ワクチン名</label>
          <input type="text" name="vaccine_name" required>
        </div>

        <div class="form-group">
          <label>接種日</label>
          <input type="date" name="vaccinated_date" required>
        </div>

        <div class="form-group">
          <label>次回予定日</label>
          <input type="date" name="next_due_date">
        </div>

        <div class="form-group">
          <label>メモ</label>
          <textarea name="vaccine_notes"></textarea>
        </div>

        <button type="submit" name="add_vaccine" class="button">追加</button>
      </form>
    </div>

    <div class="card-light">
      <h3>手術履歴を追加</h3>

      <form method="post" class="form-grid">
        <input type="hidden" name="pet_id" value="<?php echo e($pet['pet_id']); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

        <div class="form-group">
          <label>手術名</label>
          <input type="text" name="surgery_name" required>
        </div>

        <div class="form-group">
          <label>手術日</label>
          <input type="date" name="surgery_date" required>
        </div>

        <div class="form-group">
          <label>メモ</label>
          <textarea name="surgery_notes"></textarea>
        </div>

        <button type="submit" name="add_surgery" class="button">追加</button>
      </form>
    </div>

    <div class="card-light">
      <h3>アレルギー情報を追加</h3>

      <form method="post" class="form-grid">
        <input type="hidden" name="pet_id" value="<?php echo e($pet['pet_id']); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

        <div class="form-group">
          <label>アレルギー名</label>
          <input type="text" name="allergy_name" required>
        </div>

        <div class="form-group">
          <label>重症度</label>
          <select name="severity">
            <option value="">未設定</option>
            <option value="軽度">軽度</option>
            <option value="中度">中度</option>
            <option value="重度">重度</option>
          </select>
        </div>

        <div class="form-group">
          <label>メモ</label>
          <textarea name="allergy_notes"></textarea>
        </div>

        <button type="submit" name="add_allergy" class="button">追加</button>
      </form>
    </div>
  </div>
  <div class="card-list">
    <div class="card-light">
      <h3>健康記録を追加</h3>

      <form method="post" class="form-grid">
        <input type="hidden" name="pet_id" value="<?php echo e($pet['pet_id']); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

        <div class="form-group">
          <label>日付</label>
          <input type="date" name="record_date" required>
        </div>

        <div class="form-group">
          <label>詳細</label>
          <textarea name="details"></textarea>
        </div>

        <div class="form-group">
          <label>体重（kg）</label>
          <input type="text" name="weight">
        </div>

        <button type="submit" name="add_health" class="button">追加</button>
      </form>
    </div>

    <div class="card-light">
      <h3>しつけ記録を追加</h3>

      <form method="post" class="form-grid">
        <input type="hidden" name="pet_id" value="<?php echo e($pet['pet_id']); ?>">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

        <div class="form-group">
          <label>日付</label>
          <input type="date" name="log_date" required>
        </div>

        <div class="form-group">
          <label>行動</label>
          <input type="text" name="behavior">
        </div>

        <div class="form-group">
          <label>スコア</label>
          <input type="text" name="score">
        </div>

        <div class="form-group">
          <label>メモ</label>
          <textarea name="notes"></textarea>
        </div>

        <button type="submit" name="add_training" class="button">追加</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h3>迷子報告</h3>

    <form method="post" class="form-grid">
      <input type="hidden" name="pet_id" value="<?php echo e($pet['pet_id']); ?>">
      <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

      <div class="form-group">
        <label>報告日</label>
        <input type="date" name="report_date" required>
      </div>

      <div class="form-group">
        <label>場所</label>
        <input type="text" name="location">
      </div>

      <div class="form-group">
        <label>メモ</label>
        <textarea name="notes"></textarea>
      </div>

      <button type="submit" name="report_lost" class="button">迷子として報告</button>
    </form>
  </div>
  <?php endif; ?>
  <?php
    $hstmt = $pdo->prepare('SELECT * FROM health_records WHERE pet_id = ? ORDER BY record_date DESC');
    $hstmt->execute([$pet['pet_id']]);
    $healths = $hstmt->fetchAll();

    $tstmt = $pdo->prepare('SELECT * FROM training_logs WHERE pet_id = ? ORDER BY log_date DESC');
    $tstmt->execute([$pet['pet_id']]);
    $trainings = $tstmt->fetchAll();

    $lstmt = $pdo->prepare('SELECT * FROM lost_reports WHERE pet_id = ? ORDER BY report_date DESC');
    $lstmt->execute([$pet['pet_id']]);
    $losts = $lstmt->fetchAll();

    $vstmt = $pdo->prepare('SELECT * FROM vaccinations WHERE pet_id = ? ORDER BY vaccinated_date DESC');
    $vstmt->execute([$pet['pet_id']]);
    $vaccinations = $vstmt->fetchAll();

    $sstmt = $pdo->prepare('SELECT * FROM surgeries WHERE pet_id = ? ORDER BY surgery_date DESC');
    $sstmt->execute([$pet['pet_id']]);
    $surgeries = $sstmt->fetchAll();

    $astmt = $pdo->prepare('SELECT * FROM allergies WHERE pet_id = ? ORDER BY created_at DESC');
    $astmt->execute([$pet['pet_id']]);
    $allergies = $astmt->fetchAll();
  ?>

  <div class="summary-box">
    <div><span><?php echo count($healths); ?></span>健康記録</div>
    <div><span><?php echo count($trainings); ?></span>しつけ記録</div>
    <div><span><?php echo count($losts); ?></span>迷子報告</div>
  </div>

<section class="record-section">
  <h4>ワクチン履歴</h4>

  <?php if (count($vaccinations) === 0): ?>
    <p>まだワクチン履歴はありません。</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach ($vaccinations as $v): ?>
        <li>
          <strong>
            <?php echo e($v['vaccinated_date']); ?>
            - <?php echo e($v['vaccine_name']); ?>
          </strong>

          <?php if (!empty($v['next_due_date'])): ?>
            <br>次回予定日: <?php echo e($v['next_due_date']); ?>
          <?php endif; ?>

          <?php if (!empty($v['notes'])): ?>
            <br><?php echo nl2br(e($v['notes'])); ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="record-section">
  <h4>手術履歴</h4>

  <?php if (count($surgeries) === 0): ?>
    <p>まだ手術履歴はありません。</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach ($surgeries as $s): ?>
        <li>
          <strong>
            <?php echo e($s['surgery_date']); ?>
            - <?php echo e($s['surgery_name']); ?>
          </strong>

          <?php if (!empty($s['notes'])): ?>
            <br><?php echo nl2br(e($s['notes'])); ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="record-section">
  <h4>アレルギー情報</h4>

  <?php if (count($allergies) === 0): ?>
    <p>まだアレルギー情報はありません。</p>
  <?php else: ?>
    <ul class="record-list">
      <?php foreach ($allergies as $a): ?>
        <li>
          <strong>
            <?php echo e($a['allergy_name']); ?>
            <?php if (!empty($a['severity'])): ?>
              / <?php echo e($a['severity']); ?>
            <?php endif; ?>
          </strong>

          <?php if (!empty($a['notes'])): ?>
            <br><?php echo nl2br(e($a['notes'])); ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

  <section class="record-section">
    <h4>健康記録</h4>

    <?php if (count($healths) === 0): ?>
      <p>まだ健康記録はありません。</p>
    <?php else: ?>
      <ul class="record-list">
        <?php foreach ($healths as $h): ?>
          <li>
            <strong>
              <?php echo e($h['record_date']); ?>
              <?php if ($h['weight'] !== null): ?>
                / 体重 <?php echo e($h['weight']); ?> kg
              <?php endif; ?>
            </strong>
            <?php echo nl2br(e($h['details'])); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="record-section">
    <h4>しつけ記録</h4>

    <?php if (count($trainings) === 0): ?>
      <p>まだしつけ記録はありません。</p>
    <?php else: ?>
      <ul class="record-list">
        <?php foreach ($trainings as $t): ?>
          <li>
            <strong>
              <?php echo e($t['log_date']); ?> - <?php echo e($t['behavior']); ?>
              <?php if ($t['score'] !== null): ?>
                / スコア: <?php echo e($t['score']); ?>
              <?php endif; ?>
            </strong>
            <?php echo nl2br(e($t['notes'])); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="record-section">
    <h4>迷子報告</h4>

    <?php if (count($losts) === 0): ?>
      <p>まだ迷子報告はありません。</p>
    <?php else: ?>
      <ul class="record-list">
        <?php foreach ($losts as $l): ?>
          <li>
            <strong>
              <?php echo e($l['report_date']); ?> - <?php echo e($l['location']); ?>
              / 状態: <?php echo e($l['status']); ?>
            </strong>
            <?php echo nl2br(e($l['notes'])); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

<?php elseif (isset($code)): ?>
  <div class="card">
    <p>該当する個体が見つかりません。</p>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>