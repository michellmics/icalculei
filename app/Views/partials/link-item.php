<?php
/**
 * Item de lista lateral (ferramentas parecidas, notícias relacionadas...).
 * Calculadoras mostram o símbolo; notícias mostram a foto em miniatura ($image).
 *
 * @var string $href
 * @var string $symbol
 * @var string $image
 * @var string $title
 * @var string $subtitle
 */
?>
<li><a href="<?= e($href) ?>"><?php if (($image ?? '') !== ''): ?><img class="link-thumb" src="<?= e($image) ?>" alt="" width="56" height="40" loading="lazy"><?php else: ?><span class="tool-symbol"><?= e($symbol) ?></span><?php endif; ?><span><?= e($title) ?><?= ($subtitle ?? '') !== '' ? '<small>' . e($subtitle) . '</small>' : '' ?></span></a></li>
