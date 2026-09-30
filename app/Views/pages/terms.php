<?php
/**
 * Página institucional: Termos de uso.
 */
?>
<main class="page">
  <nav class="breadcrumb" aria-label="Você está em"><a href="/">Início</a> › <span>Termos de uso</span></nav>
  <article class="article-body legal-page">
    <h1>Termos de Uso</h1>
    <p class="article-byline">Última atualização: 30/09/2026</p>
    <?php if (config("owner_name") === "" || config("contact_email") === ""): ?><p class="notice">Os trechos em amarelo devem ser preenchidos no arquivo .env (SITE_OWNER_NAME, SITE_OWNER_DOCUMENT, CONTACT_EMAIL, SITE_OWNER_CITY). Recomenda-se a revisão deste texto por um advogado antes da publicação.</p><?php endif; ?>

    <h2>1. Aceitação</h2>
    <p>Estes Termos de Uso regulam o acesso e a utilização do site Vibe2000 (o "Site"), mantido por <?= owner_info("owner_name", "nome completo ou razão social") ?>, inscrito no <?= owner_info("owner_document", "CPF ou CNPJ") ?> (o "Responsável"). Ao acessar ou utilizar o Site, você declara que leu, entendeu e concorda com estes Termos e com a Política de Privacidade. Se não concordar, não utilize o Site.</p>

    <h2>2. O que é o Vibe2000</h2>
    <p>O Vibe2000 é um portal <b>gratuito</b> que oferece calculadoras, conversores e conteúdos informativos (notícias e artigos) sobre temas como finanças, trabalho, saúde, datas e o dia a dia. O Site é mantido com a exibição de anúncios publicitários.</p>

    <h2>3. Natureza das informações e dos resultados</h2>
    <p>As calculadoras e conversores apresentam <b>estimativas</b>, calculadas a partir dos dados informados pelo próprio usuário e das regras, fórmulas e tabelas oficiais conhecidas na data de revisão indicada em cada ferramenta.</p>
    <p>Os resultados têm caráter exclusivamente <b>informativo e educativo</b> e <b>não constituem</b> aconselhamento profissional de qualquer natureza, incluindo contábil, jurídico, trabalhista, tributário, financeiro, de investimentos, médico ou nutricional. Eles não substituem a análise de um profissional habilitado nem os cálculos e documentos oficiais emitidos por empregadores, instituições financeiras ou órgãos públicos.</p>
    <p>Os valores podem diferir do seu caso concreto em razão de convenções e acordos coletivos, benefícios, médias de horas extras, legislação estadual ou municipal, decisões judiciais, alterações legais posteriores à data de revisão, arredondamentos ou outras particularidades não contempladas pela ferramenta.</p>

    <h2>4. Ausência de garantias</h2>
    <p>O Site é fornecido "no estado em que se encontra" e "conforme disponível". Embora o Responsável se empenhe em manter as ferramentas corretas e atualizadas, <b>não há garantia</b> de que os resultados estejam livres de erros, falhas, imprecisões ou desatualizações, nem de que o Site funcionará de forma ininterrupta ou sem falhas técnicas.</p>

    <h2>5. Limitação de responsabilidade</h2>
    <p>Na máxima extensão permitida pela legislação aplicável, o Responsável <b>não se responsabiliza</b> por quaisquer decisões tomadas, atos praticados, perdas, prejuízos ou danos, diretos ou indiretos, materiais ou morais, decorrentes do uso ou da impossibilidade de uso do Site, das calculadoras, dos conversores ou dos conteúdos publicados, incluindo eventuais erros de cálculo ou informações desatualizadas.</p>
    <p>Cabe ao usuário conferir os resultados e, sempre que a decisão envolver dinheiro, direitos, obrigações ou saúde, buscar a orientação de um profissional habilitado ou das fontes oficiais.</p>

    <h2>6. Responsabilidades do usuário</h2>
    <p>Ao utilizar o Site, você se compromete a: informar dados corretos nas ferramentas, sabendo que o resultado depende deles; utilizar o Site de forma lícita; não tentar invadir, sobrecarregar, copiar em massa ou prejudicar o funcionamento do Site; e não utilizar robôs ou mecanismos automatizados para coletar conteúdo ou gerar acessos ou cliques artificiais em anúncios.</p>

    <h2>7. Notícias e artigos</h2>
    <p>Os conteúdos publicados na seção de notícias e artigos têm finalidade informativa, refletem as informações disponíveis na data de publicação e não representam posição oficial de nenhum órgão público ou entidade. Leis, tabelas e valores podem mudar após a publicação.</p>

    <h2>8. Anúncios e links de terceiros</h2>
    <p>O Site exibe anúncios fornecidos por terceiros, como o Google AdSense, e pode conter links para sites externos. O Responsável não controla e não se responsabiliza pelo conteúdo, pelos produtos, pelos serviços ou pelas práticas de privacidade desses terceiros. Qualquer relação estabelecida com anunciantes é de responsabilidade exclusiva do usuário e do anunciante.</p>

    <h2>9. Propriedade intelectual</h2>
    <p>Os textos, o layout, a marca Vibe2000, os códigos e demais elementos do Site pertencem ao Responsável ou são utilizados com autorização, e são protegidos pela legislação de direitos autorais e de propriedade intelectual. É permitido compartilhar links para as páginas do Site. A reprodução total ou parcial do conteúdo sem autorização prévia é proibida.</p>

    <h2>10. Correções e alterações</h2>
    <p>O Responsável pode, a qualquer momento e sem aviso prévio, corrigir, atualizar, alterar, suspender ou retirar ferramentas e conteúdos, bem como alterar estes Termos. A versão vigente estará sempre disponível nesta página, com a data da última atualização. O uso continuado do Site após alterações significa concordância com a nova versão.</p>
    <p>Se você encontrar um erro em alguma calculadora ou conteúdo, pedimos que avise pelo e-mail <?= owner_info("contact_email", "e-mail de contato") ?>. Toda correção relevante é analisada e, quando confirmada, aplicada com a atualização da data de revisão da ferramenta.</p>

    <h2>11. Legislação e foro</h2>
    <p>Estes Termos são regidos pelas leis da República Federativa do Brasil. Fica eleito o foro da comarca de <?= owner_info("owner_city", "cidade/UF") ?> para dirimir eventuais controvérsias, ressalvado o direito do consumidor de ajuizar ação no foro de seu domicílio, quando aplicável.</p>

    <h2>12. Contato</h2>
    <p>Dúvidas sobre estes Termos: <?= owner_info("contact_email", "e-mail de contato") ?>.</p>
  </article>
</main>
