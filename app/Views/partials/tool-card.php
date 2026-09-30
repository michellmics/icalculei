<?php
/**
 * Cartão de uma calculadora.
 *
 * @var array $tool
 */
?>
<a class="tool-card" href="/calculadoras/<?= e($tool['id']) ?>">
  <span class="tool-symbol"><?= e($tool['symbol']) ?></span>
  <b><?= e($tool['name']) ?><?= $tool['ready'] ? '' : '<span class="soon-tag">em breve</span>' ?></b>
  <small><?= e($tool['description']) ?></small>
</a>
