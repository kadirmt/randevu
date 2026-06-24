<?php /** @var string $message @var string $backUrl */ ?>
<main class="rv-flow rv-result">
  <div class="rv-cross">!</div>
  <h1 class="rv-h"><?= e($message ?: 'Bir sorun oluştu.') ?></h1>
  <a class="rv-btn rv-btn-primary" href="<?= e($backUrl) ?>">Geri dön</a>
</main>
