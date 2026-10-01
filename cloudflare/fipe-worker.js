// Vibe2000 - Cloudflare Worker que busca a Tabela FIPE em nome do site.
//
// Por quê: o site da FIPE (que também usa Cloudflare) bloqueia pedidos vindos de servidores de hospedagem
// (HTTP 403). Pelo Worker, o pedido sai da rede do Cloudflare. Plano grátis: 100 mil pedidos por dia.
//
// Como instalar (painel do Cloudflare):
//   1. Workers & Pages → Create → Create Worker → nome "vibe2000-fipe" → Deploy.
//   2. Edit code → apague o exemplo, cole este arquivo inteiro → Deploy.
//   3. Settings → Variables and Secrets → Add → tipo Secret, nome PROXY_KEY, valor = uma senha longa
//      (a mesma vai no .env do site em FIPE_PROXY_KEY).
//   4. No .env do servidor: FIPE_PROXY_URL=https://vibe2000-fipe.SEU-USUARIO.workers.dev e FIPE_PROXY_KEY=a senha.
//
// Só aceita POST nos endpoints da FIPE com a senha certa: ninguém mais consegue usar o seu Worker.

const FIPE_API = "https://veiculos.fipe.org.br/api/veiculos/";

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const endpoint = url.pathname.replace(/^\/api\/veiculos\//, "");
    const hasKey = env.PROXY_KEY && request.headers.get("X-Proxy-Key") === env.PROXY_KEY;
    if (request.method !== "POST" || !hasKey || !/^[A-Za-z]+$/.test(endpoint)) {
      return new Response("Não autorizado", { status: 403 });
    }

    const response = await fetch(FIPE_API + endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "Referer": "https://veiculos.fipe.org.br/",
        "Accept": "application/json",
        // Sem User-Agent o Cloudflare da FIPE responde 403; usa o mesmo do site
        "User-Agent": "Vibe2000/1.0 (+https://vibe2000.com.br/contato)",
      },
      body: await request.text(),
    });

    return new Response(response.body, {
      status: response.status,
      headers: { "Content-Type": response.headers.get("Content-Type") || "application/json" },
    });
  },
};
