<?php
/**
 * Painel → Mensagens do contato.
 *
 * @var array $messages
 * @var array $subjects
 * @var array $tools
 */
?>
<div class="admin-head"><div><h1>Mensagens do contato</h1><span class="muted">avisos de erro, sugestões e pedidos de anúncio</span></div></div>
<section class="admin-card">
  <?php if ($messages === []): ?>
    <p class="muted">Nenhuma mensagem ainda.</p>
  <?php else: ?>
    <ul class="message-list">
      <?php foreach ($messages as $message): ?>
        <?php
        $isNew = $message['status'] === 'new';
        $toolName = $message['tool_id'] !== null ? ($tools[$message['tool_id']]['name'] ?? $message['tool_id']) : null;
        ?>
        <li>
          <div class="message-top">
            <b><?= e($subjects[$message['subject']] ?? $message['subject']) ?><?= $toolName ? ' · ' . e($toolName) : '' ?></b>
            <span class="status-chip <?= $isNew ? 'status-warn' : 'status-ok' ?>"><?= $isNew ? 'nova' : 'respondida' ?></span>
          </div>
          <span class="muted"><?= e($message['name']) ?> · <span class="selectable-text"><?= e($message['email']) ?></span> · <?= e(date('d/m/Y H:i', strtotime($message['created_at']))) ?></span>
          <p><?= nl2br(e($message['message'])) ?></p>
          <div class="message-actions">
            <form method="post" action="/painel/mensagens/<?= (int) $message['id'] ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="status" value="<?= $isNew ? 'answered' : 'new' ?>">
              <button type="submit" class="secondary-button"><?= $isNew ? 'marcar como respondida' : 'marcar como nova' ?></button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
