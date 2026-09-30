<?php
/**
 * Página institucional: Política de privacidade.
 */
?>
<main class="page">
  <nav class="breadcrumb" aria-label="Você está em"><a href="/">Início</a> › <span>Política de privacidade</span></nav>
  <article class="article-body legal-page">
    <h1>Política de Privacidade</h1>
    <p class="article-byline">Última atualização: 30/09/2026</p>
    <?php if (config("owner_name") === "" || config("contact_email") === ""): ?><p class="notice">Os trechos em amarelo devem ser preenchidos no arquivo .env (SITE_OWNER_NAME, SITE_OWNER_DOCUMENT, CONTACT_EMAIL, SITE_OWNER_CITY). Recomenda-se a revisão deste texto por um advogado antes da publicação.</p><?php endif; ?>

    <h2>1. Quem somos</h2>
    <p>Esta Política explica como o Vibe2000 (o "Site") trata dados pessoais, em conformidade com a Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018, "LGPD"). O controlador dos dados é <?= owner_info("owner_name", "nome completo ou razão social") ?>, inscrito no <?= owner_info("owner_document", "CPF ou CNPJ") ?>, que pode ser contatado pelo e-mail <?= owner_info("contact_email", "e-mail de contato") ?>, também canal do encarregado de dados.</p>

    <h2>2. Dados digitados nas calculadoras</h2>
    <p>Os valores que você digita nas calculadoras e conversores (como salário, datas, peso ou altura) são processados <b>no seu próprio navegador</b>, apenas para exibir o resultado. Esses valores <b>não são enviados, armazenados nem compartilhados</b> pelo Site. As senhas criadas no gerador de senhas também são geradas no seu dispositivo e nunca saem dele.</p>

    <h2>3. Dados coletados automaticamente</h2>
    <p>Como acontece na maioria dos sites, ao navegar podem ser coletados automaticamente dados técnicos, como endereço IP, tipo de navegador e dispositivo, páginas visitadas, data e hora de acesso e site de origem. Esses dados são usados para manter o Site funcionando com segurança, gerar estatísticas de audiência e exibir anúncios.</p>

    <h2>4. Cookies e anúncios</h2>
    <p>Cookies são pequenos arquivos gravados no seu navegador. O Site pode utilizar:</p>
    <p><b>Cookies necessários</b>, para o funcionamento e a segurança do Site e para guardar a sua escolha sobre cookies; <b>um contador de visitas próprio</b>, que grava um identificador aleatório e anônimo no seu navegador para contar visitantes e páginas vistas (não guardamos o seu endereço IP nesse contador e os dados não são compartilhados); e <b>cookies de publicidade</b>, utilizados pelo Google e por seus parceiros para exibir e medir anúncios.</p>
    <p>Os anúncios <b>personalizados</b> só são exibidos se você aceitar no aviso de cookies. Se escolher "Só os necessários", o Google exibe anúncios não personalizados, que ainda podem usar cookies para limitar a frequência, medir resultados e evitar fraudes.</p>
    <p>O Site utiliza o <b>Google AdSense</b>. O Google, como fornecedor terceiro, usa cookies para exibir anúncios com base nas visitas do usuário a este e a outros sites. O uso de cookies de publicidade permite que o Google e seus parceiros exibam anúncios personalizados. Você pode desativar a publicidade personalizada nas <a href="https://adssettings.google.com" target="_blank" rel="noopener">Configurações de anúncios do Google</a> e saber mais em <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener">Como o Google usa informações de sites que usam seus serviços</a>.</p>
    <p>Você também pode bloquear ou apagar cookies nas configurações do seu navegador. Algumas funções do Site podem não funcionar corretamente sem os cookies necessários.</p>

    <h2>5. Dados enviados por você</h2>
    <p>Se você entrar em contato conosco por e-mail ou formulário, trataremos o nome, o e-mail e o conteúdo da mensagem apenas para responder ao contato e tratar a sua solicitação, como a correção de um erro informado.</p>

    <h2>6. Finalidades e bases legais</h2>
    <p>Os dados são tratados para: manter o Site em funcionamento e seguro (legítimo interesse e cumprimento de obrigação legal, como a guarda de registros de acesso prevista no Marco Civil da Internet); gerar estatísticas de audiência (legítimo interesse ou consentimento, conforme o caso); exibir anúncios, inclusive personalizados (consentimento, quando exigido); e responder contatos (execução de procedimentos a pedido do titular e legítimo interesse).</p>

    <h2>7. Compartilhamento</h2>
    <p>Os dados podem ser compartilhados com prestadores de serviço necessários ao funcionamento do Site, como a empresa de hospedagem, e com parceiros de publicidade e estatística, como o Google. Também podem ser compartilhados quando houver obrigação legal ou ordem de autoridade competente. O Site não vende dados pessoais. Alguns desses parceiros podem armazenar dados fora do Brasil, observadas as regras da LGPD para transferência internacional.</p>

    <h2>8. Por quanto tempo guardamos</h2>
    <p>Os registros de acesso são guardados pelo prazo mínimo de 6 meses exigido pelo Marco Civil da Internet (Lei nº 12.965/2014). Mensagens de contato são guardadas pelo tempo necessário para responder e tratar a solicitação. Os prazos dos cookies de terceiros são definidos por seus fornecedores.</p>

    <h2>9. Seus direitos</h2>
    <p>Nos termos do art. 18 da LGPD, você pode solicitar: confirmação da existência de tratamento; acesso aos dados; correção de dados incompletos, inexatos ou desatualizados; anonimização, bloqueio ou eliminação de dados desnecessários ou tratados em desconformidade; portabilidade; informação sobre compartilhamento; e revogação do consentimento. Para exercer esses direitos, escreva para <?= owner_info("contact_email", "e-mail de contato") ?>. Você também pode apresentar reclamação à Autoridade Nacional de Proteção de Dados (ANPD).</p>

    <h2>10. Segurança</h2>
    <p>Adotamos medidas técnicas e administrativas razoáveis para proteger os dados, como conexão segura (HTTPS). Nenhum sistema é totalmente imune a incidentes; caso ocorra algum incidente relevante, adotaremos as providências previstas na LGPD.</p>

    <h2>11. Crianças e adolescentes</h2>
    <p>O Site não é direcionado a menores de idade e não coleta intencionalmente dados pessoais de crianças.</p>

    <h2>12. Alterações desta Política</h2>
    <p>Esta Política pode ser atualizada a qualquer momento. A versão vigente estará sempre nesta página, com a data da última atualização.</p>
  </article>
</main>
