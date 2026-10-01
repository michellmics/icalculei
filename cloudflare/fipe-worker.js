// iCalculei - Cloudflare Worker que busca a Tabela FIPE em nome do site.
//
// Por quê: o site da FIPE só responde a acessos do Brasil (HTTP 403 para fora). A hospedagem fica nos EUA,
// então o servidor do site nunca consegue. O Worker roda no data center do Cloudflare mais perto de quem
// chama: quando o NAVEGADOR do visitante (no Brasil) chama, ele roda em São Paulo e a FIPE responde.
// Plano grátis: 100 mil pedidos por dia.
//
// Endereço: GET /historico?tipo=1&marca=59&modelo=8323&ano=2021-5, chamado pelo navegador na calculadora de
// depreciação; devolve { vehicle, points } igual a /api/fipe/historico. Só aceita o próprio site (cabeçalho Origin).
//
// Como instalar (painel do Cloudflare):
//   1. Workers & Pages → Create → Create Worker → nome "icalculei-fipe" → Deploy.
//   2. Edit code → apague o exemplo, cole este arquivo inteiro → Deploy.
//   3. No .env do servidor: FIPE_PROXY_URL=https://icalculei-fipe.SEU-USUARIO.workers.dev

const FIPE_API = "https://veiculos.fipe.org.br/api/veiculos/";
// Sites que podem chamar o /historico pelo navegador
const ALLOWED_ORIGINS = ["https://icalculei.com.br", "https://www.icalculei.com.br", "http://localhost:8000"];
const VEHICLE_TYPES = { 1: "carro", 2: "moto", 3: "caminhao" };
const HISTORY_YEARS = 5;
const ZERO_KM_YEAR = 32000; // a FIPE usa o "ano" 32000 para veículo zero km

export default {
  async fetch(request) {
    const url = new URL(request.url);
    if (url.pathname === "/historico") {
      return history(request, url);
    }
    return new Response("Não encontrado", { status: 404 });
  },
};

function fipeRequest(endpoint, body) {
  return fetch(FIPE_API + endpoint, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      "Referer": "https://veiculos.fipe.org.br/",
      "Accept": "application/json",
      // Sem User-Agent o Cloudflare da FIPE responde 403; usa o mesmo do site
      "User-Agent": "iCalculei/1.0 (+https://icalculei.com.br/contato)",
    },
    body,
  });
}

async function fipeJson(endpoint, fields) {
  const response = await fipeRequest(endpoint, new URLSearchParams(fields).toString());
  if (!response.ok) {
    throw new Error(`FIPE HTTP ${response.status}`);
  }
  return response.json();
}

/**
 * Histórico de preço: o mês atual e o mesmo mês de 1 a 5 anos atrás, consultados ao mesmo tempo.
 */
async function history(request, url) {
  const origin = request.headers.get("Origin") || "";
  const allowedOrigin = ALLOWED_ORIGINS.includes(origin) ? origin : null;
  const reply = (data, status = 200) => new Response(JSON.stringify(data), {
    status,
    headers: {
      "Content-Type": "application/json",
      ...(allowedOrigin ? { "Access-Control-Allow-Origin": allowedOrigin, "Vary": "Origin" } : {}),
    },
  });
  if (!allowedOrigin) {
    return reply({ error: "Não autorizado" }, 403);
  }

  const vehicleType = Number(url.searchParams.get("tipo"));
  const brandCode = Number(url.searchParams.get("marca"));
  const modelCode = Number(url.searchParams.get("modelo"));
  const yearMatch = /^(\d{4,5})-(\d{1,2})$/.exec(url.searchParams.get("ano") || "");
  if (!VEHICLE_TYPES[vehicleType] || !(brandCode > 0) || !(modelCode > 0) || !yearMatch) {
    return reply({ error: "Escolha marca, modelo e ano." }, 422);
  }
  const modelYear = Number(yearMatch[1]);
  const fuelCode = Number(yearMatch[2]);

  let references;
  try {
    references = await fipeJson("ConsultarTabelaDeReferencia", {});
  } catch (error) {
    return reply({ error: "A Tabela FIPE não respondeu agora.", detail: error.message, colo: request.cf?.colo }, 502);
  }
  const months = [];
  for (let yearsAgo = HISTORY_YEARS; yearsAgo >= 0; yearsAgo--) {
    if (references[yearsAgo * 12]) {
      months.push(references[yearsAgo * 12]);
    }
  }

  const answers = await Promise.all(months.map((reference) => fipeJson("ConsultarValorComTodosParametros", {
    codigoTabelaReferencia: reference.Codigo,
    codigoMarca: brandCode,
    codigoModelo: modelCode,
    codigoTipoVeiculo: vehicleType,
    anoModelo: modelYear,
    codigoTipoCombustivel: fuelCode,
    tipoVeiculo: VEHICLE_TYPES[vehicleType],
    modeloCodigoExterno: "",
    tipoConsulta: "tradicional",
  }).catch(() => null)));

  if (answers.every((answer) => answer === null)) {
    return reply({ error: "A Tabela FIPE não respondeu agora.", colo: request.cf?.colo }, 502);
  }
  let vehicle = null;
  const points = [];
  answers.forEach((answer, index) => {
    if (!answer || !answer.Valor) {
      return; // o veículo ainda não estava na tabela nesse mês
    }
    vehicle ??= {
      brand: answer.Marca || "",
      model: answer.Modelo || "",
      year: Number(answer.AnoModelo) === ZERO_KM_YEAR ? "Zero km" : String(answer.AnoModelo || modelYear),
      fuel: answer.Combustivel || "",
      fipeCode: answer.CodigoFipe || "",
    };
    // "R$ 47.088,00" → 47088
    points.push({ month: months[index].Mes.trim(), price: Number(answer.Valor.replace(/[^\d,]/g, "").replace(",", ".")) });
  });
  if (points.length === 0) {
    return reply({ error: "Não encontramos preços deste veículo na Tabela FIPE." }, 404);
  }
  return reply({ vehicle, points });
}
