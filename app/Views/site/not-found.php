<?php
/**
 * Página não encontrada (404).
 *
 * @var array $tools
 */
use App\Core\View;
?>
<main class="page">
  <div class="not-found">
    <h1>Página não encontrada</h1>
    <p class="tool-lead">O endereço pode estar errado ou a página mudou de lugar. Que tal uma destas calculadoras?</p>
  </div>
  <div class="tool-grid">
    <?php foreach ($tools as $tool): ?><?= View::partial('tool-card', ['tool' => $tool]) ?><?php endforeach; ?>
  </div>
</main>
