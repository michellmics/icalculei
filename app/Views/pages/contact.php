<?php
/**
 * Página de contato: /contato
 *
 * @var array $subjects
 * @var array $tools
 * @var array $errors
 * @var array $oldInput
 * @var bool $sent
 * @var string $selectedTool
 * @var string $selectedSubject
 */
$chosenSubject = $oldInput['subject'] ?? $selectedSubject;
$chosenTool = $oldInput['tool_id'] ?? $selectedTool;

$fieldError = function (string $fieldName) use ($errors): string {
    return isset($errors[$fieldName]) ? '<span class="field-error">' . e($errors[$fieldName]) . '</span>' : '';
};
?>
<main class="page">
  <nav class="breadcrumb" aria-label="Você está em"><a href="/">Início</a> › <span>Contato</span></nav>
  <div class="two-columns">
    <section class="article-body">
      <h1>Contato</h1>
      <p class="lead">Encontrou um erro, tem uma sugestão ou quer anunciar? Escreva para a gente.</p>

      <?php if ($sent): ?>
        <p class="form-success" role="status">Mensagem enviada! Obrigado pelo contato. Respondemos em até 5 dias úteis.</p>
      <?php endif; ?>
      <?php if (isset($errors['form'])): ?>
        <p class="form-error" role="alert"><?= e($errors['form']) ?></p>
      <?php endif; ?>

      <form class="contact-form" method="post" action="/contato" novalidate>
        <?= csrf_field() ?>
        <div class="field-row">
          <div class="field"><label for="contact-name">Seu nome</label><input id="contact-name" name="name" type="text" maxlength="80" autocomplete="name" value="<?= e($oldInput['name'] ?? '') ?>"><?= $fieldError('name') ?></div>
          <div class="field"><label for="contact-email">Seu e-mail</label><input id="contact-email" name="email" type="email" maxlength="120" autocomplete="email" value="<?= e($oldInput['email'] ?? '') ?>"><?= $fieldError('email') ?></div>
        </div>
        <div class="field">
          <label for="contact-subject">Assunto</label>
          <select id="contact-subject" name="subject">
            <?php foreach ($subjects as $subjectKey => $subjectLabel): ?>
              <option value="<?= e($subjectKey) ?>" <?= $chosenSubject === $subjectKey ? 'selected' : '' ?>><?= e($subjectLabel) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $fieldError('subject') ?>
        </div>
        <div class="field" id="contact-tool-field" <?= $chosenSubject === 'erro' ? '' : 'hidden' ?>>
          <label for="contact-tool">Qual calculadora?</label>
          <select id="contact-tool" name="tool_id">
            <?php foreach ($tools as $tool): ?>
              <option value="<?= e($tool['id']) ?>" <?= $chosenTool === $tool['id'] ? 'selected' : '' ?>><?= e($tool['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="contact-message">Mensagem</label>
          <textarea id="contact-message" name="message" maxlength="2000" rows="6" placeholder="Se for um erro, conte os valores que você digitou e o resultado que esperava."><?= e($oldInput['message'] ?? '') ?></textarea>
          <span class="field-hint" id="contact-counter"><?= mb_strlen($oldInput['message'] ?? '') ?> / 2000</span>
          <?= $fieldError('message') ?>
        </div>
        <!-- Campo invisível para pegar robôs de spam: pessoas não o preenchem -->
        <div class="honeypot" aria-hidden="true"><label for="contact-website">Não preencha</label><input id="contact-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
        <button type="submit" class="action-button">enviar mensagem</button>
      </form>
    </section>
    <aside class="side-column">
      <section class="side-box">
        <h3>E-mail</h3>
        <p class="selectable-text"><?= owner_info('contact_email', 'e-mail de contato') ?></p>
        <p class="field-hint">Respondemos em até 5 dias úteis.</p>
      </section>
      <section class="side-box">
        <h3>Antes de escrever</h3>
        <p class="field-hint">Para dúvidas sobre o seu caso específico (rescisão, impostos, saúde), procure um profissional habilitado. Não damos consultoria individual.</p>
      </section>
    </aside>
  </div>
</main>
