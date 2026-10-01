// Vibe2000 - lógica das calculadoras
//
// Cada calculadora tem o mesmo id usado em content/tools.php:
//   html  = campos do formulário
//   setup = liga os campos ao cálculo (o resultado muda enquanto a pessoa digita)
// As tabelas oficiais de INSS e IR ficam no bloco TABELAS OFICIAIS, logo abaixo das funções de apoio.

(() => {
  "use strict";

  /* =====================================================================
     FUNÇÕES DE APOIO
     ===================================================================== */
  // Aceita "1.234,56", "1234,56" e "1234.56"
  function parseNumber(text) {
    const cleanText = String(text).trim().replace(/\s/g, "");
    if (cleanText === "") {
      return NaN;
    }
    const normalized = cleanText.includes(",") ? cleanText.replace(/\./g, "").replace(",", ".") : cleanText;
    return Number(normalized);
  }
  function formatNumber(value, maxDecimals = 2) {
    return new Intl.NumberFormat("pt-BR", { maximumFractionDigits: maxDecimals }).format(value);
  }
  function formatMoney(value) {
    return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(value);
  }
  function formatDate(dateText) {
    const [year, month, day] = dateText.split("-");
    return `${day}/${month}/${year}`;
  }
  function escapeHtml(text) {
    const replacements = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
    return String(text).replace(/[&<>"']/g, (character) => replacements[character]);
  }
  function normalizeText(text) {
    return text.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
  }
  function dateFromInput(inputId) {
    const value = document.getElementById(inputId).value;
    // Meio-dia evita erros de fuso e horário de verão
    return value ? new Date(`${value}T12:00:00`) : null;
  }
  function todayIso(offsetDays = 0) {
    const date = new Date();
    date.setDate(date.getDate() + offsetDays);
    return date.toISOString().slice(0, 10);
  }
  // Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher)
  function easterSunday(year) {
    const a = year % 19, b = Math.floor(year / 100), c = year % 100, d = Math.floor(b / 4), e = b % 4;
    const f = Math.floor((b + 8) / 25), g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7, m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31), day = ((h + l - 7 * m + 114) % 31) + 1;
    return new Date(year, month - 1, day, 12);
  }
  // Feriados nacionais do ano (datas "aaaa-mm-dd"), usados em dias úteis e hora extra
  function holidaysOfYear(year, includeCarnival) {
    const fixedHolidays = ["01-01", "04-21", "05-01", "09-07", "10-12", "11-02", "11-15", "11-20", "12-25"];
    const holidays = new Set(fixedHolidays.map((monthDay) => `${year}-${monthDay}`));
    const easter = easterSunday(year);
    const offsetDate = (days) => new Date(easter.getTime() + days * MILLISECONDS_PER_DAY).toISOString().slice(0, 10);
    holidays.add(offsetDate(-2)); // Sexta-feira Santa
    if (includeCarnival) {
      holidays.add(offsetDate(-48));
      holidays.add(offsetDate(-47));
    }
    return holidays;
  }
  const WEEKDAYS = ["domingo", "segunda-feira", "terça-feira", "quarta-feira", "quinta-feira", "sexta-feira", "sábado"];
  const MILLISECONDS_PER_DAY = 86400000;

  // Visor de resultado: rótulo, valor principal, detalhe e uma grade opcional de valores
  function display(label, value, detail = "", gridItems = [], extraHtml = "") {
    const grid = gridItems.length
      ? `<div class="display-grid">${gridItems.map(([itemLabel, itemValue]) => `<div><span>${itemLabel}</span><b>${itemValue}</b></div>`).join("")}</div>`
      : "";
    return `<div class="display"><span class="display-label">${label}</span><span class="display-value">${value}</span>${detail ? `<span class="display-detail">${detail}</span>` : ""}${grid}${extraHtml}</div>`;
  }
  function emptyDisplay(label, message) {
    return display(label, "—", message);
  }

  // Liga os campos à função de cálculo (o resultado muda enquanto a pessoa digita)
  function onInputs(ids, calculate) {
    ids.forEach((id) => document.getElementById(id).addEventListener("input", calculate));
    calculate();
  }
  /* ---------- Máscara de dinheiro ----------
     Campos com o atributo data-money viram "caixa registradora": a pessoa digita
     só números e os centavos entram pela direita (450000 → 4.500,00).
     Colar um valor pronto ("4500" ou "4.500,50") também funciona. */
  const MONEY_MAX_DIGITS = 13; // até 99.999.999.999,99
  const moneyFormat = new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function formatMoneyInput(amount) {
    return amount > 0 ? moneyFormat.format(amount) : "";
  }
  function applyMoneyMask(input) {
    const digits = input.value.replace(/\D/g, "").slice(-MONEY_MAX_DIGITS);
    input.value = formatMoneyInput(Number(digits) / 100);
    // Cursor sempre no fim, onde os números entram
    input.setSelectionRange(input.value.length, input.value.length);
  }
  // Fase de captura: a máscara roda antes do cálculo, que também escuta "input"
  document.addEventListener("input", (inputEvent) => {
    if (inputEvent.target.matches?.("input[data-money]")) {
      applyMoneyMask(inputEvent.target);
    }
  }, true);
  document.addEventListener("paste", (pasteEvent) => {
    const input = pasteEvent.target;
    if (!input.matches?.("input[data-money]")) {
      return;
    }
    let pastedText = pasteEvent.clipboardData.getData("text").replace(/[^\d.,]/g, "");
    // "4.500" sem vírgula é quatro mil e quinhentos, não 4,5
    if (/^\d{1,3}(\.\d{3})+$/.test(pastedText)) {
      pastedText = pastedText.replace(/\./g, "");
    }
    const pastedAmount = parseNumber(pastedText);
    if (Number.isFinite(pastedAmount)) {
      pasteEvent.preventDefault();
      input.value = formatMoneyInput(Math.min(pastedAmount, 10 ** (MONEY_MAX_DIGITS - 2) - 0.01));
      input.dispatchEvent(new Event("input", { bubbles: true }));
    }
  });
  // Formata os valores iniciais escritos no html (ex.: "100" → "100,00")
  function formatMoneyFields(container) {
    container.querySelectorAll("input[data-money]").forEach((input) => {
      const initialAmount = parseNumber(input.value);
      input.value = Number.isFinite(initialAmount) ? formatMoneyInput(initialAmount) : "";
    });
  }

  function setupSegmented(groupId, onChange) {
    const buttons = document.querySelectorAll(`#${groupId} button`);
    buttons.forEach((button) => {
      button.addEventListener("click", () => {
        buttons.forEach((otherButton) => otherButton.setAttribute("aria-pressed", String(otherButton === button)));
        onChange(button.dataset.value);
      });
    });
  }
  function greatestCommonDivisor(first, second) {
    let a = Math.abs(first);
    let b = Math.abs(second);
    while (b) {
      [a, b] = [b, a % b];
    }
    return a;
  }


  /* =====================================================================
     TABELAS OFICIAIS 2026 (INSS, Imposto de Renda e seguro-desemprego)
     Usadas por: salário líquido, rescisão, 13º, férias e seguro-desemprego.
     Quando o governo publicar tabelas novas, atualize SOMENTE este bloco
     e a data de revisão das calculadoras trabalhistas.
     ===================================================================== */
  const TAX_TABLES = {
    validFrom: "2026-01-01",
    inss: {
      // Portaria Interministerial MPS/MF nº 13, de 9/1/2026 (empregados, domésticos e avulsos)
      source: "Portaria Interministerial MPS/MF nº 13/2026",
      brackets: [
        { upTo: 1621.00, rate: 0.075 },
        { upTo: 2902.84, rate: 0.09 },
        { upTo: 4354.27, rate: 0.12 },
        { upTo: 8475.55, rate: 0.14 },
      ],
    },
    irrf: {
      // Tabela progressiva mensal e redução da Lei nº 15.270/2025 (Receita Federal, tributação de 2026)
      source: "Receita Federal · Lei nº 15.270/2025",
      brackets: [
        { upTo: 2428.80, rate: 0, deduction: 0 },
        { upTo: 2826.65, rate: 0.075, deduction: 182.16 },
        { upTo: 3751.05, rate: 0.15, deduction: 394.16 },
        { upTo: 4664.68, rate: 0.225, deduction: 675.49 },
        { upTo: Infinity, rate: 0.275, deduction: 908.73 },
      ],
      dependentDeduction: 189.59,
      simplifiedDiscount: 607.20,
      // Redução mensal: zera o imposto de quem recebe até R$ 5.000
      // e diminui aos poucos entre R$ 5.000,01 e R$ 7.350 (R$ 978,62 − 0,133145 × rendimentos)
      reduction: {
        fullUpTo: 5000.00,
        partialUpTo: 7350.00,
        partialBase: 978.62,
        partialFactor: 0.133145,
      },
    },
    fgtsMonthlyRate: 0.08,
    unemploymentInsurance: {
      // Tabela do Ministério do Trabalho e Emprego, vigente desde 11/1/2026 (corrigida pelo INPC)
      source: "Ministério do Trabalho e Emprego, tabela de 11/1/2026",
      firstBracketUpTo: 2222.17, // até aqui: 80% da média
      secondBracketUpTo: 3703.99, // até aqui: valor base + 50% do que passar da 1ª faixa
      secondBracketBase: 1777.74,
      minimum: 1621.00, // salário mínimo de 2026
      maximum: 2518.65,
    },
  };

  function roundCents(value) {
    return Math.round(value * 100) / 100;
  }

  // INSS progressivo: cada faixa paga só sobre a parte do salário que está dentro dela
  function calculateInss(contributionBase) {
    let contribution = 0;
    let lowerLimit = 0;
    for (const bracket of TAX_TABLES.inss.brackets) {
      if (contributionBase <= lowerLimit) {
        break;
      }
      const amountInBracket = Math.min(contributionBase, bracket.upTo) - lowerLimit;
      contribution += amountInBracket * bracket.rate;
      lowerLimit = bracket.upTo;
    }
    return roundCents(contribution);
  }

  /**
   * Imposto de Renda retido na fonte.
   * taxableIncome: rendimentos tributáveis do mês (ex.: salário bruto)
   * isThirteenth: o 13º tem tributação exclusiva e não usa o desconto simplificado
   */
  function calculateIncomeTax({ taxableIncome, inss, dependents = 0, isThirteenth = false }) {
    const table = TAX_TABLES.irrf;
    const legalDeductions = inss + dependents * table.dependentDeduction;
    const usesSimplified = !isThirteenth && table.simplifiedDiscount > legalDeductions;
    const deductions = usesSimplified ? table.simplifiedDiscount : legalDeductions;
    const taxBase = Math.max(0, taxableIncome - deductions);

    const bracket = table.brackets.find((item) => taxBase <= item.upTo);
    const taxBeforeReduction = Math.max(0, taxBase * bracket.rate - bracket.deduction);

    let reduction = 0;
    if (taxableIncome <= table.reduction.fullUpTo) {
      // Até R$ 5.000 o imposto é zero (inclusive no 13º, que não usa o desconto simplificado)
      reduction = taxBeforeReduction;
    } else if (taxableIncome <= table.reduction.partialUpTo) {
      reduction = table.reduction.partialBase - table.reduction.partialFactor * taxableIncome;
    }
    // A redução nunca passa do imposto calculado (no máximo zera)
    reduction = Math.min(Math.max(0, reduction), taxBeforeReduction);

    return {
      taxBase: roundCents(taxBase),
      deductions: roundCents(deductions),
      usesSimplified,
      rate: bracket.rate,
      taxBeforeReduction: roundCents(taxBeforeReduction),
      reduction: roundCents(reduction),
      tax: roundCents(taxBeforeReduction - reduction),
    };
  }

  function formatPercent(rate) {
    return `${formatNumber(rate * 100, 2)}%`;
  }

  // Tabela de verbas (proventos e descontos) usada na rescisão e nas outras calculadoras trabalhistas
  function payslipTable(rows) {
    const bodyRows = rows.map((row) => `<tr><td>${row.label}</td><td class="${row.isDiscount ? "discount-cell" : ""}">${row.isDiscount ? "− " : ""}${formatMoney(Math.abs(row.value))}</td></tr>`).join("");
    return `<table class="data-table payslip"><thead><tr><th>Verba</th><th>Valor</th></tr></thead><tbody>${bodyRows}</tbody></table>`;
  }

  const LABOR_NOTICE = `Tabelas vigentes desde ${formatDate(TAX_TABLES.validFrom)}: INSS (${TAX_TABLES.inss.source}) e IR (${TAX_TABLES.irrf.source}). Resultado estimado; convenções coletivas, médias de horas extras e outros descontos podem alterar os valores.`;


  /* =====================================================================
     CALCULADORAS
     ===================================================================== */
  const CALCULATORS = {
    "porcentagem": {
      html: `        <div class="calculator-body">
          <div class="segmented" id="percent-modes" role="group" aria-label="Tipo de cálculo">
            <button type="button" data-value="of" aria-pressed="true">X% de Y</button>
            <button type="button" data-value="ratio" aria-pressed="false">X é quantos % de Y</button>
            <button type="button" data-value="change" aria-pressed="false">Aumento / desconto</button>
            <button type="button" data-value="variation" aria-pressed="false">Variação</button>
          </div>
          <div class="sentence" id="percent-sentence"></div>
        </div>
        <div id="percent-result"></div>`,
      setup() {
        const sentences = {
          of: 'Quanto é <input id="percent-a" inputmode="decimal" value="15" aria-label="Porcentagem"> % de <input id="percent-b" inputmode="decimal" value="250" aria-label="Valor">?',
          ratio: '<input id="percent-a" inputmode="decimal" value="30" aria-label="Parte"> é quantos % de <input id="percent-b" inputmode="decimal" value="120" aria-label="Total">?',
          change: '<input id="percent-b" inputmode="decimal" value="200" aria-label="Valor"> com <input id="percent-a" inputmode="decimal" value="10" aria-label="Porcentagem"> % de <select id="percent-direction" aria-label="Aumento ou desconto"><option value="1">aumento</option><option value="-1" selected>desconto</option></select>',
          variation: 'De <input id="percent-a" inputmode="decimal" value="80" aria-label="Valor inicial"> para <input id="percent-b" inputmode="decimal" value="100" aria-label="Valor final">',
        };
        let mode = "of";
        function calculate() {
          const firstValue = parseNumber(document.getElementById("percent-a").value);
          const secondValue = parseNumber(document.getElementById("percent-b").value);
          const resultBox = document.getElementById("percent-result");
          if (Number.isNaN(firstValue) || Number.isNaN(secondValue)) {
            resultBox.innerHTML = emptyDisplay("Resultado", "Preencha os dois campos com números.");
          } else if (mode === "of") {
            resultBox.innerHTML = display("Resultado", formatNumber((firstValue / 100) * secondValue, 4), `${formatNumber(firstValue)}% de ${formatNumber(secondValue)}`);
          } else if (mode === "ratio") {
            resultBox.innerHTML = secondValue === 0 ? emptyDisplay("Resultado", "O total não pode ser zero.") : display("Resultado", `${formatNumber((firstValue / secondValue) * 100, 4)}%`);
          } else if (mode === "change") {
            const direction = Number(document.getElementById("percent-direction").value);
            const difference = (firstValue / 100) * secondValue;
            resultBox.innerHTML = display("Valor final", formatNumber(secondValue + direction * difference, 4), `${direction > 0 ? "acréscimo" : "desconto"} de ${formatNumber(difference, 4)}`);
          } else if (firstValue === 0) {
            resultBox.innerHTML = emptyDisplay("Variação", "O valor inicial não pode ser zero.");
          } else {
            const variation = ((secondValue - firstValue) / Math.abs(firstValue)) * 100;
            resultBox.innerHTML = display("Variação", `${variation > 0 ? "+" : ""}${formatNumber(variation)}%`, variation >= 0 ? "aumento" : "queda");
          }
        }
        function renderSentence() {
          document.getElementById("percent-sentence").innerHTML = sentences[mode];
          document.querySelectorAll("#percent-sentence input, #percent-sentence select").forEach((field) => field.addEventListener("input", calculate));
          calculate();
        }
        setupSegmented("percent-modes", (newMode) => { mode = newMode; renderSentence(); });
        renderSentence();
      },
    },

    "regra-de-tres": {
      html: `        <div class="calculator-body">
          <div class="segmented" id="rule-type" role="group" aria-label="Tipo de proporção">
            <button type="button" data-value="direct" aria-pressed="true">Direta</button>
            <button type="button" data-value="inverse" aria-pressed="false">Inversa</button>
          </div>
          <div class="sentence">Se <input id="rule-a" inputmode="decimal" value="3" aria-label="A"> está para <input id="rule-b" inputmode="decimal" value="12" aria-label="B">,</div>
          <div class="sentence">então <input id="rule-c" inputmode="decimal" value="5" aria-label="C"> está para <b>X</b>.</div>
        </div>
        <div id="rule-result"></div>`,
      setup() {
        let proportionType = "direct";
        function calculate() {
          const [valueA, valueB, valueC] = ["rule-a", "rule-b", "rule-c"].map((id) => parseNumber(document.getElementById(id).value));
          const resultBox = document.getElementById("rule-result");
          const divisor = proportionType === "direct" ? valueA : valueC;
          if ([valueA, valueB, valueC].some(Number.isNaN)) {
            resultBox.innerHTML = emptyDisplay("X =", "Preencha os três valores.");
          } else if (divisor === 0) {
            resultBox.innerHTML = emptyDisplay("X =", "Não dá para dividir por zero.");
          } else {
            const unknownValue = proportionType === "direct" ? (valueB * valueC) / valueA : (valueA * valueB) / valueC;
            resultBox.innerHTML = display("X =", formatNumber(unknownValue, 4), proportionType === "direct" ? "B × C ÷ A" : "A × B ÷ C");
          }
        }
        setupSegmented("rule-type", (newType) => { proportionType = newType; calculate(); });
        onInputs(["rule-a", "rule-b", "rule-c"], calculate);
      },
    },

    "media": {
      html: `        <div class="calculator-body">
          <div class="field">
            <label for="stats-input">Números</label>
            <textarea id="stats-input" rows="3">7,5; 8; 6,5; 9; 8; 10</textarea>
            <span class="field-hint">Separe por ponto e vírgula, espaço ou quebra de linha. Use vírgula para decimais.</span>
          </div>
        </div>
        <div id="stats-result"></div>`,
      setup() {
        function calculate() {
          const numbers = document.getElementById("stats-input").value.split(/[;\s]+/).map(parseNumber).filter((value) => !Number.isNaN(value));
          const resultBox = document.getElementById("stats-result");
          if (numbers.length === 0) {
            resultBox.innerHTML = emptyDisplay("Média", "Digite pelo menos um número.");
            return;
          }
          const sorted = [...numbers].sort((first, second) => first - second);
          const total = numbers.reduce((sum, value) => sum + value, 0);
          const middle = Math.floor(sorted.length / 2);
          const median = sorted.length % 2 === 0 ? (sorted[middle - 1] + sorted[middle]) / 2 : sorted[middle];
          const frequency = new Map();
          numbers.forEach((value) => frequency.set(value, (frequency.get(value) || 0) + 1));
          const highestFrequency = Math.max(...frequency.values());
          const modes = [...frequency].filter(([, count]) => count === highestFrequency && highestFrequency > 1).map(([value]) => formatNumber(value));
          resultBox.innerHTML = display("Média", formatNumber(total / numbers.length, 4), "", [
            ["Mediana", formatNumber(median, 4)],
            ["Moda", modes.length ? modes.join(" · ") : "não há"],
            ["Soma", formatNumber(total, 4)],
            ["Quantidade", numbers.length],
            ["Menor", formatNumber(sorted[0], 4)],
            ["Maior", formatNumber(sorted[sorted.length - 1], 4)],
          ]);
        }
        onInputs(["stats-input"], calculate);
      },
    },

    "fracoes": {
      html: `        <div class="calculator-body">
          <div class="sentence">
            <input id="fraction-a" inputmode="numeric" value="3" aria-label="Numerador 1"> / <input id="fraction-b" inputmode="numeric" value="4" aria-label="Denominador 1">
            <select id="fraction-operation" aria-label="Operação"><option value="+">+</option><option value="-">−</option><option value="*">×</option><option value="/">÷</option></select>
            <input id="fraction-c" inputmode="numeric" value="5" aria-label="Numerador 2"> / <input id="fraction-d" inputmode="numeric" value="6" aria-label="Denominador 2">
          </div>
        </div>
        <div id="fraction-result"></div>`,
      setup() {
        function calculate() {
          const [numeratorA, denominatorA, numeratorB, denominatorB] = ["fraction-a", "fraction-b", "fraction-c", "fraction-d"].map((id) => parseInt(document.getElementById(id).value, 10));
          const operation = document.getElementById("fraction-operation").value;
          const resultBox = document.getElementById("fraction-result");
          if ([numeratorA, denominatorA, numeratorB, denominatorB].some(Number.isNaN) || denominatorA === 0 || denominatorB === 0) {
            resultBox.innerHTML = emptyDisplay("Resultado", "Use números inteiros e denominadores diferentes de zero.");
            return;
          }
          let numerator;
          let denominator;
          if (operation === "+") { numerator = numeratorA * denominatorB + numeratorB * denominatorA; denominator = denominatorA * denominatorB; }
          if (operation === "-") { numerator = numeratorA * denominatorB - numeratorB * denominatorA; denominator = denominatorA * denominatorB; }
          if (operation === "*") { numerator = numeratorA * numeratorB; denominator = denominatorA * denominatorB; }
          if (operation === "/") { numerator = numeratorA * denominatorB; denominator = denominatorA * numeratorB; }
          if (denominator === 0) {
            resultBox.innerHTML = emptyDisplay("Resultado", "Não dá para dividir por zero.");
            return;
          }
          const divisor = greatestCommonDivisor(numerator, denominator) || 1;
          const sign = denominator < 0 ? -1 : 1;
          const simpleNumerator = (numerator / divisor) * sign;
          const simpleDenominator = Math.abs(denominator / divisor);
          const wholePart = Math.trunc(simpleNumerator / simpleDenominator);
          const remainder = Math.abs(simpleNumerator % simpleDenominator);
          const mixed = wholePart !== 0 && remainder !== 0 ? `${wholePart} e ${remainder}/${simpleDenominator}` : "—";
          resultBox.innerHTML = display("Resultado", simpleDenominator === 1 ? `${simpleNumerator}` : `${simpleNumerator}/${simpleDenominator}`, "já simplificado", [
            ["Decimal", formatNumber(simpleNumerator / simpleDenominator, 6)],
            ["Número misto", mixed],
          ]);
        }
        onInputs(["fraction-a", "fraction-b", "fraction-c", "fraction-d", "fraction-operation"], calculate);
      },
    },

    "mmc-mdc": {
      html: `        <div class="calculator-body">
          <div class="field"><label for="mmc-input">Números inteiros</label><input id="mmc-input" value="12, 18, 30"><span class="field-hint">Separe por vírgula ou espaço.</span></div>
        </div>
        <div id="mmc-result"></div>`,
      setup() {
        function calculate() {
          const numbers = document.getElementById("mmc-input").value.split(/[,;\s]+/).map((part) => parseInt(part, 10)).filter((value) => Number.isInteger(value) && value > 0);
          const resultBox = document.getElementById("mmc-result");
          if (numbers.length < 2) {
            resultBox.innerHTML = emptyDisplay("MMC", "Digite pelo menos dois números inteiros positivos.");
            return;
          }
          const mdc = numbers.reduce((current, value) => greatestCommonDivisor(current, value));
          const mmc = numbers.reduce((current, value) => (current * value) / greatestCommonDivisor(current, value));
          resultBox.innerHTML = display("MMC", formatNumber(mmc, 0), `de ${numbers.join(", ")}`, [["MDC", formatNumber(mdc, 0)]]);
        }
        onInputs(["mmc-input"], calculate);
      },
    },

    "bhaskara": {
      html: `        <div class="calculator-body">
          <div class="sentence"><input id="quadratic-a" inputmode="decimal" value="1" aria-label="a"> x² + <input id="quadratic-b" inputmode="decimal" value="-5" aria-label="b"> x + <input id="quadratic-c" inputmode="decimal" value="6" aria-label="c"> = 0</div>
        </div>
        <div id="quadratic-result"></div>`,
      setup() {
        function calculate() {
          const [coefficientA, coefficientB, coefficientC] = ["quadratic-a", "quadratic-b", "quadratic-c"].map((id) => parseNumber(document.getElementById(id).value));
          const resultBox = document.getElementById("quadratic-result");
          if ([coefficientA, coefficientB, coefficientC].some(Number.isNaN) || coefficientA === 0) {
            resultBox.innerHTML = emptyDisplay("Raízes", "Preencha a, b e c (o a não pode ser zero).");
            return;
          }
          const delta = coefficientB * coefficientB - 4 * coefficientA * coefficientC;
          if (delta < 0) {
            resultBox.innerHTML = display("Raízes", "sem raízes reais", `Δ = ${formatNumber(delta, 4)} (negativo)`);
            return;
          }
          const firstRoot = (-coefficientB + Math.sqrt(delta)) / (2 * coefficientA);
          const secondRoot = (-coefficientB - Math.sqrt(delta)) / (2 * coefficientA);
          resultBox.innerHTML = display("Raízes", delta === 0 ? `x = ${formatNumber(firstRoot, 4)}` : `x₁ = ${formatNumber(firstRoot, 4)} · x₂ = ${formatNumber(secondRoot, 4)}`, `Δ = ${formatNumber(delta, 4)}`);
        }
        onInputs(["quadratic-a", "quadratic-b", "quadratic-c"], calculate);
      },
    },

    "area": {
      html: `        <div class="calculator-body">
          <div class="segmented" id="area-shapes" role="group" aria-label="Figura">
            <button type="button" data-value="square" aria-pressed="true">Quadrado</button>
            <button type="button" data-value="rectangle" aria-pressed="false">Retângulo</button>
            <button type="button" data-value="triangle" aria-pressed="false">Triângulo</button>
            <button type="button" data-value="circle" aria-pressed="false">Círculo</button>
            <button type="button" data-value="trapezoid" aria-pressed="false">Trapézio</button>
          </div>
          <div class="field-row" id="area-fields"></div>
        </div>
        <div id="area-result"></div>`,
      setup() {
        const shapes = {
          square: { fields: [["side", "Lado"]], formula: "lado²", area: (values) => values.side ** 2 },
          rectangle: { fields: [["base", "Base"], ["height", "Altura"]], formula: "base × altura", area: (values) => values.base * values.height },
          triangle: { fields: [["base", "Base"], ["height", "Altura"]], formula: "base × altura ÷ 2", area: (values) => (values.base * values.height) / 2 },
          circle: { fields: [["radius", "Raio"]], formula: "π × raio²", area: (values) => Math.PI * values.radius ** 2 },
          trapezoid: { fields: [["bigBase", "Base maior"], ["smallBase", "Base menor"], ["height", "Altura"]], formula: "(B + b) × h ÷ 2", area: (values) => ((values.bigBase + values.smallBase) * values.height) / 2 },
        };
        let currentShape = "square";
        function calculate() {
          const shape = shapes[currentShape];
          const values = {};
          shape.fields.forEach(([fieldKey]) => { values[fieldKey] = parseNumber(document.getElementById(`area-${fieldKey}`).value); });
          const resultBox = document.getElementById("area-result");
          if (Object.values(values).some((value) => Number.isNaN(value) || value < 0)) {
            resultBox.innerHTML = emptyDisplay("Área", "Preencha as medidas com números positivos.");
            return;
          }
          resultBox.innerHTML = display("Área", `${formatNumber(shape.area(values), 4)} m²`, `fórmula: ${shape.formula} (use a mesma unidade em todas as medidas)`);
        }
        function renderFields() {
          document.getElementById("area-fields").innerHTML = shapes[currentShape].fields
            .map(([fieldKey, fieldLabel]) => `<div class="field"><label for="area-${fieldKey}">${fieldLabel} (m)</label><input id="area-${fieldKey}" inputmode="decimal" value="5"></div>`).join("");
          document.querySelectorAll("#area-fields input").forEach((field) => field.addEventListener("input", calculate));
          calculate();
        }
        setupSegmented("area-shapes", (newShape) => { currentShape = newShape; renderFields(); });
        renderFields();
      },
    },

    "juros-compostos": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="compound-initial">Valor inicial (R$)</label><input id="compound-initial" inputmode="numeric" data-money value="1.000,00"></div>
            <div class="field"><label for="compound-monthly">Aporte mensal (R$)</label><input id="compound-monthly" inputmode="numeric" data-money value="300,00"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="compound-rate">Taxa de juros (%)</label><input id="compound-rate" inputmode="decimal" value="0,9"></div>
            <div class="field"><label for="compound-rate-period">Taxa ao</label><select id="compound-rate-period"><option value="month">mês</option><option value="year">ano</option></select></div>
            <div class="field"><label for="compound-time">Tempo</label><input id="compound-time" inputmode="numeric" value="5"></div>
            <div class="field"><label for="compound-time-unit">Em</label><select id="compound-time-unit"><option value="year">anos</option><option value="month">meses</option></select></div>
          </div>
        </div>
        <div id="compound-result"></div>`,
      setup() {
        function calculate() {
          const initialAmount = parseNumber(document.getElementById("compound-initial").value) || 0;
          const monthlyDeposit = parseNumber(document.getElementById("compound-monthly").value) || 0;
          const ratePercent = parseNumber(document.getElementById("compound-rate").value);
          const timeValue = parseNumber(document.getElementById("compound-time").value);
          const resultBox = document.getElementById("compound-result");
          if (Number.isNaN(ratePercent) || Number.isNaN(timeValue) || timeValue <= 0) {
            resultBox.innerHTML = emptyDisplay("Valor final", "Preencha a taxa e o tempo.");
            return;
          }
          // Taxa anual vira mensal equivalente: (1 + anual)^(1/12) − 1
          const isYearlyRate = document.getElementById("compound-rate-period").value === "year";
          const monthlyRate = isYearlyRate ? Math.pow(1 + ratePercent / 100, 1 / 12) - 1 : ratePercent / 100;
          const totalMonths = Math.round(document.getElementById("compound-time-unit").value === "year" ? timeValue * 12 : timeValue);
          const growth = Math.pow(1 + monthlyRate, totalMonths);
          const depositsValue = monthlyRate === 0 ? monthlyDeposit * totalMonths : monthlyDeposit * ((growth - 1) / monthlyRate);
          const finalAmount = initialAmount * growth + depositsValue;
          const totalInvested = initialAmount + monthlyDeposit * totalMonths;
          const investedShare = finalAmount > 0 ? Math.min(100, (totalInvested / finalAmount) * 100) : 100;
          resultBox.innerHTML = display(`Valor final em ${totalMonths} meses`, formatMoney(finalAmount), "", [
            ["Total investido", formatMoney(totalInvested)],
            ["Juros ganhos", formatMoney(finalAmount - totalInvested)],
          ], `<div class="split-bar" aria-hidden="true"><i class="split-first" style="width:${investedShare}%"></i><i class="split-second" style="width:${100 - investedShare}%"></i></div>
              <div class="split-legend"><span>▮ investido ${formatNumber(investedShare, 0)}%</span><span>▮ juros ${formatNumber(100 - investedShare, 0)}%</span></div>`);
        }
        onInputs(["compound-initial", "compound-monthly", "compound-rate", "compound-rate-period", "compound-time", "compound-time-unit"], calculate);
      },
    },

    "juros-simples": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="simple-capital">Capital (R$)</label><input id="simple-capital" inputmode="numeric" data-money value="5.000,00"></div>
            <div class="field"><label for="simple-rate">Taxa (% ao mês)</label><input id="simple-rate" inputmode="decimal" value="2"></div>
            <div class="field"><label for="simple-months">Período (meses)</label><input id="simple-months" inputmode="numeric" value="6"></div>
          </div>
        </div>
        <div id="simple-result"></div>`,
      setup() {
        function calculate() {
          const [capital, ratePercent, months] = ["simple-capital", "simple-rate", "simple-months"].map((id) => parseNumber(document.getElementById(id).value));
          const resultBox = document.getElementById("simple-result");
          if ([capital, ratePercent, months].some(Number.isNaN)) {
            resultBox.innerHTML = emptyDisplay("Montante", "Preencha todos os campos.");
            return;
          }
          const interest = capital * (ratePercent / 100) * months;
          resultBox.innerHTML = display("Montante", formatMoney(capital + interest), "", [["Juros", formatMoney(interest)], ["Juros por mês", formatMoney(interest / (months || 1))]]);
        }
        onInputs(["simple-capital", "simple-rate", "simple-months"], calculate);
      },
    },

    "investimentos": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="invest-initial">Valor inicial (R$)</label><input id="invest-initial" inputmode="numeric" data-money value="10.000,00"></div>
            <div class="field"><label for="invest-monthly">Aporte mensal (R$)</label><input id="invest-monthly" inputmode="numeric" data-money value="500,00"></div>
            <div class="field"><label for="invest-months">Prazo (meses)</label><input id="invest-months" inputmode="numeric" value="24"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="invest-cdi">CDI (% ao ano)</label><input id="invest-cdi" inputmode="decimal" value="13,65"></div>
            <div class="field"><label for="invest-cdb">CDB (% do CDI)</label><input id="invest-cdb" inputmode="decimal" value="100"></div>
            <div class="field"><label for="invest-lci">LCI/LCA (% do CDI)</label><input id="invest-lci" inputmode="decimal" value="90"></div>
            <div class="field"><label for="invest-savings">Poupança (% ao mês)</label><input id="invest-savings" inputmode="decimal" value="0,67"></div>
          </div>
          <p class="field-hint" id="invest-rates-hint">CDI e poupança: valores de referência; atualizando com o Banco Central…</p>
        </div>
        <div id="invest-result"></div>`,
      setup() {
        const SELIC_ABOVE_CDI = 0.10; // Selic fica ~0,10 ponto acima do CDI
        const TREASURY_CUSTODY_YEARLY = 0.002; // taxa de custódia B3 do Tesouro Selic: 0,20% ao ano
        const TREASURY_CUSTODY_FREE_UP_TO = 10000; // isento até R$ 10 mil por CPF (cobra só sobre o que passar)
        const LCI_MIN_MONTHS = 6; // carência mínima das LCI/LCA atreladas ao CDI (Resolução CMN 5.215/2025)

        // IR da renda fixa (tabela regressiva), pelo número de dias aplicados
        function incomeTaxRate(days) {
          if (days <= 180) {
            return 0.225;
          }
          if (days <= 360) {
            return 0.20;
          }
          return days <= 720 ? 0.175 : 0.15;
        }
        // CDI vale por dia útil (252 no ano): % do CDI se aplica à taxa diária; 21 dias úteis por mês
        function monthlyRateFromCdi(cdiYearly, percentOfCdi) {
          const dailyRate = (Math.pow(1 + cdiYearly, 1 / 252) - 1) * percentOfCdi;
          return Math.pow(1 + dailyRate, 21) - 1;
        }
        // Cada depósito (o inicial e cada aporte) rende pelo tempo que ficou aplicado e tem a sua alíquota de IR
        function simulate({ monthlyRate, months, initial, monthly, hasIncomeTax, custody = false }) {
          const deposits = [{ amount: initial, monthsInvested: months }];
          for (let month = 1; month <= months; month++) {
            deposits.push({ amount: monthly, monthsInvested: months - month });
          }
          let gross = 0;
          let incomeTax = 0;
          deposits.forEach(({ amount, monthsInvested }) => {
            const finalValue = amount * Math.pow(1 + monthlyRate, monthsInvested);
            gross += finalValue;
            if (hasIncomeTax) {
              incomeTax += (finalValue - amount) * incomeTaxRate(Math.round((monthsInvested * 365) / 12));
            }
          });
          // Custódia do Tesouro: mês a mês sobre o saldo acima de R$ 10 mil, paga no resgate
          let fees = 0;
          if (custody) {
            let balance = initial;
            for (let month = 1; month <= months; month++) {
              balance = balance * (1 + monthlyRate);
              fees += Math.max(0, balance - TREASURY_CUSTODY_FREE_UP_TO) * (TREASURY_CUSTODY_YEARLY / 12);
              balance += monthly;
            }
          }
          return { gross, discounts: incomeTax + fees, net: gross - incomeTax - fees };
        }

        function calculate() {
          const initial = parseNumber(document.getElementById("invest-initial").value) || 0;
          const monthly = parseNumber(document.getElementById("invest-monthly").value) || 0;
          const months = Math.round(parseNumber(document.getElementById("invest-months").value));
          const [cdi, cdbPercent, lciPercent, savingsRate] = ["invest-cdi", "invest-cdb", "invest-lci", "invest-savings"].map((id) => parseNumber(document.getElementById(id).value));
          const resultBox = document.getElementById("invest-result");
          if (!(months > 0) || months > 600 || initial + monthly <= 0 || [cdi, cdbPercent, lciPercent, savingsRate].some(Number.isNaN)) {
            resultBox.innerHTML = emptyDisplay("Qual rende mais", "Preencha valor, prazo (até 600 meses) e as taxas.");
            return;
          }
          const invested = initial + monthly * months;
          const base = { months, initial, monthly };
          const options = [
            { name: "CDB", note: `${formatNumber(cdbPercent, 1)}% do CDI · com IR`, ...simulate({ ...base, monthlyRate: monthlyRateFromCdi(cdi / 100, cdbPercent / 100), hasIncomeTax: true }) },
            { name: "LCI / LCA", note: `${formatNumber(lciPercent, 1)}% do CDI · isenta de IR`, available: months >= LCI_MIN_MONTHS, ...simulate({ ...base, monthlyRate: monthlyRateFromCdi(cdi / 100, lciPercent / 100), hasIncomeTax: false }) },
            { name: "Tesouro Selic", note: "100% da Selic · IR e custódia", ...simulate({ ...base, monthlyRate: monthlyRateFromCdi((cdi + SELIC_ABOVE_CDI) / 100, 1), hasIncomeTax: true, custody: true }) },
            { name: "Poupança", note: `${formatNumber(savingsRate, 2)}% ao mês · isenta de IR`, ...simulate({ ...base, monthlyRate: savingsRate / 100, hasIncomeTax: false }) },
          ];
          const comparable = options.filter((option) => option.available !== false);
          const best = comparable.reduce((winner, option) => (option.net > winner.net ? option : winner));
          const savings = options[3];
          const rows = options.map((option) => {
            const isBest = option === best;
            const unavailable = option.available === false;
            return `<tr${isBest ? ' class="is-best"' : ""}><td><b>${option.name}</b>${isBest ? " 🏆" : ""}<br><small>${unavailable ? `exige pelo menos ${LCI_MIN_MONTHS} meses` : option.note}</small></td>`
              + `<td><b>${unavailable ? "—" : formatMoney(option.net)}</b></td><td>${unavailable ? "—" : formatMoney(option.discounts)}</td><td>${unavailable ? "—" : formatMoney(option.gross)}</td></tr>`;
          }).join("");
          resultBox.innerHTML = display(
            `Rende mais em ${months} meses: ${best.name}`,
            formatMoney(best.net),
            `valor líquido para resgatar · ${formatMoney(best.net - invested)} de rendimento sobre ${formatMoney(invested)} investidos`,
            [
              ["Rendimento líquido", formatMoney(best.net - invested)],
              [best === savings ? "Vantagem sobre o 2º" : "A mais que a poupança", formatMoney(best === savings ? best.net - Math.max(...comparable.filter((option) => option !== best).map((option) => option.net)) : best.net - savings.net)],
            ],
            `<div class="invest-table-scroll"><table class="data-table invest-table"><thead><tr><th>Investimento</th><th>Líquido</th><th>IR e taxas</th><th>Bruto</th></tr></thead><tbody>${rows}</tbody></table></div>`,
          );
        }
        onInputs(["invest-initial", "invest-monthly", "invest-months", "invest-cdi", "invest-cdb", "invest-lci", "invest-savings"], calculate);

        // CDI e poupança atuais (Banco Central, pela faixa de indicadores do site); não troca o que a pessoa já digitou
        const typedByPerson = new Set();
        ["invest-cdi", "invest-savings"].forEach((id) => document.getElementById(id).addEventListener("input", () => typedByPerson.add(id), { once: true }));
        const ratesHint = document.getElementById("invest-rates-hint");
        fetch("/api/indicadores", { headers: { Accept: "application/json" }, credentials: "same-origin" })
          .then((response) => (response.ok ? response.json() : Promise.reject()))
          .then((data) => {
            const parts = [];
            if (data.cdi && !typedByPerson.has("invest-cdi")) {
              document.getElementById("invest-cdi").value = formatNumber(data.cdi.yearly, 2);
              parts.push(`CDI de ${formatNumber(data.cdi.yearly, 2)}% ao ano (${data.cdi.date})`);
            }
            if (data.savings && !typedByPerson.has("invest-savings")) {
              document.getElementById("invest-savings").value = formatNumber(data.savings.monthly, 2);
              parts.push(`poupança de ${formatNumber(data.savings.monthly, 2)}% no último mês`);
            }
            ratesHint.textContent = parts.length ? `Taxas atuais do Banco Central: ${parts.join(" e ")}. Pode mudar para simular outros cenários.` : "Pode mudar as taxas para simular outros cenários.";
            calculate();
          })
          .catch(() => {
            ratesHint.textContent = "Não foi possível buscar as taxas de hoje; confira o CDI e a poupança e ajuste se precisar.";
          });
      },
    },

    "fgts": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="fgts-salary">Salário bruto (R$)</label><input id="fgts-salary" inputmode="numeric" data-money value="3.000,00"></div>
            <div class="field"><label for="fgts-balance">Saldo atual do FGTS (R$)</label><input id="fgts-balance" inputmode="numeric" data-money value="5.000,00"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="fgts-months">Daqui a quantos meses</label><input id="fgts-months" inputmode="numeric" value="12"></div>
            <div class="field"><label for="fgts-type">Contrato</label><select id="fgts-type"><option value="0.08">CLT · 8% do salário</option><option value="0.02">Jovem aprendiz · 2%</option></select></div>
            <div class="field"><label for="fgts-extras">13º e férias</label><select id="fgts-extras"><option value="yes">Somar o FGTS do 13º e do 1/3 de férias</option><option value="no">Só o salário mensal</option></select></div>
          </div>
        </div>
        <div id="fgts-result"></div>`,
      setup() {
        const YEARLY_RETURN = 0.03; // remuneração mínima por lei: 3% ao ano + TR (TR e distribuição de lucros ficam de fora)
        function calculate() {
          const salary = parseNumber(document.getElementById("fgts-salary").value) || 0;
          const currentBalance = parseNumber(document.getElementById("fgts-balance").value) || 0;
          const months = Math.round(parseNumber(document.getElementById("fgts-months").value));
          const depositRate = Number(document.getElementById("fgts-type").value);
          const withExtras = document.getElementById("fgts-extras").value === "yes";
          const resultBox = document.getElementById("fgts-result");
          if (!(months >= 0) || months > 480 || salary + currentBalance <= 0) {
            resultBox.innerHTML = emptyDisplay("Saldo do FGTS", "Preencha o salário ou o saldo e o prazo (até 480 meses).");
            return;
          }
          const monthlyReturn = Math.pow(1 + YEARLY_RETURN, 1 / 12) - 1;
          const monthlyDeposit = salary * depositRate;
          // Uma vez por ano: FGTS sobre o 13º (um salário) e sobre o 1/3 de férias
          const yearlyExtra = withExtras ? salary * depositRate * (1 + 1 / 3) : 0;
          let balance = currentBalance;
          let deposited = 0;
          for (let month = 1; month <= months; month++) {
            balance = balance * (1 + monthlyReturn) + monthlyDeposit;
            deposited += monthlyDeposit;
            if (month % 12 === 0) {
              balance += yearlyExtra;
              deposited += yearlyExtra;
            }
          }
          const earnings = balance - currentBalance - deposited;
          // Multa de 40%: sobre tudo o que foi depositado no emprego atual (aqui: o saldo, como aproximação)
          const fine = balance * 0.4;
          resultBox.innerHTML = display(
            months === 0 ? "Saldo do FGTS hoje" : `Saldo do FGTS em ${months} ${months === 1 ? "mês" : "meses"}`,
            formatMoney(balance),
            `depósito de ${formatMoney(monthlyDeposit)} por mês (${formatNumber(depositRate * 100, 0)}% do salário), pago pela empresa`,
            [
              ["Depósitos no período", formatMoney(deposited)],
              ["Rendimento (3% ao ano)", formatMoney(earnings)],
              ["Multa de 40% na demissão", formatMoney(fine)],
              ["Saque na demissão sem justa causa", formatMoney(balance + fine)],
            ],
            `<p class="field-hint">Quem optou pelo saque-aniversário recebe só a multa de 40% na demissão; o saldo fica guardado. Veja também a <a href="/calculadoras/saque-aniversario-fgts">calculadora do saque-aniversário</a>.</p>`,
          );
        }
        onInputs(["fgts-salary", "fgts-balance", "fgts-months", "fgts-type", "fgts-extras"], calculate);
      },
    },

    "saque-aniversario-fgts": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="anniversary-balance">Saldo total do FGTS (R$)</label><input id="anniversary-balance" inputmode="numeric" data-money value="8.000,00"></div>
            <div class="field"><label for="anniversary-month">Mês do seu aniversário</label><select id="anniversary-month">
              <option value="0">janeiro</option><option value="1">fevereiro</option><option value="2">março</option><option value="3">abril</option>
              <option value="4">maio</option><option value="5">junho</option><option value="6">julho</option><option value="7">agosto</option>
              <option value="8">setembro</option><option value="9">outubro</option><option value="10">novembro</option><option value="11">dezembro</option>
            </select></div>
          </div>
          <p class="field-hint">Some o saldo de todas as contas do FGTS (empregos atuais e antigos), como aparece no app FGTS.</p>
        </div>
        <div id="anniversary-result"></div>`,
      setup() {
        // Tabela do saque-aniversário (Lei 13.932/2019; Caixa): alíquota sobre o saldo + parcela adicional
        const BRACKETS = [
          { upTo: 500, rate: 0.50, extra: 0 },
          { upTo: 1000, rate: 0.40, extra: 50 },
          { upTo: 5000, rate: 0.30, extra: 150 },
          { upTo: 10000, rate: 0.20, extra: 650 },
          { upTo: 15000, rate: 0.15, extra: 1150 },
          { upTo: 20000, rate: 0.10, extra: 1900 },
          { upTo: Infinity, rate: 0.05, extra: 2900 },
        ];
        const MONTH_NAMES = ["janeiro", "fevereiro", "março", "abril", "maio", "junho", "julho", "agosto", "setembro", "outubro", "novembro", "dezembro"];
        // Mês atual vem selecionado
        document.getElementById("anniversary-month").value = String(new Date().getMonth());

        function calculate() {
          const balance = parseNumber(document.getElementById("anniversary-balance").value) || 0;
          const birthMonth = Number(document.getElementById("anniversary-month").value);
          const resultBox = document.getElementById("anniversary-result");
          if (balance <= 0) {
            resultBox.innerHTML = emptyDisplay("Saque-aniversário", "Digite o saldo do FGTS.");
            return;
          }
          const bracket = BRACKETS.find((item) => balance <= item.upTo);
          const withdrawal = Math.min(balance, balance * bracket.rate + bracket.extra);
          // Prazo para sacar: do mês do aniversário até o fim do 2º mês seguinte
          const lastMonth = MONTH_NAMES[(birthMonth + 2) % 12];
          const bracketLabel = bracket.upTo === Infinity ? "acima de R$ 20.000,00" : `até ${formatMoney(bracket.upTo)}`;
          resultBox.innerHTML = display(
            "Você pode sacar por ano",
            formatMoney(withdrawal),
            `${formatNumber(bracket.rate * 100, 0)}% do saldo${bracket.extra ? ` + ${formatMoney(bracket.extra)} de parcela adicional` : ""} (faixa ${bracketLabel})`,
            [
              ["Fica na conta", formatMoney(balance - withdrawal)],
              ["Prazo para sacar", `do 1º dia útil de ${MONTH_NAMES[birthMonth]} ao último dia útil de ${lastMonth}`],
            ],
            `<p class="field-hint"><b>Atenção:</b> quem está no saque-aniversário e é demitido sem justa causa recebe a multa de 40%, mas não saca o saldo. Para voltar ao saque-rescisão, o pedido só vale depois de 24 meses.</p>`,
          );
        }
        onInputs(["anniversary-balance", "anniversary-month"], calculate);
      },
    },

    "correcao-monetaria": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="correction-amount">Valor (R$)</label><input id="correction-amount" inputmode="numeric" data-money value="1.000,00"></div>
            <div class="field"><label for="correction-index">Índice</label><select id="correction-index">
              <option value="ipca">IPCA · inflação oficial</option>
              <option value="igpm">IGP-M · aluguel</option>
              <option value="inpc">INPC · salários e INSS</option>
              <option value="igpdi">IGP-DI · contratos</option>
              <option value="selic">Selic · juros básicos</option>
              <option value="cdi">CDI · renda fixa</option>
              <option value="poupanca">Poupança</option>
            </select></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="correction-start">Mês inicial</label><input id="correction-start" type="month" placeholder="aaaa-mm"></div>
            <div class="field"><label for="correction-end">Mês final</label><input id="correction-end" type="month" placeholder="aaaa-mm"></div>
          </div>
          <div class="button-row" id="correction-shortcuts">
            <button type="button" class="secondary-button" data-months="12">Últimos 12 meses</button>
            <button type="button" class="secondary-button" data-months="24">24 meses</button>
            <button type="button" class="secondary-button" data-months="60">5 anos</button>
          </div>
          <span class="field-hint" id="correction-availability">Carregando o índice…</span>
        </div>
        <div id="correction-result"></div>
        <div class="calculator-body" id="correction-details" hidden></div>`,
      setup() {
        const MONTH_NAMES = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];
        const indexSelect = document.getElementById("correction-index");
        const startInput = document.getElementById("correction-start");
        const endInput = document.getElementById("correction-end");
        const resultBox = document.getElementById("correction-result");
        const detailsBox = document.getElementById("correction-details");
        const availability = document.getElementById("correction-availability");
        const seriesCache = {}; // índices já baixados nesta visita
        let series = null;
        // "Últimos N meses": ao trocar de índice, o período acompanha o último mês divulgado do novo índice.
        // Se a pessoa digitar os meses, eles ficam como estão.
        let followLatest = 0;
        // "pelo IPCA", "pela Selic", "pela poupança"
        const byIndex = (indexKey, name) => ({ selic: "pela Selic", poupanca: "pela poupança" }[indexKey] || `pelo ${name}`);

        const monthLabel = (month) => `${MONTH_NAMES[Number(month.slice(5, 7)) - 1]}/${month.slice(0, 4)}`;
        const shiftMonth = (month, delta) => {
          const date = new Date(Number(month.slice(0, 4)), Number(month.slice(5, 7)) - 1 + delta, 1);
          return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
        };
        const percent = (rate) => `${rate > 0 ? "+" : ""}${formatNumber(rate * 100, 2)}%`;

        function setPeriod(monthCount) {
          followLatest = monthCount;
          const last = series.months[series.months.length - 1][0];
          endInput.value = last;
          startInput.value = shiftMonth(last, -(monthCount - 1));
          calculate();
        }

        async function loadIndex() {
          const indexKey = indexSelect.value;
          availability.textContent = "Carregando o índice…";
          resultBox.innerHTML = emptyDisplay("Valor corrigido", "Carregando o índice do Banco Central…");
          detailsBox.hidden = true;
          try {
            if (!seriesCache[indexKey]) {
              const response = await fetch(`/api/indices/serie?${new URLSearchParams({ indice: indexKey })}`, { headers: { Accept: "application/json" }, credentials: "same-origin" });
              const data = await response.json().catch(() => ({}));
              if (!response.ok) {
                throw new Error(data.error || "O Banco Central não respondeu agora.");
              }
              seriesCache[indexKey] = data;
            }
          } catch (error) {
            series = null;
            availability.textContent = "";
            resultBox.innerHTML = emptyDisplay("Valor corrigido", escapeHtml(error.message));
            return;
          }
          if (indexSelect.value !== indexKey) {
            return; // a pessoa já escolheu outro índice
          }
          series = seriesCache[indexKey];
          const first = series.months[0][0];
          const last = series.months[series.months.length - 1][0];
          startInput.min = endInput.min = first;
          startInput.max = endInput.max = last;
          availability.textContent = `${series.name}: ${series.description}. Dados de ${monthLabel(first)} a ${monthLabel(last)} (Banco Central).`;
          // Primeira vez ou período fora do que existe para este índice: últimos 12 meses
          if (followLatest || !startInput.value || !endInput.value || startInput.value < first || endInput.value > last) {
            setPeriod(followLatest || 12);
          } else {
            calculate();
          }
        }

        function calculate() {
          if (!series) {
            return;
          }
          const amount = parseNumber(document.getElementById("correction-amount").value);
          let start = startInput.value;
          let end = endInput.value;
          if (!(amount > 0) || !/^\d{4}-\d{2}$/.test(start) || !/^\d{4}-\d{2}$/.test(end)) {
            resultBox.innerHTML = emptyDisplay("Valor corrigido", "Informe o valor e os meses inicial e final.");
            detailsBox.hidden = true;
            return;
          }
          if (start > end) {
            [start, end] = [end, start];
          }
          // Variações do mês inicial ao mês final (os dois entram na conta)
          const months = series.months.filter(([month]) => month >= start && month <= end);
          if (months.length === 0) {
            resultBox.innerHTML = emptyDisplay("Valor corrigido", `Não há dados do ${escapeHtml(series.name)} nesse período.`);
            detailsBox.hidden = true;
            return;
          }
          const factor = months.reduce((product, [, rate]) => product * (1 + rate / 100), 1);
          const corrected = amount * factor;
          const realStart = months[0][0];
          const realEnd = months[months.length - 1][0];
          const averageMonthly = Math.pow(factor, 1 / months.length) - 1;
          const cutNote = realEnd < end ? ` (os meses depois de ${monthLabel(realEnd)} ainda não foram divulgados)` : "";
          resultBox.innerHTML = display("Valor corrigido", formatMoney(corrected), `${formatMoney(amount)} corrigido ${escapeHtml(byIndex(indexSelect.value, series.name))} de ${monthLabel(realStart)} a ${monthLabel(realEnd)} · ${months.length} ${months.length === 1 ? "mês" : "meses"}${cutNote}`, [
            ["Variação acumulada", percent(factor - 1)],
            [corrected >= amount ? "Aumento" : "Redução", formatMoney(Math.abs(corrected - amount))],
            ["Média por mês", percent(averageMonthly)],
            ["Fator de correção", formatNumber(factor, 6)],
          ]);

          // Ano a ano dentro do período escolhido
          const byYear = new Map();
          months.forEach(([month, rate]) => {
            const year = month.slice(0, 4);
            byYear.set(year, (byYear.get(year) || 1) * (1 + rate / 100));
          });
          let runningValue = amount;
          const yearRows = [...byYear].map(([year, yearFactor]) => {
            runningValue *= yearFactor;
            const count = months.filter(([month]) => month.startsWith(year)).length;
            return `<tr><td>${year}${count < 12 ? ` <small>(${count} ${count === 1 ? "mês" : "meses"})</small>` : ""}</td><td>${percent(yearFactor - 1)}</td><td>${formatMoney(runningValue)}</td></tr>`;
          }).join("");
          const monthRows = months.map(([month, rate]) => `<tr><td>${monthLabel(month)}</td><td>${formatNumber(rate, 2)}%</td></tr>`).join("");
          detailsBox.hidden = false;
          detailsBox.innerHTML = `<h3>Ano a ano</h3>
            <table class="data-table payslip"><thead><tr><th>Ano</th><th>Variação</th><th>Valor no fim do ano</th></tr></thead><tbody>${yearRows}</tbody></table>
            <details class="correction-months"><summary>Ver a variação de cada mês (${months.length})</summary>
              <table class="data-table payslip"><thead><tr><th>Mês</th><th>${escapeHtml(series.name)}</th></tr></thead><tbody>${monthRows}</tbody></table>
            </details>
            <p class="notice">Correção composta (juros sobre juros), incluindo a variação do mês inicial e do mês final. Em contratos de aluguel, o reajuste anual costuma usar o acumulado de 12 meses do índice do contrato divulgado antes do aniversário. Fonte: Banco Central (SGS).</p>`;
        }

        indexSelect.addEventListener("change", loadIndex);
        document.getElementById("correction-shortcuts").addEventListener("click", (clickEvent) => {
          const button = clickEvent.target.closest("[data-months]");
          if (button && series) {
            setPeriod(Number(button.dataset.months));
          }
        });
        document.getElementById("correction-amount").addEventListener("input", calculate);
        [startInput, endInput].forEach((input) => input.addEventListener("input", () => { followLatest = 0; calculate(); }));
        loadIndex();
      },
    },

    "financiamento": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="loan-value">Valor do bem (R$)</label><input id="loan-value" inputmode="numeric" data-money value="80.000,00"></div>
            <div class="field"><label for="loan-down">Entrada (R$)</label><input id="loan-down" inputmode="numeric" data-money value="20.000,00"></div>
            <div class="field"><label for="loan-rate">Juros (% ao mês)</label><input id="loan-rate" inputmode="decimal" value="1,2"></div>
            <div class="field"><label for="loan-months">Prazo (meses)</label><input id="loan-months" inputmode="numeric" value="48"></div>
          </div>
        </div>
        <div id="loan-result"></div>`,
      setup() {
        function calculate() {
          const [assetValue, downPayment, ratePercent, months] = ["loan-value", "loan-down", "loan-rate", "loan-months"].map((id) => parseNumber(document.getElementById(id).value));
          const resultBox = document.getElementById("loan-result");
          const financed = assetValue - (downPayment || 0);
          if ([assetValue, ratePercent, months].some(Number.isNaN) || months <= 0 || financed <= 0) {
            resultBox.innerHTML = emptyDisplay("Parcela", "Confira os valores: a entrada precisa ser menor que o valor do bem.");
            return;
          }
          const rate = ratePercent / 100;
          const priceInstallment = rate === 0 ? financed / months : (financed * rate) / (1 - Math.pow(1 + rate, -months));
          const priceTotal = priceInstallment * months;
          const amortization = financed / months;
          const sacFirst = amortization + financed * rate;
          const sacLast = amortization + amortization * rate;
          // No SAC os juros caem linearmente: total = amortização + juros sobre o saldo de cada mês
          const sacTotal = financed + rate * amortization * (months * (months + 1)) / 2;
          resultBox.innerHTML = display("Parcela pela tabela Price", formatMoney(priceInstallment), `valor financiado: ${formatMoney(financed)}`, [
            ["Total Price", formatMoney(priceTotal)],
            ["Juros Price", formatMoney(priceTotal - financed)],
            ["1ª parcela SAC", formatMoney(sacFirst)],
            ["Última SAC", formatMoney(sacLast)],
            ["Total SAC", formatMoney(sacTotal)],
            ["Juros SAC", formatMoney(sacTotal - financed)],
          ]);
        }
        onInputs(["loan-value", "loan-down", "loan-rate", "loan-months"], calculate);
      },
    },

    "financiamento-veiculo": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="vehicle-price">Valor do veículo (R$)</label><input id="vehicle-price" inputmode="numeric" data-money value="90.000,00"></div>
            <div class="field"><label for="vehicle-down">Entrada (R$)</label><input id="vehicle-down" inputmode="numeric" data-money value="30.000,00"><span class="field-hint" id="vehicle-down-percent"></span></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="vehicle-months">Prazo</label><select id="vehicle-months"><option value="12">12 meses</option><option value="24">24 meses</option><option value="36">36 meses</option><option value="48" selected>48 meses</option><option value="60">60 meses</option><option value="72">72 meses</option></select></div>
            <div class="field"><label for="vehicle-rate">Juros (% ao mês)</label><input id="vehicle-rate" inputmode="decimal" value="1,9"></div>
            <div class="field"><label for="vehicle-fees">Tarifas financiadas (R$)</label><input id="vehicle-fees" inputmode="numeric" data-money value="0"></div>
          </div>
          <span class="field-hint">A taxa de juros é só um exemplo: use a que o banco ou a loja ofereceu. Tarifas = cadastro, registro do contrato, avaliação do veículo.</span>
          <label class="check"><input type="checkbox" id="vehicle-iof" checked> Incluir IOF (pessoa física)</label>
        </div>
        <div id="vehicle-result"></div>
        <div class="calculator-body" id="vehicle-table"></div>`,
      setup() {
        // IOF de operações de crédito para pessoa física (Decreto 6.306/2007):
        // 0,38% fixo sobre o valor + 0,0082% ao dia sobre cada parcela amortizada, contando no máximo 365 dias
        const IOF_FIXED_RATE = 0.0038;
        const IOF_DAILY_RATE = 0.000082;
        const IOF_MAX_DAYS = 365;
        const TERM_OPTIONS = [12, 24, 36, 48, 60, 72];

        function priceInstallment(principal, rate, months) {
          return rate === 0 ? principal / months : (principal * rate) / (1 - Math.pow(1 + rate, -months));
        }

        // O IOF entra no valor financiado, e o IOF depende do valor financiado.
        // Por isso o cálculo é repetido até o valor parar de mudar.
        function financedWithIof(baseAmount, rate, months) {
          let principal = baseAmount;
          let iof = 0;
          for (let attempt = 0; attempt < 30; attempt++) {
            const installment = priceInstallment(principal, rate, months);
            let balance = principal;
            let dailyIof = 0;
            for (let month = 1; month <= months; month++) {
              const amortization = installment - balance * rate;
              balance -= amortization;
              dailyIof += amortization * Math.min(month * 30, IOF_MAX_DAYS) * IOF_DAILY_RATE;
            }
            iof = principal * IOF_FIXED_RATE + dailyIof;
            const nextPrincipal = baseAmount + iof;
            if (Math.abs(nextPrincipal - principal) < 0.001) {
              break;
            }
            principal = nextPrincipal;
          }
          return { principal: baseAmount + iof, iof };
        }

        // CET: taxa mensal que iguala o dinheiro recebido (preço − entrada) às parcelas pagas
        function monthlyEffectiveCost(creditReceived, installment, months) {
          let lowRate = 0;
          let highRate = 1;
          for (let step = 0; step < 100; step++) {
            const middleRate = (lowRate + highRate) / 2;
            const presentValue = middleRate === 0 ? installment * months : installment * (1 - Math.pow(1 + middleRate, -months)) / middleRate;
            if (presentValue > creditReceived) {
              lowRate = middleRate;
            } else {
              highRate = middleRate;
            }
          }
          return (lowRate + highRate) / 2;
        }

        function simulate(baseAmount, rate, months, includeIof) {
          const financing = includeIof ? financedWithIof(baseAmount, rate, months) : { principal: baseAmount, iof: 0 };
          const installment = priceInstallment(financing.principal, rate, months);
          return { ...financing, installment, totalInstallments: installment * months };
        }

        function formatPercent(rate) {
          return `${formatNumber(rate * 100, 2)}%`;
        }

        function calculate() {
          const [price, downPayment, ratePercent] = ["vehicle-price", "vehicle-down", "vehicle-rate"].map((id) => parseNumber(document.getElementById(id).value));
          const fees = parseNumber(document.getElementById("vehicle-fees").value) || 0;
          const months = Number(document.getElementById("vehicle-months").value);
          const includeIof = document.getElementById("vehicle-iof").checked;
          const down = downPayment || 0;
          const resultBox = document.getElementById("vehicle-result");
          const tableBox = document.getElementById("vehicle-table");
          const downHint = document.getElementById("vehicle-down-percent");
          downHint.textContent = price > 0 ? `${formatNumber((down / price) * 100, 1)}% do valor do veículo` : "";

          const creditReceived = price - down;
          if (!(price > 0) || Number.isNaN(ratePercent) || ratePercent < 0 || creditReceived <= 0) {
            resultBox.innerHTML = emptyDisplay("Valor da parcela", "Confira os valores: a entrada precisa ser menor que o valor do veículo.");
            tableBox.innerHTML = "";
            return;
          }
          const rate = ratePercent / 100;
          const baseAmount = creditReceived + fees;
          const result = simulate(baseAmount, rate, months, includeIof);
          const interest = result.totalInstallments - result.principal;
          const totalCost = down + result.totalInstallments;
          const monthlyCet = monthlyEffectiveCost(creditReceived, result.installment, months);
          const yearlyCet = Math.pow(1 + monthlyCet, 12) - 1;

          resultBox.innerHTML = display("Valor da parcela", formatMoney(result.installment), `${months} parcelas fixas · o carro sai por ${formatMoney(totalCost)} (${formatPercent(totalCost / price - 1)} a mais que à vista)`, [
            ["Valor financiado", formatMoney(result.principal)],
            ["Juros no período", formatMoney(interest)],
            ["IOF", formatMoney(result.iof)],
            ["Total das parcelas", formatMoney(result.totalInstallments)],
            ["CET ao mês", formatPercent(monthlyCet)],
            ["CET ao ano", formatPercent(yearlyCet)],
          ]);

          // Mesma taxa em outros prazos, para comparar parcela e custo total
          const rows = TERM_OPTIONS.map((termMonths) => {
            const option = simulate(baseAmount, rate, termMonths, includeIof);
            const isSelected = termMonths === months;
            return `<tr${isSelected ? ' class="current-row"' : ""}><td>${termMonths} meses${isSelected ? " (escolhido)" : ""}</td><td>${formatMoney(option.installment)}</td><td>${formatMoney(down + option.totalInstallments)}</td></tr>`;
          }).join("");
          tableBox.innerHTML = `<h3>Compare os prazos com a mesma taxa</h3>
            <table class="data-table"><thead><tr><th>Prazo</th><th>Parcela</th><th>Custo total do carro</th></tr></thead><tbody>${rows}</tbody></table>
            <p class="notice">Simulação pela tabela Price (parcelas iguais), com o IOF e as tarifas incluídos no financiamento. Não inclui seguro prestamista nem seguro do carro. O CET oficial aparece no contrato, e o banco é obrigado a informá-lo antes da assinatura.</p>`;
        }
        onInputs(["vehicle-price", "vehicle-down", "vehicle-months", "vehicle-rate", "vehicle-fees", "vehicle-iof"], calculate);
      },
    },

    "moedas": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="currency-amount">Valor</label><input id="currency-amount" inputmode="numeric" data-money value="100"></div>
            <div class="field"><label for="currency-from">De</label><select id="currency-from"></select></div>
            <div class="field"><label for="currency-to">Para</label><select id="currency-to"></select></div>
          </div>
          <details>
            <summary class="field-label">Cotações usadas (em reais)</summary>
            <div class="field-row" id="currency-rates"></div>
          </details>
          <p class="notice" id="currency-source">Buscando a cotação do dia…</p>
        </div>
        <div id="currency-result"></div>`,
      setup() {
        // Quanto vale 1 unidade de cada moeda em reais.
        // Estes valores só são usados se as APIs de cotação estiverem fora do ar.
        const currencies = { BRL: ["Real", 1], USD: ["Dólar americano", 5.4], EUR: ["Euro", 6.3], GBP: ["Libra esterlina", 7.2], ARS: ["Peso argentino", 0.0045] };
        const fromSelect = document.getElementById("currency-from");
        const toSelect = document.getElementById("currency-to");
        const options = Object.entries(currencies).map(([code, [name]]) => `<option value="${code}">${code} · ${name}</option>`).join("");
        fromSelect.innerHTML = options;
        toSelect.innerHTML = options;
        fromSelect.value = "USD";
        toSelect.value = "BRL";
        document.getElementById("currency-rates").innerHTML = Object.entries(currencies).filter(([code]) => code !== "BRL")
          .map(([code, [, rate]]) => `<div class="field"><label for="rate-${code}">1 ${code} =</label><input id="rate-${code}" inputmode="decimal" value="${formatNumber(rate, 4)}"></div>`).join("");

        function calculate() {
          Object.keys(currencies).filter((code) => code !== "BRL").forEach((code) => {
            const typedRate = parseNumber(document.getElementById(`rate-${code}`).value);
            if (typedRate > 0) {
              currencies[code][1] = typedRate;
            }
          });
          const amount = parseNumber(document.getElementById("currency-amount").value);
          const resultBox = document.getElementById("currency-result");
          if (Number.isNaN(amount)) {
            resultBox.innerHTML = emptyDisplay("Resultado", "Digite um valor.");
            return;
          }
          const inReais = amount * currencies[fromSelect.value][1];
          const converted = inReais / currencies[toSelect.value][1];
          resultBox.innerHTML = display("Resultado", `${formatNumber(converted, 2)} ${toSelect.value}`, `${formatNumber(amount)} ${fromSelect.value} = ${formatNumber(converted, 4)} ${toSelect.value}`);
        }
        const rateIds = Object.keys(currencies).filter((code) => code !== "BRL").map((code) => `rate-${code}`);
        onInputs(["currency-amount", "currency-from", "currency-to", ...rateIds], calculate);

        // Troca os valores de reserva pelas cotações do dia (buscadas pelo site.js)
        const sourceNotice = document.getElementById("currency-source");
        // Nas páginas de calculadora este arquivo roda antes do site.js (que busca as cotações):
        // espera a página terminar de carregar para chamar window.Vibe2000
        const pageReady = document.readyState === "loading" ? new Promise((resolve) => document.addEventListener("DOMContentLoaded", resolve, { once: true })) : Promise.resolve();
        const exchangePromise = pageReady.then(() => (window.Vibe2000?.getExchangeRates ? window.Vibe2000.getExchangeRates() : null));
        exchangePromise.then((exchange) => {
          if (!exchange) {
            sourceNotice.textContent = "Não foi possível buscar a cotação do dia. Os valores abaixo são aproximados: confira e ajuste em \"Cotações usadas\".";
            return;
          }
          Object.entries(exchange.rates).forEach(([code, quote]) => {
            const rateInput = document.getElementById(`rate-${code}`);
            if (rateInput) {
              rateInput.value = formatNumber(quote.value, 4);
            }
          });
          const updatedAt = new Date(exchange.updatedAt);
          const when = exchange.dailyOnly ? updatedAt.toLocaleDateString("pt-BR") : updatedAt.toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" });
          sourceNotice.textContent = `Cotação comercial de ${when} (fonte: ${exchange.source}). Casas de câmbio e cartões cobram mais: você pode ajustar os valores em "Cotações usadas".`;
          calculate();
        });
      },
    },

    "salario-liquido": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="net-salary">Salário bruto (R$)</label><input id="net-salary" inputmode="numeric" data-money value="4.500,00"></div>
            <div class="field"><label for="net-dependents">Dependentes (IR)</label><input id="net-dependents" type="number" min="0" max="20" value="0"></div>
            <div class="field"><label for="net-other">Outros descontos (R$)</label><input id="net-other" inputmode="numeric" data-money value="0"><span class="field-hint">vale-transporte, plano de saúde...</span></div>
          </div>
        </div>
        <div id="net-result"></div>
        <div class="calculator-body" id="net-table"></div>`,
      setup() {
        function calculate() {
          const grossSalary = parseNumber(document.getElementById("net-salary").value);
          const dependents = Math.max(0, parseInt(document.getElementById("net-dependents").value, 10) || 0);
          const otherDiscounts = parseNumber(document.getElementById("net-other").value) || 0;
          const resultBox = document.getElementById("net-result");
          const tableBox = document.getElementById("net-table");
          if (!(grossSalary > 0)) {
            resultBox.innerHTML = emptyDisplay("Salário líquido", "Informe o salário bruto.");
            tableBox.innerHTML = "";
            return;
          }
          const inss = calculateInss(grossSalary);
          const incomeTax = calculateIncomeTax({ taxableIncome: grossSalary, inss, dependents });
          const netSalary = grossSalary - inss - incomeTax.tax - otherDiscounts;
          resultBox.innerHTML = display("Salário líquido", formatMoney(netSalary), `descontos totais de ${formatMoney(grossSalary - netSalary)}`, [
            ["INSS", `${formatMoney(inss)} (${formatPercent(inss / grossSalary)})`],
            ["Imposto de Renda", `${formatMoney(incomeTax.tax)} (${formatPercent(incomeTax.tax / grossSalary)})`],
            ["Base do IR", formatMoney(incomeTax.taxBase)],
            ["Dedução usada", incomeTax.usesSimplified ? "desconto simplificado" : "INSS + dependentes"],
          ]);
          const rows = [
            { label: "Salário bruto", value: grossSalary },
            { label: "INSS", value: inss, isDiscount: true },
            { label: `IR calculado na tabela (alíquota ${formatPercent(incomeTax.rate)})`, value: incomeTax.taxBeforeReduction, isDiscount: true },
          ];
          if (incomeTax.reduction > 0) {
            rows.push({ label: "Redução da Lei 15.270/2025 (devolvida)", value: incomeTax.reduction });
          }
          if (otherDiscounts > 0) {
            rows.push({ label: "Outros descontos", value: otherDiscounts, isDiscount: true });
          }
          tableBox.innerHTML = payslipTable(rows) + `<p class="notice">${LABOR_NOTICE}</p>`;
        }
        onInputs(["net-salary", "net-dependents", "net-other"], calculate);
      },
    },

    "decimo-terceiro": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="thirteenth-salary">Salário bruto (R$)</label><input id="thirteenth-salary" inputmode="numeric" data-money value="4.500,00"></div>
            <div class="field"><label for="thirteenth-months">Meses trabalhados no ano</label><input id="thirteenth-months" type="number" min="1" max="12" value="12"></div>
            <div class="field"><label for="thirteenth-dependents">Dependentes (IR)</label><input id="thirteenth-dependents" type="number" min="0" max="20" value="0"></div>
          </div>
          <span class="field-hint">Conta como mês trabalhado aquele em que você trabalhou 15 dias ou mais.</span>
          <div class="field-row">
            <div class="field"><label for="thirteenth-variables">Média de horas extras, adicionais e comissões (R$/mês)</label><input id="thirteenth-variables" inputmode="numeric" data-money value="0"><span class="field-hint">média mensal do que você recebe além do salário (opcional)</span></div>
            <div class="field"><label for="thirteenth-advance">1ª parcela já recebida (R$)</label><input id="thirteenth-advance" inputmode="numeric" data-money value="0"><span class="field-hint">se já recebeu, ex.: adiantamento nas férias (opcional)</span></div>
          </div>
        </div>
        <div id="thirteenth-result"></div>
        <div class="calculator-body" id="thirteenth-table"></div>`,
      setup() {
        // Prazo legal; se cair no fim de semana, o pagamento é antecipado para a sexta-feira anterior
        function deadline(year, month, day) {
          const date = new Date(year, month - 1, day, 12);
          while (date.getDay() === 0 || date.getDay() === 6) {
            date.setDate(date.getDate() - 1);
          }
          return `${String(date.getDate()).padStart(2, "0")}/${String(date.getMonth() + 1).padStart(2, "0")}`;
        }
        const year = new Date().getFullYear();
        const firstDeadline = deadline(year, 11, 30);
        const secondDeadline = deadline(year, 12, 20);

        function calculate() {
          const salary = parseNumber(document.getElementById("thirteenth-salary").value);
          const months = Math.min(12, Math.max(0, parseInt(document.getElementById("thirteenth-months").value, 10) || 0));
          const dependents = Math.max(0, parseInt(document.getElementById("thirteenth-dependents").value, 10) || 0);
          const variables = parseNumber(document.getElementById("thirteenth-variables").value) || 0;
          const advanceReceived = parseNumber(document.getElementById("thirteenth-advance").value) || 0;
          const resultBox = document.getElementById("thirteenth-result");
          const tableBox = document.getElementById("thirteenth-table");
          if (!(salary > 0) || months === 0) {
            resultBox.innerHTML = emptyDisplay("13º líquido", "Informe o salário e os meses trabalhados (1 a 12).");
            tableBox.innerHTML = "";
            return;
          }
          // Base do 13º: salário + média das verbas variáveis habituais (horas extras, adicionais, comissões)
          const base = salary + variables;
          const grossThirteenth = roundCents((base / 12) * months);
          const firstInstallment = advanceReceived > 0 ? advanceReceived : roundCents(grossThirteenth / 2);
          // INSS e IR do 13º são calculados separados do salário, sobre o valor total, e descontados na 2ª parcela
          const inss = calculateInss(grossThirteenth);
          const incomeTax = calculateIncomeTax({ taxableIncome: grossThirteenth, inss, dependents, isThirteenth: true });
          const secondInstallment = roundCents(grossThirteenth - firstInstallment - inss - incomeTax.tax);
          const detail = `bruto de ${formatMoney(grossThirteenth)} (${months}/12 avos${variables > 0 ? ", com as médias" : ""})`;
          resultBox.innerHTML = display("13º salário líquido", formatMoney(firstInstallment + secondInstallment), detail, [
            [advanceReceived > 0 ? "1ª parcela (já recebida)" : `1ª parcela (até ${firstDeadline})`, formatMoney(firstInstallment)],
            [`2ª parcela (até ${secondDeadline})`, formatMoney(secondInstallment)],
            ["INSS", formatMoney(inss)],
            ["Imposto de Renda", formatMoney(incomeTax.tax)],
          ], secondInstallment < 0 ? `<span class="display-detail">⚠ A 1ª parcela informada é maior que o 13º líquido: confira o valor.</span>` : "");
          const rows = [{ label: `Salário: ${formatMoney(salary)} ÷ 12 × ${months}`, value: roundCents(salary / 12 * months) }];
          if (variables > 0) {
            rows.push({ label: `Médias de variáveis: ${formatMoney(variables)} ÷ 12 × ${months}`, value: roundCents(variables / 12 * months) });
          }
          rows.push(
            { label: "INSS sobre o 13º", value: inss, isDiscount: true },
            { label: `IR sobre o 13º (alíquota ${formatPercent(incomeTax.rate)}, tributação exclusiva)`, value: incomeTax.taxBeforeReduction, isDiscount: true },
          );
          if (incomeTax.reduction > 0) {
            rows.push({ label: "Redução da Lei 15.270/2025 (devolvida)", value: incomeTax.reduction });
          }
          rows.push({ label: advanceReceived > 0 ? "1ª parcela já recebida" : "1ª parcela (metade do 13º, sem descontos)", value: firstInstallment, isDiscount: true });
          tableBox.innerHTML = payslipTable(rows)
            + `<p><b>2ª parcela a receber:</b> ${formatMoney(secondInstallment)}</p>`
            + `<p class="notice">${LABOR_NOTICE} Em ${year}, a 1ª parcela vence em ${firstDeadline} e a 2ª em ${secondDeadline} (prazo legal de 20/12 antecipado quando cai no fim de semana).</p>`;
        }
        onInputs(["thirteenth-salary", "thirteenth-months", "thirteenth-dependents", "thirteenth-variables", "thirteenth-advance"], calculate);
      },
    },

    "ferias": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="vacation-salary">Salário bruto (R$)</label><input id="vacation-salary" inputmode="numeric" data-money value="4.500,00"></div>
            <div class="field"><label for="vacation-days">Dias de férias</label><select id="vacation-days"><option value="30">30 dias</option><option value="20">20 dias</option><option value="15">15 dias</option><option value="10">10 dias</option></select></div>
            <div class="field"><label for="vacation-dependents">Dependentes (IR)</label><input id="vacation-dependents" type="number" min="0" max="20" value="0"></div>
          </div>
          <label class="check"><input type="checkbox" id="vacation-sell"> Vender 10 dias (abono pecuniário)</label>
        </div>
        <div id="vacation-result"></div>
        <div class="calculator-body" id="vacation-table"></div>`,
      setup() {
        const vacationDays = document.getElementById("vacation-days");
        const sellCheckbox = document.getElementById("vacation-sell");
        function calculate() {
          const salary = parseNumber(document.getElementById("vacation-salary").value);
          const dependents = Math.max(0, parseInt(document.getElementById("vacation-dependents").value, 10) || 0);
          const resultBox = document.getElementById("vacation-result");
          const tableBox = document.getElementById("vacation-table");
          // Quem vende 10 dias tira no máximo 20 dias de férias
          if (sellCheckbox.checked && vacationDays.value === "30") {
            vacationDays.value = "20";
          }
          if (!(salary > 0)) {
            resultBox.innerHTML = emptyDisplay("Férias líquidas", "Informe o salário.");
            tableBox.innerHTML = "";
            return;
          }
          const days = Number(vacationDays.value);
          const dailyValue = salary / 30;
          const vacationPay = roundCents(dailyValue * days);
          const oneThird = roundCents(vacationPay / 3);
          // O abono (dias vendidos + 1/3) não tem desconto de INSS nem de IR
          const bonus = sellCheckbox.checked ? roundCents(dailyValue * 10) : 0;
          const bonusThird = roundCents(bonus / 3);
          const taxedAmount = vacationPay + oneThird;
          const inss = calculateInss(taxedAmount);
          const incomeTax = calculateIncomeTax({ taxableIncome: taxedAmount, inss, dependents });
          const netTotal = taxedAmount + bonus + bonusThird - inss - incomeTax.tax;
          resultBox.innerHTML = display("Férias líquidas", formatMoney(netTotal), `${days} dias de férias${sellCheckbox.checked ? " + 10 dias vendidos" : ""}`, [
            ["Total bruto", formatMoney(taxedAmount + bonus + bonusThird)],
            ["INSS", formatMoney(inss)],
            ["Imposto de Renda", formatMoney(incomeTax.tax)],
          ]);
          const rows = [
            { label: `Férias (${days} dias)`, value: vacationPay },
            { label: "1/3 constitucional", value: oneThird },
          ];
          if (bonus > 0) {
            rows.push({ label: "Abono pecuniário (10 dias vendidos)", value: bonus }, { label: "1/3 sobre o abono", value: bonusThird });
          }
          rows.push({ label: "INSS", value: inss, isDiscount: true }, { label: "Imposto de Renda (já com a redução de 2026)", value: incomeTax.tax, isDiscount: true });
          tableBox.innerHTML = payslipTable(rows) + `<p class="notice">${LABOR_NOTICE} O cálculo considera as férias pagas separadas do salário do mês.</p>`;
        }
        onInputs(["vacation-salary", "vacation-days", "vacation-dependents", "vacation-sell"], calculate);
      },
    },

    "rescisao": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="termination-salary">Último salário bruto (R$)</label><input id="termination-salary" inputmode="numeric" data-money value="3.500,00"></div>
            <div class="field"><label for="termination-start">Data de admissão</label><input id="termination-start" type="date" value="2022-03-10"></div>
            <div class="field"><label for="termination-end">Último dia de trabalho</label><input id="termination-end" type="date"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="termination-reason">Motivo</label>
              <select id="termination-reason">
                <option value="dismissal">Demissão sem justa causa</option>
                <option value="resignation">Pedido de demissão</option>
                <option value="agreement">Acordo entre as partes</option>
                <option value="cause">Demissão por justa causa</option>
              </select>
            </div>
            <div class="field"><label for="termination-notice">Aviso prévio</label>
              <select id="termination-notice">
                <option value="paid">Indenizado (não trabalhado)</option>
                <option value="worked">Trabalhado</option>
                <option value="unfulfilled">Não cumprido pelo empregado</option>
              </select>
            </div>
            <div class="field"><label for="termination-dependents">Dependentes (IR)</label><input id="termination-dependents" type="number" min="0" max="20" value="0"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="termination-fgts">Saldo do FGTS (R$, opcional)</label><input id="termination-fgts" inputmode="numeric" data-money placeholder="deixe vazio para estimar"><span class="field-hint">Veja no app FGTS. Vazio = estimativa pelo tempo de casa.</span></div>
          </div>
          <label class="check"><input type="checkbox" id="termination-overdue"> Tenho férias vencidas (um período completo não tirado)</label>
        </div>
        <div id="termination-result"></div>
        <div class="calculator-body" id="termination-table"></div>`,
      setup() {
        document.getElementById("termination-end").value = todayIso();
        const reasonSelect = document.getElementById("termination-reason");
        const noticeSelect = document.getElementById("termination-notice");

        // Meses do período com 15 dias ou mais (regra usada no 13º proporcional)
        function monthsWithFifteenDays(periodStart, periodEnd) {
          let count = 0;
          for (let cursor = new Date(periodStart.getFullYear(), periodStart.getMonth(), 1, 12); cursor <= periodEnd; cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1, 12)) {
            const monthEnd = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0, 12);
            const overlapStart = periodStart > cursor ? periodStart : cursor;
            const overlapEnd = periodEnd < monthEnd ? periodEnd : monthEnd;
            const daysInMonth = Math.round((overlapEnd - overlapStart) / MILLISECONDS_PER_DAY) + 1;
            if (daysInMonth >= 15) {
              count += 1;
            }
          }
          return Math.min(12, count);
        }

        function addMonths(date, monthsToAdd) {
          const target = new Date(date.getFullYear(), date.getMonth() + monthsToAdd, 1, 12);
          const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
          target.setDate(Math.min(date.getDate(), lastDay));
          return target;
        }

        // Avos de férias proporcionais: meses (ou frações de 15 dias ou mais) desde o último aniversário da admissão
        function vacationTwelfths(admission, periodEnd) {
          let yearsSinceAdmission = periodEnd.getFullYear() - admission.getFullYear();
          if (addMonths(admission, yearsSinceAdmission * 12) > periodEnd) {
            yearsSinceAdmission -= 1;
          }
          let periodStart = addMonths(admission, yearsSinceAdmission * 12);
          let twelfths = 0;
          while (twelfths < 12) {
            const nextStart = addMonths(periodStart, 1);
            if (nextStart - MILLISECONDS_PER_DAY <= periodEnd) {
              twelfths += 1;
              periodStart = nextStart;
              continue;
            }
            const remainingDays = Math.round((periodEnd - periodStart) / MILLISECONDS_PER_DAY) + 1;
            if (remainingDays >= 15) {
              twelfths += 1;
            }
            break;
          }
          return { twelfths, fullYears: yearsSinceAdmission };
        }

        function calculate() {
          const salary = parseNumber(document.getElementById("termination-salary").value);
          const admission = dateFromInput("termination-start");
          const lastDay = dateFromInput("termination-end");
          const reason = reasonSelect.value;
          const dependents = Math.max(0, parseInt(document.getElementById("termination-dependents").value, 10) || 0);
          const hasOverdueVacation = document.getElementById("termination-overdue").checked;
          const resultBox = document.getElementById("termination-result");
          const tableBox = document.getElementById("termination-table");

          if (!(salary > 0) || !admission || !lastDay || admission >= lastDay) {
            resultBox.innerHTML = emptyDisplay("Valor líquido estimado", "Informe o salário e as datas (a admissão precisa ser antes da saída).");
            tableBox.innerHTML = "";
            return;
          }

          // Aviso prévio: na demissão sem justa causa ou no acordo, é indenizado ou trabalhado;
          // no pedido de demissão, é trabalhado ou descontado; na justa causa, não existe
          const allowedNotices = { dismissal: ["paid", "worked"], agreement: ["paid", "worked"], resignation: ["worked", "unfulfilled"], cause: [] };
          [...noticeSelect.options].forEach((option) => { option.disabled = !allowedNotices[reason].includes(option.value); });
          noticeSelect.disabled = reason === "cause";
          if (reason !== "cause" && !allowedNotices[reason].includes(noticeSelect.value)) {
            noticeSelect.value = allowedNotices[reason][0];
          }
          const notice = reason === "cause" ? "none" : noticeSelect.value;

          const dailySalary = salary / 30;
          const { fullYears } = vacationTwelfths(admission, lastDay);
          // Lei 12.506/2011: 30 dias + 3 dias por ano completo de serviço, até 90 dias (vale para quem é demitido)
          const employerNoticeDays = Math.min(90, 30 + 3 * Math.max(0, fullYears));

          let paidNoticeDays = 0;
          if (notice === "paid" && reason === "dismissal") paidNoticeDays = employerNoticeDays;
          if (notice === "paid" && reason === "agreement") paidNoticeDays = Math.floor(employerNoticeDays / 2);

          // O aviso indenizado conta como tempo de serviço para 13º e férias
          const projectedEnd = new Date(lastDay.getTime() + paidNoticeDays * MILLISECONDS_PER_DAY);
          const rows = [];

          const daysWorkedInMonth = Math.min(30, lastDay.getDate());
          const salaryBalance = roundCents(dailySalary * daysWorkedInMonth);
          rows.push({ label: `Saldo de salário (${daysWorkedInMonth} dias)`, value: salaryBalance });

          let noticePay = 0;
          if (paidNoticeDays > 0) {
            noticePay = roundCents(dailySalary * paidNoticeDays);
            rows.push({ label: `Aviso prévio indenizado (${paidNoticeDays} dias${reason === "agreement" ? ", metade no acordo" : ""})`, value: noticePay });
          }

          let thirteenth = 0;
          let proportionalVacation = 0;
          if (reason !== "cause") {
            const yearStart = new Date(projectedEnd.getFullYear(), 0, 1, 12);
            const thirteenthMonths = monthsWithFifteenDays(admission > yearStart ? admission : yearStart, projectedEnd);
            thirteenth = roundCents((salary / 12) * thirteenthMonths);
            if (thirteenth > 0) {
              rows.push({ label: `13º salário proporcional (${thirteenthMonths}/12)`, value: thirteenth });
            }
            const { twelfths } = vacationTwelfths(admission, projectedEnd);
            proportionalVacation = roundCents((salary / 12) * twelfths);
            if (proportionalVacation > 0) {
              rows.push({ label: `Férias proporcionais (${twelfths}/12)`, value: proportionalVacation }, { label: "1/3 sobre férias proporcionais", value: roundCents(proportionalVacation / 3) });
            }
          }

          let overdueVacation = 0;
          if (hasOverdueVacation) {
            overdueVacation = roundCents(salary);
            rows.push({ label: "Férias vencidas", value: overdueVacation }, { label: "1/3 sobre férias vencidas", value: roundCents(overdueVacation / 3) });
          }

          const grossTotal = rows.reduce((sum, row) => sum + row.value, 0);

          // Descontos: aviso não cumprido, INSS e IR (só saldo de salário e 13º são tributados;
          // aviso indenizado e férias indenizadas não têm INSS nem IR)
          const discounts = [];
          if (notice === "unfulfilled") {
            discounts.push({ label: "Aviso prévio não cumprido (30 dias)", value: roundCents(salary), isDiscount: true });
          }
          const inssOnSalary = calculateInss(salaryBalance);
          const inssOnThirteenth = calculateInss(thirteenth);
          const taxOnSalary = calculateIncomeTax({ taxableIncome: salaryBalance, inss: inssOnSalary, dependents });
          const taxOnThirteenth = thirteenth > 0 ? calculateIncomeTax({ taxableIncome: thirteenth, inss: inssOnThirteenth, dependents, isThirteenth: true }) : { tax: 0 };
          discounts.push(
            { label: "INSS sobre saldo de salário", value: inssOnSalary, isDiscount: true },
            { label: "INSS sobre 13º", value: inssOnThirteenth, isDiscount: true },
            { label: "IR sobre saldo de salário", value: taxOnSalary.tax, isDiscount: true },
            { label: "IR sobre 13º", value: taxOnThirteenth.tax, isDiscount: true },
          );
          const discountRows = discounts.filter((row) => row.value > 0);
          const discountTotal = discountRows.reduce((sum, row) => sum + row.value, 0);
          const netTotal = grossTotal - discountTotal;

          // FGTS: saque e multa dependem do motivo
          const typedFgts = parseNumber(document.getElementById("termination-fgts").value);
          const monthsOfService = Math.max(1, Math.round((lastDay - admission) / (MILLISECONDS_PER_DAY * 30.44)));
          const fgtsBalance = typedFgts > 0 ? typedFgts : roundCents(salary * TAX_TABLES.fgtsMonthlyRate * monthsOfService * (13 / 12));
          const finePercent = { dismissal: 0.4, agreement: 0.2, resignation: 0, cause: 0 }[reason];
          const withdrawPercent = { dismissal: 1, agreement: 0.8, resignation: 0, cause: 0 }[reason];
          const fgtsFine = roundCents(fgtsBalance * finePercent);
          const fgtsWithdraw = roundCents(fgtsBalance * withdrawPercent);

          const reasonNotes = {
            dismissal: "Pode ter direito ao seguro-desemprego.",
            agreement: "No acordo (art. 484-A da CLT) não há seguro-desemprego.",
            resignation: "No pedido de demissão não há multa nem saque do FGTS.",
            cause: "Na justa causa ficam só o saldo de salário e as férias vencidas.",
          };

          resultBox.innerHTML = display("Valor líquido estimado da rescisão", formatMoney(netTotal), reasonNotes[reason], [
            ["Total bruto", formatMoney(grossTotal)],
            ["Descontos", formatMoney(discountTotal)],
            [`Multa do FGTS (${formatNumber(finePercent * 100, 0)}%)`, formatMoney(fgtsFine)],
            ["FGTS que pode sacar", formatMoney(fgtsWithdraw)],
            ["Total a receber (com FGTS)", formatMoney(netTotal + fgtsFine + fgtsWithdraw)],
          ], `<span class="display-detail">FGTS ${typedFgts > 0 ? "informado" : "estimado pelo tempo de casa"}: ${formatMoney(fgtsBalance)}. A multa é depositada na conta do FGTS.</span>`);

          // Resumo: rescisão + multa e, depois, tudo o que a pessoa recebe (incluindo o saque do saldo do FGTS)
          const terminationPlusFine = netTotal + fgtsFine;
          const everythingTotal = terminationPlusFine + fgtsWithdraw;
          const fineLabel = finePercent > 0 ? `Multa do FGTS (${formatNumber(finePercent * 100, 0)}% do saldo)` : "Multa do FGTS (não há neste motivo)";
          const withdrawLabel = withdrawPercent === 1 ? "Saque do saldo do FGTS" : withdrawPercent > 0 ? `Saque do saldo do FGTS (até ${formatNumber(withdrawPercent * 100, 0)}%)` : "Saque do saldo do FGTS (não pode sacar neste motivo)";
          const summaryHtml = `<h3>Resumo do que você vai receber</h3>
            <table class="data-table payslip termination-summary"><tbody>
              <tr><td>Rescisão líquida (paga pela empresa)</td><td>${formatMoney(netTotal)}</td></tr>
              <tr><td>${fineLabel}</td><td>${formatMoney(fgtsFine)}</td></tr>
              <tr class="subtotal-row"><td>Rescisão + multa</td><td>${formatMoney(terminationPlusFine)}</td></tr>
              <tr><td>${withdrawLabel}</td><td>${formatMoney(fgtsWithdraw)}</td></tr>
              <tr class="total-row"><td>Total a receber</td><td>${formatMoney(everythingTotal)}</td></tr>
            </tbody></table>
            <p class="notice">A rescisão é paga pela empresa em até 10 dias após o fim do contrato. A multa é depositada na conta do FGTS e sacada junto com o saldo, pelo app FGTS ou na Caixa.${reason === "dismissal" ? " O seguro-desemprego não entra nesta soma: calcule na <a href=\"/calculadoras/seguro-desemprego\">calculadora de seguro-desemprego</a>." : ""}</p>`;

          tableBox.innerHTML = payslipTable([...rows, ...discountRows]) + summaryHtml + `<p class="notice">${LABOR_NOTICE} Os depósitos de FGTS sobre as verbas da rescisão não estão incluídos.</p>`;
        }
        onInputs(["termination-salary", "termination-start", "termination-end", "termination-reason", "termination-notice", "termination-dependents", "termination-fgts", "termination-overdue"], calculate);
      },
    },

    "hora-extra": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="overtime-salary">Salário bruto (R$)</label><input id="overtime-salary" inputmode="numeric" data-money value="3.000,00"></div>
            <div class="field"><label for="overtime-journey">Jornada mensal (horas)</label><input id="overtime-journey" inputmode="numeric" value="220"></div>
            <div class="field"><label for="overtime-dependents">Dependentes (IR)</label><input id="overtime-dependents" type="number" min="0" max="20" value="0"></div>
          </div>
          <span class="field-hint">Jornada: 220 horas = 44 por semana; 200 horas = 40 por semana; 180 horas = 36 por semana.</span>
          <div class="field-row">
            <div class="field"><label for="overtime-hours">Horas extras em dias úteis</label><input id="overtime-hours" inputmode="decimal" value="10"></div>
            <div class="field"><label for="overtime-rate">Adicional</label><select id="overtime-rate"><option value="50">50%</option><option value="60">60%</option><option value="70">70%</option><option value="75">75%</option><option value="100">100%</option></select></div>
            <div class="field"><label for="overtime-hours-100">Horas em domingos e feriados (100%)</label><input id="overtime-hours-100" inputmode="decimal" value="0"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="overtime-workdays">Dias úteis no mês</label><input id="overtime-workdays" type="number" min="1" max="31" value="25"></div>
            <div class="field"><label for="overtime-restdays">Domingos e feriados no mês</label><input id="overtime-restdays" type="number" min="0" max="31" value="5"></div>
          </div>
          <span class="field-hint" id="overtime-month-hint">Dias úteis = segunda a sábado, sem feriados. Usados no cálculo do DSR.</span>
        </div>
        <div id="overtime-result"></div>
        <div class="calculator-body" id="overtime-table"></div>`,
      setup() {
        // Preenche os dias do mês atual: úteis (segunda a sábado) e domingos + feriados nacionais
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth();
        const holidays = holidaysOfYear(year, false);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let workdays = 0;
        let restDays = 0;
        for (let day = 1; day <= daysInMonth; day++) {
          const date = new Date(year, month, day, 12);
          const isoDate = `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
          if (date.getDay() === 0 || holidays.has(isoDate)) {
            restDays++;
          } else {
            workdays++;
          }
        }
        document.getElementById("overtime-workdays").value = workdays;
        document.getElementById("overtime-restdays").value = restDays;
        const monthName = today.toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
        document.getElementById("overtime-month-hint").textContent = `Preenchido para ${monthName} com os feriados nacionais (some os feriados do seu estado ou cidade). Dias úteis = segunda a sábado. Usados no cálculo do DSR.`;

        // Salário líquido do mês (INSS e IR sobre tudo o que é tributável)
        function netPay(grossPay, dependents) {
          const inss = calculateInss(grossPay);
          const incomeTax = calculateIncomeTax({ taxableIncome: grossPay, inss, dependents });
          return { inss, incomeTax, net: grossPay - inss - incomeTax.tax };
        }

        function calculate() {
          const [salary, journey, hours, hoursAt100] = ["overtime-salary", "overtime-journey", "overtime-hours", "overtime-hours-100"].map((id) => parseNumber(document.getElementById(id).value) || 0);
          const additionalPercent = Number(document.getElementById("overtime-rate").value);
          const workdaysInMonth = parseInt(document.getElementById("overtime-workdays").value, 10) || 0;
          const restDaysInMonth = parseInt(document.getElementById("overtime-restdays").value, 10) || 0;
          const dependents = Math.max(0, parseInt(document.getElementById("overtime-dependents").value, 10) || 0);
          const resultBox = document.getElementById("overtime-result");
          const tableBox = document.getElementById("overtime-table");
          if (!(salary > 0) || !(journey > 0) || workdaysInMonth <= 0) {
            resultBox.innerHTML = emptyDisplay("Total de horas extras com DSR", "Preencha salário, jornada e dias úteis.");
            tableBox.innerHTML = "";
            return;
          }

          const hourValue = salary / journey;
          const overtimeHourValue = hourValue * (1 + additionalPercent / 100);
          const overtimeAmount = roundCents(overtimeHourValue * hours);
          const overtimeAt100Amount = roundCents(hourValue * 2 * hoursAt100);
          const overtimeTotal = overtimeAmount + overtimeAt100Amount;
          // DSR sobre horas extras (Lei 605/1949 e Súmula 172 do TST): média por dia útil × domingos e feriados
          const restPayOnOvertime = roundCents(overtimeTotal / workdaysInMonth * restDaysInMonth);
          const overtimeWithRest = overtimeTotal + restPayOnOvertime;

          const grossPay = salary + overtimeWithRest;
          const withOvertime = netPay(grossPay, dependents);
          const withoutOvertime = netPay(salary, dependents);
          const netGain = withOvertime.net - withoutOvertime.net;

          resultBox.innerHTML = display("Total de horas extras com DSR", formatMoney(overtimeWithRest), `sobram ${formatMoney(netGain)} a mais no salário líquido, depois de INSS e IR`, [
            ["Hora normal", formatMoney(hourValue)],
            [`Hora extra ${additionalPercent}%`, formatMoney(overtimeHourValue)],
            ["DSR sobre as extras", formatMoney(restPayOnOvertime)],
            ["Salário líquido do mês", formatMoney(withOvertime.net)],
          ]);

          const rows = [{ label: "Salário base", value: salary }];
          if (overtimeAmount > 0) {
            rows.push({ label: `Horas extras ${additionalPercent}% (${formatNumber(hours)} h × ${formatMoney(overtimeHourValue)})`, value: overtimeAmount });
          }
          if (overtimeAt100Amount > 0) {
            rows.push({ label: `Horas extras 100% (${formatNumber(hoursAt100)} h × ${formatMoney(hourValue * 2)})`, value: overtimeAt100Amount });
          }
          if (restPayOnOvertime > 0) {
            rows.push({ label: `DSR sobre horas extras (${workdaysInMonth} dias úteis, ${restDaysInMonth} domingos e feriados)`, value: restPayOnOvertime });
          }
          rows.push(
            { label: "INSS", value: withOvertime.inss, isDiscount: true },
            { label: `IR calculado na tabela (alíquota ${formatPercent(withOvertime.incomeTax.rate)})`, value: withOvertime.incomeTax.taxBeforeReduction, isDiscount: true },
          );
          if (withOvertime.incomeTax.reduction > 0) {
            rows.push({ label: "Redução da Lei 15.270/2025 (devolvida)", value: withOvertime.incomeTax.reduction });
          }
          const fgtsOnOvertime = roundCents(overtimeWithRest * TAX_TABLES.fgtsMonthlyRate);
          const summaryHtml = `<p><b>Salário bruto do mês:</b> ${formatMoney(grossPay)} · <b>Líquido:</b> ${formatMoney(withOvertime.net)} · <b>Sem as horas extras seria:</b> ${formatMoney(withoutOvertime.net)}</p>`
            + `<p>A empresa também deposita ${formatMoney(fgtsOnOvertime)} de FGTS (8%) sobre as horas extras e o DSR; não é desconto.</p>`;
          tableBox.innerHTML = payslipTable(rows) + summaryHtml + `<p class="notice">${LABOR_NOTICE} Horas extras habituais também refletem em férias, 13º e aviso prévio.</p>`;
        }
        onInputs(["overtime-salary", "overtime-journey", "overtime-dependents", "overtime-hours", "overtime-rate", "overtime-hours-100", "overtime-workdays", "overtime-restdays"], calculate);
      },
    },

    "seguro-desemprego": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="unemployment-salary-1">Último salário (R$)</label><input id="unemployment-salary-1" inputmode="numeric" data-money value="2.800,00"></div>
            <div class="field"><label for="unemployment-salary-2">Penúltimo salário (R$)</label><input id="unemployment-salary-2" inputmode="numeric" data-money value="2.800,00"></div>
            <div class="field"><label for="unemployment-salary-3">Antepenúltimo salário (R$)</label><input id="unemployment-salary-3" inputmode="numeric" data-money value="2.700,00"></div>
          </div>
          <span class="field-hint">Salários brutos dos 3 meses antes da demissão, com horas extras e adicionais habituais.</span>
          <div class="field-row">
            <div class="field"><label for="unemployment-request">Qual solicitação é esta?</label><select id="unemployment-request"><option value="1">1ª vez</option><option value="2">2ª vez</option><option value="3">3ª vez ou mais</option></select></div>
            <div class="field"><label for="unemployment-months">Meses trabalhados com carteira</label><input id="unemployment-months" type="number" min="0" max="36" value="18"></div>
          </div>
          <span class="field-hint">Conte os meses com carteira assinada nos últimos 36 meses (pode somar empregos diferentes).</span>
        </div>
        <div id="unemployment-result"></div>
        <div class="calculator-body" id="unemployment-table"></div>`,
      setup() {
        const table = TAX_TABLES.unemploymentInsurance;
        // Tempo mínimo de trabalho para ter direito, conforme a solicitação (Lei 7.998/1990, com a Lei 13.134/2015)
        const minimumMonthsByRequest = { 1: 12, 2: 9, 3: 6 };
        const requirementByRequest = {
          1: "Na 1ª solicitação é preciso ter trabalhado pelo menos 12 meses nos últimos 18 meses.",
          2: "Na 2ª solicitação é preciso ter trabalhado pelo menos 9 meses nos últimos 12 meses.",
          3: "Da 3ª solicitação em diante é preciso ter trabalhado em cada um dos 6 meses antes da demissão.",
        };

        function installmentValue(averageSalary) {
          let value;
          if (averageSalary <= table.firstBracketUpTo) {
            value = averageSalary * 0.8;
          } else if (averageSalary <= table.secondBracketUpTo) {
            value = table.secondBracketBase + (averageSalary - table.firstBracketUpTo) * 0.5;
          } else {
            value = table.maximum;
          }
          // Nenhuma parcela fica abaixo do salário mínimo nem acima do teto
          return roundCents(Math.min(table.maximum, Math.max(table.minimum, value)));
        }

        function numberOfInstallments(request, months) {
          if (months < minimumMonthsByRequest[request]) {
            return 0;
          }
          if (months >= 24) {
            return 5;
          }
          if (months >= 12) {
            return 4;
          }
          return 3; // 2ª solicitação com 9 a 11 meses ou 3ª com 6 a 11 meses
        }

        function calculate() {
          const salaries = ["unemployment-salary-1", "unemployment-salary-2", "unemployment-salary-3"].map((id) => parseNumber(document.getElementById(id).value));
          const request = Number(document.getElementById("unemployment-request").value);
          const months = Math.max(0, parseInt(document.getElementById("unemployment-months").value, 10) || 0);
          const resultBox = document.getElementById("unemployment-result");
          const tableBox = document.getElementById("unemployment-table");
          if (salaries.some((salary) => !(salary > 0))) {
            resultBox.innerHTML = emptyDisplay("Valor da parcela", "Informe os 3 últimos salários.");
            tableBox.innerHTML = "";
            return;
          }
          const averageSalary = salaries.reduce((sum, salary) => sum + salary, 0) / salaries.length;
          const installments = numberOfInstallments(request, months);
          const tableNotice = `<p class="notice">Tabela vigente: ${table.source}. Piso de ${formatMoney(table.minimum)} e teto de ${formatMoney(table.maximum)}. Resultado estimado; o valor oficial é calculado pelo governo no pedido.</p>`;
          if (installments === 0) {
            resultBox.innerHTML = display("Sem direito pelo tempo informado", "—", requirementByRequest[request]);
            tableBox.innerHTML = tableNotice;
            return;
          }
          const value = installmentValue(averageSalary);
          resultBox.innerHTML = display("Valor da parcela", formatMoney(value), `${installments} parcelas · total de ${formatMoney(value * installments)}`, [
            ["Salário médio", formatMoney(averageSalary)],
            ["Parcelas", String(installments)],
            ["Total", formatMoney(value * installments)],
          ]);
          const rows = [
            ["Até " + formatMoney(table.firstBracketUpTo), "80% do salário médio"],
            [`De ${formatMoney(table.firstBracketUpTo + 0.01)} até ${formatMoney(table.secondBracketUpTo)}`, `${formatMoney(table.secondBracketBase)} + 50% do que passar de ${formatMoney(table.firstBracketUpTo)}`],
            [`Acima de ${formatMoney(table.secondBracketUpTo)}`, `${formatMoney(table.maximum)} (teto)`],
          ];
          tableBox.innerHTML = `<table class="data-table"><thead><tr><th>Salário médio</th><th>Valor da parcela</th></tr></thead><tbody>${rows.map(([range, rule]) => `<tr><td>${range}</td><td>${rule}</td></tr>`).join("")}</tbody></table>` + tableNotice;
        }
        onInputs(["unemployment-salary-1", "unemployment-salary-2", "unemployment-salary-3", "unemployment-request", "unemployment-months"], calculate);
      },
    },

    "imc": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="bmi-weight">Peso (kg)</label><input id="bmi-weight" inputmode="decimal" value="72"></div>
            <div class="field"><label for="bmi-height">Altura (cm)</label><input id="bmi-height" inputmode="decimal" value="175"></div>
          </div>
        </div>
        <div id="bmi-result"></div>
        <div class="calculator-body"><table class="data-table" id="bmi-table"></table></div>`,
      setup() {
        const bmiRanges = [
          { label: "Abaixo do peso", min: 0, max: 18.5, text: "menos de 18,5" },
          { label: "Peso normal", min: 18.5, max: 25, text: "18,5 a 24,9" },
          { label: "Sobrepeso", min: 25, max: 30, text: "25 a 29,9" },
          { label: "Obesidade grau I", min: 30, max: 35, text: "30 a 34,9" },
          { label: "Obesidade grau II", min: 35, max: 40, text: "35 a 39,9" },
          { label: "Obesidade grau III", min: 40, max: Infinity, text: "40 ou mais" },
        ];
        function calculate() {
          const weight = parseNumber(document.getElementById("bmi-weight").value);
          const heightInMeters = parseNumber(document.getElementById("bmi-height").value) / 100;
          const isValid = weight > 0 && heightInMeters > 0.5 && heightInMeters < 2.6;
          const bmi = isValid ? weight / (heightInMeters * heightInMeters) : NaN;
          const currentRange = bmiRanges.find((range) => bmi >= range.min && bmi < range.max);
          document.getElementById("bmi-result").innerHTML = isValid ? display("Seu IMC", formatNumber(bmi, 1), currentRange.label) : emptyDisplay("Seu IMC", "Informe peso em kg e altura em centímetros.");
          document.getElementById("bmi-table").innerHTML = bmiRanges.map((range) => `<tr class="${range === currentRange ? "current-row" : ""}"><td>${range.label}</td><td>${range.text}</td></tr>`).join("");
        }
        onInputs(["bmi-weight", "bmi-height"], calculate);
      },
    },

    "calorias": {
      html: `        <div class="calculator-body">
          <div class="segmented" id="calorie-sex" role="group" aria-label="Sexo">
            <button type="button" data-value="female" aria-pressed="true">Mulher</button>
            <button type="button" data-value="male" aria-pressed="false">Homem</button>
          </div>
          <div class="field-row">
            <div class="field"><label for="calorie-age">Idade</label><input id="calorie-age" inputmode="numeric" value="30"></div>
            <div class="field"><label for="calorie-weight">Peso (kg)</label><input id="calorie-weight" inputmode="decimal" value="65"></div>
            <div class="field"><label for="calorie-height">Altura (cm)</label><input id="calorie-height" inputmode="decimal" value="165"></div>
          </div>
          <div class="field"><label for="calorie-activity">Nível de atividade</label>
            <select id="calorie-activity">
              <option value="1.2">Sedentário (pouco ou nenhum exercício)</option>
              <option value="1.375" selected>Leve (1 a 3 dias por semana)</option>
              <option value="1.55">Moderado (3 a 5 dias por semana)</option>
              <option value="1.725">Intenso (6 a 7 dias por semana)</option>
              <option value="1.9">Muito intenso (atleta)</option>
            </select>
          </div>
        </div>
        <div id="calorie-result"></div>`,
      setup() {
        let sex = "female";
        function calculate() {
          const [age, weight, height] = ["calorie-age", "calorie-weight", "calorie-height"].map((id) => parseNumber(document.getElementById(id).value));
          const activityFactor = Number(document.getElementById("calorie-activity").value);
          const resultBox = document.getElementById("calorie-result");
          if ([age, weight, height].some((value) => Number.isNaN(value) || value <= 0)) {
            resultBox.innerHTML = emptyDisplay("Calorias por dia", "Preencha idade, peso e altura.");
            return;
          }
          // Fórmula de Mifflin-St Jeor
          const basalRate = 10 * weight + 6.25 * height - 5 * age + (sex === "male" ? 5 : -161);
          const dailyCalories = basalRate * activityFactor;
          resultBox.innerHTML = display("Calorias por dia", `${formatNumber(dailyCalories, 0)} kcal`, "para manter o peso atual", [
            ["Taxa basal", `${formatNumber(basalRate, 0)} kcal`],
            ["Para emagrecer*", `~${formatNumber(dailyCalories - 500, 0)} kcal`],
          ], `<span class="display-detail">* estimativa comum; procure um nutricionista para um plano adequado.</span>`);
        }
        setupSegmented("calorie-sex", (newSex) => { sex = newSex; calculate(); });
        onInputs(["calorie-age", "calorie-weight", "calorie-height", "calorie-activity"], calculate);
      },
    },

    "perda-de-peso": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="weight-loss-expenditure">Quanto você gasta por dia (kcal)</label><input id="weight-loss-expenditure" inputmode="numeric" value="1800"><span class="field-hint">Gasto total do dia, com as atividades. Não sabe? Calcule no <a href="/calculadoras/calorias">gasto calórico</a>.</span></div>
            <div class="field"><label for="weight-loss-intake">Quanto você come por dia (kcal)</label><input id="weight-loss-intake" inputmode="numeric" value="1000"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="weight-loss-weight">Peso atual (kg)</label><input id="weight-loss-weight" inputmode="decimal" value="80"></div>
            <div class="field"><label for="weight-loss-days">Por quantos dias</label><input id="weight-loss-days" type="number" min="1" max="730" value="60"></div>
          </div>
        </div>
        <div id="weight-loss-result"></div>
        <div class="calculator-body" id="weight-loss-details"></div>`,
      setup() {
        // Regra clássica: cerca de 7.700 kcal de déficit ≈ 1 kg de gordura corporal
        const KCAL_PER_KG = 7700;
        const MAX_DAYS = 730;

        // Simulação dia a dia. Quando o peso cai, o corpo passa a gastar menos
        // (o gasto acompanha o peso na mesma proporção), então a perda desacelera.
        function simulate(expenditure, intake, startWeight, days) {
          const weights = [startWeight];
          let weight = startWeight;
          for (let day = 1; day <= days; day++) {
            const todayExpenditure = expenditure * (weight / startWeight);
            weight -= (todayExpenditure - intake) / KCAL_PER_KG;
            weights.push(weight);
          }
          return weights;
        }

        // Gráfico simples em SVG: peso ao longo dos dias
        function chartSvg(weights) {
          const width = 600;
          const height = 200;
          const padding = 34;
          const maxWeight = Math.max(...weights);
          const minWeight = Math.min(...weights);
          const range = maxWeight - minWeight || 1;
          const points = weights.map((weight, day) => {
            const x = padding + (day / (weights.length - 1)) * (width - padding * 2);
            const y = padding / 2 + ((maxWeight - weight) / range) * (height - padding * 1.5);
            return `${x.toFixed(1)},${y.toFixed(1)}`;
          });
          const lastPoint = points[points.length - 1].split(",");
          return `<svg class="weight-chart" viewBox="0 0 ${width} ${height}" role="img" aria-label="Gráfico do peso estimado ao longo dos dias">
            <line x1="${padding}" y1="${height - padding}" x2="${width - padding}" y2="${height - padding}" class="weight-chart-axis"/>
            <polyline points="${points.join(" ")}" class="weight-chart-line"/>
            <circle cx="${lastPoint[0]}" cy="${lastPoint[1]}" r="4" class="weight-chart-dot"/>
            <text x="${padding}" y="12" class="weight-chart-label">${formatNumber(maxWeight, 1)} kg</text>
            <text x="${width - padding}" y="${Math.min(height - padding - 8, Number(lastPoint[1]) - 10)}" text-anchor="end" class="weight-chart-label">${formatNumber(weights[weights.length - 1], 1)} kg</text>
            <text x="${padding}" y="${height - 12}" class="weight-chart-label">hoje</text>
            <text x="${width - padding}" y="${height - 12}" text-anchor="end" class="weight-chart-label">dia ${weights.length - 1}</text>
          </svg>`;
        }

        function calculate() {
          const [expenditure, intake, startWeight] = ["weight-loss-expenditure", "weight-loss-intake", "weight-loss-weight"].map((id) => parseNumber(document.getElementById(id).value));
          const days = Math.min(MAX_DAYS, parseInt(document.getElementById("weight-loss-days").value, 10) || 0);
          const resultBox = document.getElementById("weight-loss-result");
          const detailsBox = document.getElementById("weight-loss-details");
          if (!(expenditure > 0) || !(intake >= 0) || !(startWeight > 0) || days < 1) {
            resultBox.innerHTML = emptyDisplay("Perda estimada", "Preencha o gasto, o consumo, o peso e os dias.");
            detailsBox.innerHTML = "";
            return;
          }
          const dailyDeficit = expenditure - intake;
          if (dailyDeficit <= 0) {
            const gain = (-dailyDeficit * days) / KCAL_PER_KG;
            resultBox.innerHTML = display("Sem déficit calórico", dailyDeficit === 0 ? "0 kg" : `+${formatNumber(gain, 1)} kg`, dailyDeficit === 0
              ? "comendo o mesmo que gasta, o peso tende a ficar igual"
              : `comendo ${formatNumber(-dailyDeficit)} kcal a mais do que gasta, o peso tende a subir em ${days} dias`);
            detailsBox.innerHTML = "";
            return;
          }
          const weights = simulate(expenditure, intake, startWeight, days);
          const finalWeight = weights[days];
          const totalLoss = startWeight - finalWeight;
          const simpleLoss = (dailyDeficit * days) / KCAL_PER_KG;
          const weeklyLossStart = (dailyDeficit * 7) / KCAL_PER_KG;

          resultBox.innerHTML = display(`Perda estimada em ${days} dias`, `${formatNumber(totalLoss, 1)} kg`, `de ${formatNumber(startWeight, 1)} kg para cerca de ${formatNumber(finalWeight, 1)} kg`, [
            ["Déficit por dia", `${formatNumber(dailyDeficit)} kcal`],
            ["Perda por semana (início)", `${formatNumber(weeklyLossStart, 2)} kg`],
            ["Pela conta simples", `${formatNumber(simpleLoss, 1)} kg`],
          ]);

          const checkpoints = [7, 14, 30, 60, 90, 180, 365, 730].filter((day) => day < days).concat(days);
          const rows = checkpoints.map((day) => `<tr${day === days ? ' class="current-row"' : ""}><td>${day} dias</td><td>${formatNumber(weights[day], 1)} kg</td><td>−${formatNumber(startWeight - weights[day], 1)} kg</td></tr>`).join("");

          // Avisos de saúde conforme o tamanho do déficit
          const warnings = [];
          if (intake < 1200) {
            warnings.push(`Comer menos de 1.200 kcal por dia só é indicado com acompanhamento médico ou de nutricionista: faltam nutrientes e há perda de massa muscular.`);
          }
          if (weeklyLossStart > 1) {
            warnings.push(`Perder mais de 1 kg por semana é considerado rápido demais para a maioria das pessoas e aumenta o risco de efeito sanfona.`);
          }
          const warningHtml = warnings.length ? `<p class="notice notice-warning"><b>Atenção:</b> ${warnings.join(" ")}</p>` : "";

          detailsBox.innerHTML = `${warningHtml}<h3>Seu peso estimado ao longo dos dias</h3>${chartSvg(weights)}
            <table class="data-table"><thead><tr><th>Depois de</th><th>Peso estimado</th><th>Perda</th></tr></thead><tbody>${rows}</tbody></table>
            <p class="notice">Estimativa. A conta usa 7.700 kcal ≈ 1 kg e considera que o corpo gasta menos à medida que emagrece, por isso a perda desacelera com o tempo. Nas primeiras semanas a balança costuma cair mais por causa da água. Hormônios, sono, atividade física e composição corporal mudam o resultado. Procure um médico ou nutricionista antes de começar uma dieta.</p>`;
        }
        onInputs(["weight-loss-expenditure", "weight-loss-intake", "weight-loss-weight", "weight-loss-days"], calculate);
      },
    },

    "agua": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="water-weight">Peso (kg)</label><input id="water-weight" inputmode="decimal" value="70"></div>
            <div class="field"><label for="water-glass">Tamanho do copo (ml)</label><input id="water-glass" inputmode="numeric" value="250"></div>
          </div>
        </div>
        <div id="water-result"></div>`,
      setup() {
        const MILLILITERS_PER_KILO = 35;
        function calculate() {
          const weight = parseNumber(document.getElementById("water-weight").value);
          const glassSize = parseNumber(document.getElementById("water-glass").value);
          const resultBox = document.getElementById("water-result");
          if (!(weight > 0) || !(glassSize > 0)) {
            resultBox.innerHTML = emptyDisplay("Água por dia", "Informe peso e tamanho do copo.");
            return;
          }
          const milliliters = weight * MILLILITERS_PER_KILO;
          resultBox.innerHTML = display("Água por dia", `${formatNumber(milliliters / 1000, 1)} litros`, `${MILLILITERS_PER_KILO} ml por quilo`, [["Copos", `${Math.ceil(milliliters / glassSize)} de ${formatNumber(glassSize, 0)} ml`]]);
        }
        onInputs(["water-weight", "water-glass"], calculate);
      },
    },

    "gestacao": {
      html: `        <div class="calculator-body">
          <div class="field"><label for="pregnancy-dum">Primeiro dia da última menstruação</label><input id="pregnancy-dum" type="date"></div>
        </div>
        <div id="pregnancy-result"></div>`,
      setup() {
        document.getElementById("pregnancy-dum").value = todayIso(-84);
        const PREGNANCY_DAYS = 280;
        function calculate() {
          const lastPeriod = dateFromInput("pregnancy-dum");
          const today = new Date(`${todayIso()}T12:00:00`);
          const resultBox = document.getElementById("pregnancy-result");
          if (!lastPeriod || lastPeriod > today) {
            resultBox.innerHTML = emptyDisplay("Idade gestacional", "Informe uma data até hoje.");
            return;
          }
          const daysPregnant = Math.round((today - lastPeriod) / MILLISECONDS_PER_DAY);
          if (daysPregnant > 300) {
            resultBox.innerHTML = emptyDisplay("Idade gestacional", "A data parece antiga demais. Confira o ano.");
            return;
          }
          const dueDate = new Date(lastPeriod.getTime() + PREGNANCY_DAYS * MILLISECONDS_PER_DAY);
          const weeks = Math.floor(daysPregnant / 7);
          const trimester = weeks < 14 ? "1º trimestre" : weeks < 28 ? "2º trimestre" : "3º trimestre";
          resultBox.innerHTML = display("Idade gestacional", `${weeks} semanas e ${daysPregnant % 7} dias`, trimester, [
            ["Data provável do parto", dueDate.toLocaleDateString("pt-BR")],
            ["Faltam", `${Math.max(0, PREGNANCY_DAYS - daysPregnant)} dias`],
          ]);
        }
        onInputs(["pregnancy-dum"], calculate);
      },
    },

    "unidades": {
      html: `        <div class="calculator-body">
          <div class="segmented" id="unit-groups" role="group" aria-label="Tipo de medida"></div>
          <div class="field-row">
            <div class="field"><label for="unit-value">Valor</label><input id="unit-value" inputmode="decimal" value="10"></div>
            <div class="field"><label for="unit-from">De</label><select id="unit-from"></select></div>
            <div class="field"><label for="unit-to">Para</label><select id="unit-to"></select></div>
          </div>
        </div>
        <div id="unit-result"></div>`,
      setup() {
        // Fatores em relação à unidade base de cada grupo
        const unitGroups = {
          comprimento: { label: "Comprimento", from: "in", to: "cm", units: { m: ["metro", 1], km: ["quilômetro", 1000], cm: ["centímetro", 0.01], mm: ["milímetro", 0.001], in: ["polegada", 0.0254], ft: ["pé", 0.3048], mi: ["milha", 1609.344] } },
          peso: { label: "Peso", from: "lb", to: "kg", units: { kg: ["quilo", 1], g: ["grama", 0.001], t: ["tonelada", 1000], lb: ["libra", 0.45359237], oz: ["onça", 0.028349523125] } },
          volume: { label: "Volume", from: "gal", to: "l", units: { l: ["litro", 1], ml: ["mililitro", 0.001], m3: ["metro cúbico", 1000], gal: ["galão americano", 3.785411784], xic: ["xícara (240 ml)", 0.24] } },
          temperatura: { label: "Temperatura", from: "f", to: "c", units: { c: ["Celsius", null], f: ["Fahrenheit", null], k: ["Kelvin", null] } },
          velocidade: { label: "Velocidade", from: "kmh", to: "ms", units: { ms: ["metro por segundo", 1], kmh: ["km por hora", 1 / 3.6], mph: ["milha por hora", 0.44704], kn: ["nó", 0.514444] } },
          area: { label: "Área", from: "ha", to: "m2", units: { m2: ["metro quadrado", 1], km2: ["km quadrado", 1e6], ha: ["hectare", 10000], alq: ["alqueire paulista", 24200], ac: ["acre", 4046.8564224] } },
          dados: { label: "Dados", from: "gb", to: "mb", units: { b: ["byte", 1], kb: ["kilobyte", 1024], mb: ["megabyte", 1024 ** 2], gb: ["gigabyte", 1024 ** 3], tb: ["terabyte", 1024 ** 4] } },
          tempo: { label: "Tempo", from: "h", to: "min", units: { s: ["segundo", 1], min: ["minuto", 60], h: ["hora", 3600], d: ["dia", 86400], sem: ["semana", 604800] } },
        };
        let currentGroup = "comprimento";
        const toCelsius = (value, unit) => (unit === "f" ? (value - 32) * 5 / 9 : unit === "k" ? value - 273.15 : value);
        const fromCelsius = (value, unit) => (unit === "f" ? value * 9 / 5 + 32 : unit === "k" ? value + 273.15 : value);

        function fillUnitSelects() {
          const group = unitGroups[currentGroup];
          ["unit-from", "unit-to"].forEach((selectId) => {
            document.getElementById(selectId).innerHTML = Object.entries(group.units).map(([unitKey, [unitName]]) => `<option value="${unitKey}">${unitName}</option>`).join("");
          });
          document.getElementById("unit-from").value = group.from;
          document.getElementById("unit-to").value = group.to;
        }
        function calculate() {
          const group = unitGroups[currentGroup];
          const inputValue = parseNumber(document.getElementById("unit-value").value);
          const fromUnit = document.getElementById("unit-from").value;
          const toUnit = document.getElementById("unit-to").value;
          const resultBox = document.getElementById("unit-result");
          if (Number.isNaN(inputValue)) {
            resultBox.innerHTML = emptyDisplay("Resultado", "Digite um número.");
            return;
          }
          const convertedValue = currentGroup === "temperatura" ? fromCelsius(toCelsius(inputValue, fromUnit), toUnit) : (inputValue * group.units[fromUnit][1]) / group.units[toUnit][1];
          resultBox.innerHTML = display("Resultado", formatNumber(convertedValue, 4), `${formatNumber(inputValue, 4)} ${group.units[fromUnit][0]} = ${formatNumber(convertedValue, 4)} ${group.units[toUnit][0]}`);
        }
        const groupBox = document.getElementById("unit-groups");
        groupBox.innerHTML = Object.entries(unitGroups).map(([groupKey, group]) => `<button type="button" data-value="${groupKey}" aria-pressed="${groupKey === currentGroup}">${group.label}</button>`).join("");
        setupSegmented("unit-groups", (newGroup) => { currentGroup = newGroup; fillUnitSelects(); calculate(); });
        fillUnitSelects();
        onInputs(["unit-value", "unit-from", "unit-to"], calculate);
      },
    },

    "idade": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="age-birth">Data de nascimento</label><input id="age-birth" type="date" value="1995-06-15"></div>
            <div class="field"><label for="age-reference">Calcular até</label><input id="age-reference" type="date"></div>
          </div>
        </div>
        <div id="age-result"></div>`,
      setup() {
        document.getElementById("age-reference").value = todayIso();
        function calculate() {
          const birth = dateFromInput("age-birth");
          const reference = dateFromInput("age-reference");
          const resultBox = document.getElementById("age-result");
          if (!birth || !reference || birth > reference) {
            resultBox.innerHTML = emptyDisplay("Idade", "A data de nascimento precisa ser antes da data final.");
            return;
          }
          const difference = yearsMonthsDays(birth, reference);
          let nextBirthday = new Date(reference.getFullYear(), birth.getMonth(), birth.getDate(), 12);
          if (nextBirthday < reference) {
            nextBirthday = new Date(reference.getFullYear() + 1, birth.getMonth(), birth.getDate(), 12);
          }
          const daysToBirthday = Math.round((nextBirthday - reference) / MILLISECONDS_PER_DAY);
          resultBox.innerHTML = display("Idade", `${difference.years} anos`, `${difference.months} meses e ${difference.days} dias`, [
            ["Dias vividos", formatNumber(Math.round((reference - birth) / MILLISECONDS_PER_DAY), 0)],
            ["Próximo aniversário", daysToBirthday === 0 ? "hoje! 🎉" : `em ${daysToBirthday} dias`],
            ["Nasceu numa", WEEKDAYS[birth.getDay()]],
          ]);
        }
        onInputs(["age-birth", "age-reference"], calculate);
      },
    },

    "diferenca-datas": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="difference-start">Data inicial</label><input id="difference-start" type="date"></div>
            <div class="field"><label for="difference-end">Data final</label><input id="difference-end" type="date"></div>
          </div>
        </div>
        <div id="difference-result"></div>`,
      setup() {
        document.getElementById("difference-start").value = todayIso();
        document.getElementById("difference-end").value = todayIso(100);
        function calculate() {
          let start = dateFromInput("difference-start");
          let end = dateFromInput("difference-end");
          const resultBox = document.getElementById("difference-result");
          if (!start || !end) {
            resultBox.innerHTML = emptyDisplay("Diferença", "Escolha as duas datas.");
            return;
          }
          if (start > end) {
            [start, end] = [end, start];
          }
          const totalDays = Math.round((end - start) / MILLISECONDS_PER_DAY);
          const difference = yearsMonthsDays(start, end);
          resultBox.innerHTML = display("Diferença", `${formatNumber(totalDays, 0)} dias`, `${difference.years} anos, ${difference.months} meses e ${difference.days} dias`, [
            ["Semanas", `${Math.floor(totalDays / 7)} e ${totalDays % 7} dias`],
            ["Horas", formatNumber(totalDays * 24, 0)],
          ]);
        }
        onInputs(["difference-start", "difference-end"], calculate);
      },
    },

    "somar-dias": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="add-start">Data de partida</label><input id="add-start" type="date"></div>
            <div class="field"><label for="add-days">Dias (use negativo para voltar)</label><input id="add-days" inputmode="numeric" value="30"></div>
          </div>
        </div>
        <div id="add-result"></div>`,
      setup() {
        document.getElementById("add-start").value = todayIso();
        function calculate() {
          const start = dateFromInput("add-start");
          const days = parseInt(document.getElementById("add-days").value, 10);
          const resultBox = document.getElementById("add-result");
          if (!start || Number.isNaN(days)) {
            resultBox.innerHTML = emptyDisplay("Data resultante", "Escolha a data e o número de dias.");
            return;
          }
          const resultDate = new Date(start.getTime() + days * MILLISECONDS_PER_DAY);
          resultBox.innerHTML = display("Data resultante", resultDate.toLocaleDateString("pt-BR"), WEEKDAYS[resultDate.getDay()]);
        }
        onInputs(["add-start", "add-days"], calculate);
      },
    },

    "dias-uteis": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="business-start">Data inicial</label><input id="business-start" type="date"></div>
            <div class="field"><label for="business-end">Data final</label><input id="business-end" type="date"></div>
          </div>
          <label class="check"><input type="checkbox" id="business-carnival" checked> Considerar o Carnaval (segunda e terça) como folga</label>
        </div>
        <div id="business-result"></div>`,
      setup() {
        document.getElementById("business-start").value = todayIso();
        document.getElementById("business-end").value = todayIso(60);

        function calculate() {
          let start = dateFromInput("business-start");
          let end = dateFromInput("business-end");
          const includeCarnival = document.getElementById("business-carnival").checked;
          const resultBox = document.getElementById("business-result");
          if (!start || !end) {
            resultBox.innerHTML = emptyDisplay("Dias úteis", "Escolha as duas datas.");
            return;
          }
          if (start > end) {
            [start, end] = [end, start];
          }
          const holidaysByYear = {};
          let businessDays = 0;
          let holidaysOnWeekdays = 0;
          for (let current = new Date(start); current <= end; current = new Date(current.getTime() + MILLISECONDS_PER_DAY)) {
            const year = current.getFullYear();
            holidaysByYear[year] ??= holidaysOfYear(year, includeCarnival);
            const isWeekend = current.getDay() === 0 || current.getDay() === 6;
            const isHoliday = holidaysByYear[year].has(current.toISOString().slice(0, 10));
            if (!isWeekend && isHoliday) holidaysOnWeekdays += 1;
            if (!isWeekend && !isHoliday) businessDays += 1;
          }
          const totalDays = Math.round((end - start) / MILLISECONDS_PER_DAY) + 1;
          resultBox.innerHTML = display("Dias úteis", formatNumber(businessDays, 0), "contando a data inicial e a final", [
            ["Dias corridos", formatNumber(totalDays, 0)],
            ["Feriados em dia de semana", holidaysOnWeekdays],
          ]);
        }
        onInputs(["business-start", "business-end", "business-carnival"], calculate);
      },
    },

    "somar-horas": {
      html: `        <div class="calculator-body">
          <div class="field">
            <label for="hours-input">Horários (um por linha)</label>
            <textarea id="hours-input" rows="5">08:30
07:45
-01:15
09:10</textarea>
            <span class="field-hint">Use o formato hh:mm. Coloque um sinal de menos para subtrair.</span>
          </div>
        </div>
        <div id="hours-result"></div>`,
      setup() {
        function calculate() {
          const lines = document.getElementById("hours-input").value.split("\n").map((line) => line.trim()).filter(Boolean);
          const resultBox = document.getElementById("hours-result");
          let totalMinutes = 0;
          let invalidLines = 0;
          lines.forEach((line) => {
            const match = line.match(/^(-?)(\d{1,4}):([0-5]\d)$/);
            if (!match) {
              invalidLines += 1;
              return;
            }
            const minutes = parseInt(match[2], 10) * 60 + parseInt(match[3], 10);
            totalMinutes += match[1] === "-" ? -minutes : minutes;
          });
          if (lines.length === 0) {
            resultBox.innerHTML = emptyDisplay("Total", "Digite pelo menos um horário.");
            return;
          }
          const sign = totalMinutes < 0 ? "-" : "";
          const absoluteMinutes = Math.abs(totalMinutes);
          const formatted = `${sign}${Math.floor(absoluteMinutes / 60)}:${String(absoluteMinutes % 60).padStart(2, "0")}`;
          resultBox.innerHTML = display("Total", formatted, invalidLines ? `${invalidLines} linha(s) ignorada(s) por estar fora do formato hh:mm` : `${lines.length} horários somados`, [
            ["Em horas decimais", formatNumber(totalMinutes / 60, 2)],
            ["Em minutos", formatNumber(totalMinutes, 0)],
          ]);
        }
        onInputs(["hours-input"], calculate);
      },
    },

    "consumo": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="fuel-distance">Distância percorrida (km)</label><input id="fuel-distance" inputmode="decimal" value="420"></div>
            <div class="field"><label for="fuel-liters">Litros abastecidos</label><input id="fuel-liters" inputmode="decimal" value="35"></div>
            <div class="field"><label for="fuel-price">Preço do litro (R$)</label><input id="fuel-price" inputmode="numeric" data-money value="6,19"></div>
          </div>
        </div>
        <div id="fuel-result"></div>`,
      setup() {
        function calculate() {
          const [distance, liters, price] = ["fuel-distance", "fuel-liters", "fuel-price"].map((id) => parseNumber(document.getElementById(id).value));
          const resultBox = document.getElementById("fuel-result");
          if (!(distance > 0) || !(liters > 0)) {
            resultBox.innerHTML = emptyDisplay("Consumo", "Informe distância e litros.");
            return;
          }
          const kilometersPerLiter = distance / liters;
          const gridItems = [["Litros por 100 km", formatNumber((liters / distance) * 100, 2)]];
          if (price > 0) {
            gridItems.push(["Custo por km", formatMoney(price / kilometersPerLiter)], ["Gasto total", formatMoney(price * liters)]);
          }
          resultBox.innerHTML = display("Consumo", `${formatNumber(kilometersPerLiter, 2)} km/l`, "", gridItems);
        }
        onInputs(["fuel-distance", "fuel-liters", "fuel-price"], calculate);
      },
    },

    "alcool-gasolina": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="ethanol-price">Preço do etanol (R$)</label><input id="ethanol-price" inputmode="numeric" data-money value="4,09"></div>
            <div class="field"><label for="gasoline-price">Preço da gasolina (R$)</label><input id="gasoline-price" inputmode="numeric" data-money value="6,19"></div>
            <div class="field"><label for="ethanol-limit">Limite (%)</label><input id="ethanol-limit" inputmode="numeric" value="70"></div>
          </div>
          <span class="field-hint">Se você sabe o consumo do seu carro, use como limite: km/l no etanol ÷ km/l na gasolina × 100.</span>
        </div>
        <div id="ethanol-result"></div>`,
      setup() {
        function calculate() {
          const [ethanol, gasoline, limit] = ["ethanol-price", "gasoline-price", "ethanol-limit"].map((id) => parseNumber(document.getElementById(id).value));
          const resultBox = document.getElementById("ethanol-result");
          if (!(ethanol > 0) || !(gasoline > 0) || !(limit > 0)) {
            resultBox.innerHTML = emptyDisplay("Compensa", "Preencha os preços.");
            return;
          }
          const ratio = (ethanol / gasoline) * 100;
          const ethanolWins = ratio <= limit;
          resultBox.innerHTML = display("Compensa", ethanolWins ? "ETANOL" : "GASOLINA", `o etanol custa ${formatNumber(ratio, 1)}% do preço da gasolina (limite ${formatNumber(limit, 0)}%)`, [
            ["Etanol compensa até", formatMoney(gasoline * (limit / 100))],
          ]);
        }
        onInputs(["ethanol-price", "gasoline-price", "ethanol-limit"], calculate);
      },
    },

    "eletrico-vs-combustao": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="ev-km">Quilômetros por mês</label><input id="ev-km" inputmode="numeric" value="1200"></div>
            <div class="field"><label for="ev-extra-price">Quanto o elétrico custa a mais na compra (R$, opcional)</label><input id="ev-extra-price" inputmode="numeric" data-money placeholder="ex.: 30.000,00"></div>
          </div>
          <h3>Carro elétrico (recarga na tomada)</h3>
          <div class="field-row">
            <div class="field"><label for="ev-kwh-price">Preço do kWh (R$)</label><input id="ev-kwh-price" inputmode="decimal" value="0,95"></div>
            <div class="field"><label for="ev-efficiency">Consumo (km por kWh)</label><input id="ev-efficiency" inputmode="decimal" value="6,5"></div>
            <div class="field"><label for="ev-loss">Perda na recarga (%)</label><input id="ev-loss" inputmode="decimal" value="10"></div>
          </div>
          <span class="field-hint">Preço do kWh: divida o total da conta de luz (com impostos) pelos kWh consumidos. O consumo do carro está no Inmetro/PBE Veicular ou no painel do carro. Parte da energia se perde na recarga, cerca de 10% na tomada comum.</span>
          <h3>Carro a combustão</h3>
          <div class="field-row">
            <div class="field"><label for="ev-fuel">Combustível</label><select id="ev-fuel"><option value="gasolina">Gasolina</option><option value="etanol">Etanol</option></select></div>
            <div class="field"><label for="ev-fuel-price">Preço do litro (R$)</label><input id="ev-fuel-price" inputmode="numeric" data-money value="6,19"></div>
            <div class="field"><label for="ev-fuel-efficiency">Consumo (km por litro)</label><input id="ev-fuel-efficiency" inputmode="decimal" value="12"></div>
          </div>
        </div>
        <div id="ev-result"></div>
        <div class="calculator-body" id="ev-table"></div>`,
      setup() {
        // Consumos típicos para preencher ao trocar o combustível (a pessoa pode mudar)
        const typicalEfficiency = { gasolina: "12", etanol: "8,5" };
        const fuelSelect = document.getElementById("ev-fuel");
        fuelSelect.addEventListener("change", () => {
          document.getElementById("ev-fuel-efficiency").value = typicalEfficiency[fuelSelect.value];
        });

        function calculate() {
          const [kmPerMonth, kwhPrice, kmPerKwh, lossPercent, fuelPrice, kmPerLiter] = ["ev-km", "ev-kwh-price", "ev-efficiency", "ev-loss", "ev-fuel-price", "ev-fuel-efficiency"].map((id) => parseNumber(document.getElementById(id).value));
          const extraPrice = parseNumber(document.getElementById("ev-extra-price").value) || 0;
          const fuelName = fuelSelect.value === "etanol" ? "etanol" : "gasolina";
          const resultBox = document.getElementById("ev-result");
          const tableBox = document.getElementById("ev-table");
          const loss = Number.isNaN(lossPercent) ? 0 : lossPercent;
          if (!(kmPerMonth > 0 && kwhPrice > 0 && kmPerKwh > 0 && fuelPrice > 0 && kmPerLiter > 0) || loss < 0 || loss >= 100) {
            resultBox.innerHTML = emptyDisplay("Economia por mês", "Preencha todos os campos com valores maiores que zero.");
            tableBox.innerHTML = "";
            return;
          }
          // Com perda de 10%, para colocar 1 kWh na bateria a tomada entrega 1 ÷ 0,9 kWh
          const electricCostPerKm = kwhPrice / (kmPerKwh * (1 - loss / 100));
          const combustionCostPerKm = fuelPrice / kmPerLiter;
          const electricMonthly = electricCostPerKm * kmPerMonth;
          const combustionMonthly = combustionCostPerKm * kmPerMonth;
          const monthlySavings = combustionMonthly - electricMonthly;
          const electricIsCheaper = monthlySavings > 0;

          let paybackText = "";
          if (extraPrice > 0) {
            paybackText = electricIsCheaper
              ? ` · a diferença de ${formatMoney(extraPrice)} na compra se paga em ${formatNumber(extraPrice / monthlySavings / 12, 1)} anos`
              : " · com esses valores, a diferença na compra não se paga";
          }
          resultBox.innerHTML = display(
            electricIsCheaper ? "O elétrico economiza por mês" : `A ${fuelName} sai mais barata por mês`,
            formatMoney(Math.abs(monthlySavings)),
            `${formatMoney(Math.abs(monthlySavings) * 12)} por ano rodando ${formatNumber(kmPerMonth)} km por mês${paybackText}`,
            [
              ["Elétrico por km", formatMoney(electricCostPerKm)],
              [`${fuelName === "etanol" ? "Etanol" : "Gasolina"} por km`, formatMoney(combustionCostPerKm)],
              ["Elétrico por mês", formatMoney(electricMonthly)],
              [`${fuelName === "etanol" ? "Etanol" : "Gasolina"} por mês`, formatMoney(combustionMonthly)],
              ["Diferença por km", `${formatNumber(Math.abs(1 - electricCostPerKm / combustionCostPerKm) * 100, 0)}%`],
            ],
          );

          const rows = [1, 3, 5, 10].map((years) => {
            const months = years * 12;
            return `<tr><td>${years} ${years === 1 ? "ano" : "anos"} (${formatNumber(kmPerMonth * months)} km)</td><td>${formatMoney(electricMonthly * months)}</td><td>${formatMoney(combustionMonthly * months)}</td><td>${formatMoney(monthlySavings * months)}</td></tr>`;
          }).join("");
          tableBox.innerHTML = `<h3>Gasto com energia ao longo do tempo</h3>
            <table class="data-table"><thead><tr><th>Período</th><th>Elétrico</th><th>${fuelName === "etanol" ? "Etanol" : "Gasolina"}</th><th>Economia</th></tr></thead><tbody>${rows}</tbody></table>
            <p class="notice">Compara só o gasto com energia (tomada × bomba). Não entram manutenção (em geral menor no elétrico), seguro, IPVA, desvalorização nem troca de bateria. Recarga em eletroposto costuma custar bem mais que em casa. Os preços mudam com frequência: use os da sua cidade.</p>`;
        }
        onInputs(["ev-km", "ev-extra-price", "ev-kwh-price", "ev-efficiency", "ev-loss", "ev-fuel", "ev-fuel-price", "ev-fuel-efficiency"], calculate);
      },
    },

    "ipva": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="ipva-state">Estado</label><select id="ipva-state"></select></div>
            <div class="field"><label for="ipva-type">Veículo</label><select id="ipva-type"><option value="car">Carro</option><option value="moto">Moto</option></select></div>
            <div class="field"><label for="ipva-value">Valor na tabela FIPE (R$)</label><input id="ipva-value" inputmode="numeric" data-money value="60.000,00"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="ipva-rate">Alíquota do IPVA (%)</label><input id="ipva-rate" inputmode="decimal"><span class="field-hint" id="ipva-rate-hint"></span></div>
            <div class="field"><label for="ipva-licensing">Taxa de licenciamento (R$)</label><input id="ipva-licensing" inputmode="numeric" data-money><span class="field-hint" id="ipva-licensing-hint"></span></div>
          </div>
        </div>
        <div id="ipva-result"></div>
        <div class="calculator-body"><p class="notice">Estimativa. A alíquota pode mudar conforme potência, combustível e tipo do veículo, e há estados com desconto para pagamento à vista, isenção para carros antigos, elétricos ou pessoas com deficiência. O valor oficial está no site da Secretaria da Fazenda (Sefaz) e do Detran do seu estado.</p></div>`,
      setup() {
        // IPVA 2026 por estado (carro de passeio e moto) e taxa de licenciamento 2026 divulgada pelos Detrans.
        // carRange/motoRange: o estado cobra alíquotas diferentes conforme potência ou combustível.
        // licensing null = valor não encontrado; a pessoa preenche.
        const STATES = {
          AC: { name: "Acre", car: 2, moto: 1, licensing: 200.25 },
          AL: { name: "Alagoas", car: 3, carRange: "2% a 3,25%", moto: 3, motoRange: "2% a 3,25%", licensing: 36.03 },
          AP: { name: "Amapá", car: 3, moto: 1.5, licensing: 128.54 },
          AM: { name: "Amazonas", car: 1.5, moto: 2, licensing: 122.88 },
          BA: { name: "Bahia", car: 2.5, carRange: "2,5% a 3%", moto: 1, licensing: 173.50 },
          CE: { name: "Ceará", car: 3, carRange: "2,5% a 3,5%", moto: 2, motoRange: "2% a 3,5%", licensing: null },
          DF: { name: "Distrito Federal", car: 3, moto: 2, licensing: 102.00 },
          ES: { name: "Espírito Santo", car: 2, moto: 1, licensing: null },
          GO: { name: "Goiás", car: 3.75, carRange: "3% a 3,75%", moto: 3, licensing: 251.25 },
          MA: { name: "Maranhão", car: 2.5, carRange: "2,5% a 3%", moto: 2, motoRange: "1% a 2,5%", licensing: null },
          MT: { name: "Mato Grosso", car: 3, carRange: "2% a 4%", moto: 2, motoRange: "1% a 3,5%", licensing: 140.00 },
          MS: { name: "Mato Grosso do Sul", car: 3, carRange: "3% a 4,5%", moto: 2, licensing: 235.28 },
          MG: { name: "Minas Gerais", car: 4, carRange: "2% a 4%", moto: 2, licensing: 35.62 },
          PA: { name: "Pará", car: 2.5, moto: 1, licensing: 288.08 },
          PB: { name: "Paraíba", car: 2.5, moto: 2.5, licensing: 206.55 },
          PR: { name: "Paraná", car: 1.9, moto: 1.9, motoRange: "1% a 1,9%", licensing: 90.94 },
          PE: { name: "Pernambuco", car: 2.4, moto: 2, motoRange: "1% a 2%", licensing: null },
          PI: { name: "Piauí", car: 2.5, carRange: "2,5% a 3%", moto: 2, licensing: 129.60 },
          RJ: { name: "Rio de Janeiro", car: 4, carRange: "3% a 4%", moto: 2, licensing: 293.71 },
          RN: { name: "Rio Grande do Norte", car: 3, moto: 2, licensing: null },
          RS: { name: "Rio Grande do Sul", car: 3, moto: 2, licensing: 109.27 },
          RO: { name: "Rondônia", car: 3, moto: 2, licensing: 220.41 },
          RR: { name: "Roraima", car: 3, moto: 2, licensing: null },
          SC: { name: "Santa Catarina", car: 2, moto: 1, licensing: null },
          SP: { name: "São Paulo", car: 4, moto: 2, licensing: 174.08 },
          SE: { name: "Sergipe", car: 2.5, carRange: "2,5% a 3%", moto: 2, licensing: 207.36 },
          TO: { name: "Tocantins", car: 3, carRange: "2,5% a 3,5%", moto: 3, motoRange: "2,5% a 3,5%", licensing: 79.63 },
        };
        const stateSelect = document.getElementById("ipva-state");
        const typeSelect = document.getElementById("ipva-type");
        stateSelect.innerHTML = Object.entries(STATES).map(([code, state]) => `<option value="${code}"${code === "SP" ? " selected" : ""}>${state.name} (${code})</option>`).join("");

        // Ao trocar estado ou tipo, preenche alíquota e taxa sugeridas (a pessoa pode ajustar)
        function fillStateDefaults() {
          const state = STATES[stateSelect.value];
          const isMoto = typeSelect.value === "moto";
          const range = isMoto ? state.motoRange : state.carRange;
          document.getElementById("ipva-rate").value = formatNumber(isMoto ? state.moto : state.car, 2);
          document.getElementById("ipva-rate-hint").textContent = range
            ? `Neste estado a alíquota varia de ${range} conforme o veículo. Confira a sua na Sefaz.`
            : "Alíquota de 2026 para este estado.";
          document.getElementById("ipva-licensing").value = state.licensing ? formatMoneyInput(state.licensing) : "";
          document.getElementById("ipva-licensing-hint").textContent = state.licensing
            ? "Valor de 2026 divulgado pelo Detran. Confira antes de pagar."
            : "Não encontramos o valor de 2026: veja no site do Detran do seu estado.";
        }
        function calculate() {
          const fipeValue = parseNumber(document.getElementById("ipva-value").value);
          const ratePercent = parseNumber(document.getElementById("ipva-rate").value);
          const licensing = parseNumber(document.getElementById("ipva-licensing").value) || 0;
          const resultBox = document.getElementById("ipva-result");
          if (!(fipeValue > 0) || Number.isNaN(ratePercent) || ratePercent < 0) {
            resultBox.innerHTML = emptyDisplay("IPVA + licenciamento", "Informe o valor do veículo e a alíquota.");
            return;
          }
          const ipva = roundCents(fipeValue * ratePercent / 100);
          const total = ipva + licensing;
          resultBox.innerHTML = display("IPVA + licenciamento 2026", formatMoney(total), `${STATES[stateSelect.value].name} · alíquota de ${formatNumber(ratePercent, 2)}% sobre ${formatMoney(fipeValue)}`, [
            ["IPVA", formatMoney(ipva)],
            ["Licenciamento", licensing > 0 ? formatMoney(licensing) : "não informado"],
            ["IPVA em 3 parcelas", formatMoney(ipva / 3)],
            ["Guardar por mês", formatMoney(total / 12)],
          ]);
        }
        stateSelect.addEventListener("change", () => { fillStateDefaults(); calculate(); });
        typeSelect.addEventListener("change", () => { fillStateDefaults(); calculate(); });
        fillStateDefaults();
        onInputs(["ipva-value", "ipva-rate", "ipva-licensing"], calculate);
      },
    },

    "depreciacao-veiculo": {
      html: `        <div class="calculator-body depreciation-modes-row">
          <div class="segmented" id="depreciation-modes" role="group" aria-label="Como calcular">
            <button type="button" data-value="fipe" aria-pressed="true">Consultar a FIPE (histórico real)</button>
            <button type="button" data-value="estimate" aria-pressed="false">Estimar o futuro</button>
          </div>
        </div>
        <div id="depreciation-fipe">
          <div class="calculator-body">
            <div class="field-row">
              <div class="field"><label for="fipe-type">Veículo</label><select id="fipe-type"><option value="1">Carro</option><option value="2">Moto</option><option value="3">Caminhão</option></select></div>
              <div class="field"><label for="fipe-brand">Marca</label><select id="fipe-brand" disabled><option>carregando…</option></select></div>
            </div>
            <div class="field"><label for="fipe-model-filter">Modelo</label><input id="fipe-model-filter" placeholder="Digite para filtrar (ex.: Gol 1.0)" autocomplete="off" disabled><select id="fipe-model" disabled><option value="">Escolha a marca primeiro</option></select></div>
            <div class="field-row">
              <div class="field"><label for="fipe-year">Ano do modelo</label><select id="fipe-year" disabled><option value="">Escolha o modelo primeiro</option></select></div>
            </div>
            <span class="field-hint">Valores da Tabela FIPE no mesmo mês de cada um dos últimos 5 anos.</span>
          </div>
          <div id="fipe-result"></div>
          <div class="calculator-body" id="fipe-details" hidden></div>
        </div>
        <div id="depreciation-estimate" hidden>
        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="depreciation-value">Valor atual na FIPE (R$)</label><input id="depreciation-value" inputmode="numeric" data-money value="80.000,00"></div>
            <div class="field"><label for="depreciation-category">Categoria</label><select id="depreciation-category"></select></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="depreciation-first">Perda no 1º ano (%)</label><input id="depreciation-first" inputmode="decimal"></div>
            <div class="field"><label for="depreciation-yearly">Perda nos anos seguintes (% ao ano)</label><input id="depreciation-yearly" inputmode="decimal"></div>
          </div>
          <label class="check"><input type="checkbox" id="depreciation-new" checked> O carro é zero km (no 1º ano a perda é maior)</label>
          <span class="field-hint">Os percentuais são médias aproximadas do mercado para cada categoria e podem ser ajustados.</span>
        </div>
        <div id="depreciation-result"></div>
        <div class="calculator-body" id="depreciation-table"></div>
        </div>`,
      setup() {
        // Médias aproximadas de desvalorização no Brasil (estimativas; modelos específicos variam bastante)
        const CATEGORIES = {
          compact: { name: "Hatch ou sedã compacto", firstYear: 15, yearly: 8 },
          midsize: { name: "Sedã médio", firstYear: 17, yearly: 9 },
          suv: { name: "SUV", firstYear: 15, yearly: 8 },
          pickup: { name: "Picape", firstYear: 12, yearly: 6 },
          premium: { name: "Importado ou premium", firstYear: 20, yearly: 11 },
          electric: { name: "Elétrico", firstYear: 22, yearly: 12 },
          motorcycle: { name: "Moto", firstYear: 12, yearly: 7 },
        };
        const categorySelect = document.getElementById("depreciation-category");
        categorySelect.innerHTML = Object.entries(CATEGORIES).map(([key, category]) => `<option value="${key}">${category.name}</option>`).join("");
        function fillCategoryRates() {
          const category = CATEGORIES[categorySelect.value];
          document.getElementById("depreciation-first").value = formatNumber(category.firstYear);
          document.getElementById("depreciation-yearly").value = formatNumber(category.yearly);
        }
        function calculate() {
          const value = parseNumber(document.getElementById("depreciation-value").value);
          const firstYearPercent = parseNumber(document.getElementById("depreciation-first").value);
          const yearlyPercent = parseNumber(document.getElementById("depreciation-yearly").value);
          const isNew = document.getElementById("depreciation-new").checked;
          const resultBox = document.getElementById("depreciation-result");
          const tableBox = document.getElementById("depreciation-table");
          document.getElementById("depreciation-first").disabled = !isNew;
          if (!(value > 0) || [firstYearPercent, yearlyPercent].some((percent) => Number.isNaN(percent) || percent < 0 || percent >= 100)) {
            resultBox.innerHTML = emptyDisplay("Perda de valor", "Informe o valor e percentuais entre 0 e 100.");
            tableBox.innerHTML = "";
            return;
          }
          // Valor no fim de cada ano: 1º ano com a perda maior (se zero km), depois a perda anual
          function valueAfter(years) {
            let current = value;
            for (let year = 1; year <= years; year++) {
              const lossPercent = year === 1 && isNew ? firstYearPercent : yearlyPercent;
              current *= 1 - lossPercent / 100;
            }
            return current;
          }
          const valueInThreeYears = valueAfter(3);
          resultBox.innerHTML = display("Perda estimada em 3 anos", formatMoney(value - valueInThreeYears), `o carro passaria a valer cerca de ${formatMoney(valueInThreeYears)}`, [
            ["Em 1 ano", formatMoney(value - valueAfter(1))],
            ["Em 5 anos", formatMoney(value - valueAfter(5))],
            ["Perda média por mês (3 anos)", formatMoney((value - valueInThreeYears) / 36)],
          ]);
          const rows = [1, 2, 3, 4, 5].map((years) => {
            const futureValue = valueAfter(years);
            return `<tr><td>${years} ${years === 1 ? "ano" : "anos"}</td><td>${formatMoney(futureValue)}</td><td>${formatMoney(value - futureValue)} (${formatNumber((1 - futureValue / value) * 100, 1)}%)</td></tr>`;
          }).join("");
          tableBox.innerHTML = `<table class="data-table"><thead><tr><th>Daqui a</th><th>Valor estimado</th><th>Perda acumulada</th></tr></thead><tbody>${rows}</tbody></table>
            <p class="notice">Estimativa com médias de mercado, não é a tabela FIPE futura. Quilometragem, estado de conservação, cor, versão, procura pelo modelo e lançamentos de novas gerações mudam muito o valor de revenda.</p>`;
        }
        categorySelect.addEventListener("change", () => { fillCategoryRates(); calculate(); });
        fillCategoryRates();
        onInputs(["depreciation-value", "depreciation-first", "depreciation-yearly", "depreciation-new"], calculate);

        /* ---------- Aba "Consultar a FIPE": histórico real dos últimos 5 anos ---------- */
        // Chart.js só carrega quando o gráfico for desenhado (cdnjs, com verificação de integridade)
        const CHART_JS = ["https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js", "sha512-CQBWl4fJHWbryGE+Pc7UAxWMUMNMWzWxF4SQo9CgkJIN1kx6djDQZjh3Y8SZ1d+6I+1zze6Z7kHXO7q3UyZAWw=="];
        let chartJsPromise = null;
        let chart = null;
        function loadChartJs() {
          if (!chartJsPromise) {
            chartJsPromise = window.Chart ? Promise.resolve(window.Chart) : new Promise((resolve, reject) => {
              const script = document.createElement("script");
              Object.assign(script, { src: CHART_JS[0], integrity: CHART_JS[1], crossOrigin: "anonymous" });
              script.onload = () => resolve(window.Chart);
              script.onerror = reject;
              document.head.appendChild(script);
            });
          }
          return chartJsPromise;
        }

        const typeSelect = document.getElementById("fipe-type");
        const brandSelect = document.getElementById("fipe-brand");
        const modelFilter = document.getElementById("fipe-model-filter");
        const modelSelect = document.getElementById("fipe-model");
        const yearSelect = document.getElementById("fipe-year");
        const fipeResult = document.getElementById("fipe-result");
        const fipeDetails = document.getElementById("fipe-details");
        let allModels = [];
        let requestNumber = 0; // evita que uma resposta antiga apareça depois de uma escolha nova

        async function fetchFipe(path, params) {
          const response = await fetch(`/api/fipe/${path}?${new URLSearchParams(params)}`, { headers: { Accept: "application/json" }, credentials: "same-origin" });
          const data = await response.json().catch(() => ({}));
          if (!response.ok) {
            throw new Error(data.error || "A Tabela FIPE não respondeu agora.");
          }
          return data;
        }
        // Histórico pelo Cloudflare Worker (cloudflare/fipe-worker.js), chamado DO NAVEGADOR: a FIPE só responde
        // a acessos do Brasil e o servidor do site fica nos EUA. Sem Worker configurado ou se ele falhar, usa o servidor.
        async function fetchHistoryFromWorker(params) {
          const workerUrl = document.getElementById("calculator")?.dataset.fipeWorker;
          if (!workerUrl) {
            throw new Error("sem Worker");
          }
          const response = await fetch(`${workerUrl}/historico?${new URLSearchParams(params)}`, { headers: { Accept: "application/json" } });
          const data = await response.json().catch(() => ({}));
          if (!response.ok || !Array.isArray(data.points)) {
            throw new Error("Worker indisponível");
          }
          return data;
        }
        function fillSelect(select, items, placeholder) {
          select.innerHTML = `<option value="">${placeholder}</option>` + items.map((item) => `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`).join("");
          select.disabled = items.length === 0;
        }
        function resetBelow(level) {
          if (level <= 1) {
            allModels = [];
            fillSelect(modelSelect, [], "Escolha a marca primeiro");
            modelFilter.value = "";
            modelFilter.disabled = true;
          }
          if (level <= 2) {
            fillSelect(yearSelect, [], "Escolha o modelo primeiro");
          }
          fipeResult.innerHTML = emptyDisplay("Desvalorização na FIPE", "Escolha marca, modelo e ano.");
          fipeDetails.hidden = true;
        }
        function showFipeError(message) {
          fipeResult.innerHTML = emptyDisplay("Desvalorização na FIPE", escapeHtml(message) + " Você pode usar a aba \"Estimar o futuro\".");
          fipeDetails.hidden = true;
        }

        async function loadBrands() {
          const thisRequest = ++requestNumber;
          brandSelect.disabled = true;
          brandSelect.innerHTML = "<option>carregando…</option>";
          resetBelow(1);
          try {
            const data = await fetchFipe("marcas", { tipo: typeSelect.value });
            if (thisRequest === requestNumber) {
              fillSelect(brandSelect, data.items, "Escolha a marca");
            }
          } catch (error) {
            brandSelect.innerHTML = "<option>indisponível</option>";
            showFipeError(error.message);
          }
        }
        async function loadModels() {
          resetBelow(1);
          if (!brandSelect.value) {
            return;
          }
          const thisRequest = ++requestNumber;
          modelSelect.innerHTML = "<option>carregando…</option>";
          try {
            const data = await fetchFipe("modelos", { tipo: typeSelect.value, marca: brandSelect.value });
            if (thisRequest === requestNumber) {
              allModels = data.items;
              fillSelect(modelSelect, allModels, `Escolha o modelo (${allModels.length})`);
              modelFilter.disabled = false;
            }
          } catch (error) {
            showFipeError(error.message);
          }
        }
        // Filtro do modelo: as marcas têm centenas de versões
        function filterModels() {
          const words = normalizeText(modelFilter.value).split(/\s+/).filter(Boolean);
          const matches = allModels.filter((item) => words.every((word) => normalizeText(item.label).includes(word)));
          fillSelect(modelSelect, matches, matches.length ? `Escolha o modelo (${matches.length})` : "Nenhum modelo com esse nome");
          resetBelow(2);
        }
        async function loadYears() {
          resetBelow(2);
          if (!modelSelect.value) {
            return;
          }
          const thisRequest = ++requestNumber;
          yearSelect.innerHTML = "<option>carregando…</option>";
          try {
            const data = await fetchFipe("anos", { tipo: typeSelect.value, marca: brandSelect.value, modelo: modelSelect.value });
            if (thisRequest === requestNumber) {
              const items = data.items.map((item) => ({ ...item, label: item.value.startsWith("32000") ? "Zero km" : item.label }));
              fillSelect(yearSelect, items, "Escolha o ano");
            }
          } catch (error) {
            showFipeError(error.message);
          }
        }

        async function loadHistory() {
          fipeDetails.hidden = true;
          if (!yearSelect.value) {
            return;
          }
          const thisRequest = ++requestNumber;
          fipeResult.innerHTML = emptyDisplay("Desvalorização na FIPE", "Consultando a Tabela FIPE dos últimos 5 anos…");
          window.Vibe2000?.registerToolUse("depreciacao-veiculo");
          const params = { tipo: typeSelect.value, marca: brandSelect.value, modelo: modelSelect.value, ano: yearSelect.value };
          let history;
          try {
            history = await fetchHistoryFromWorker(params).catch(() => fetchFipe("historico", params));
          } catch (error) {
            if (thisRequest === requestNumber) {
              showFipeError(error.message);
            }
            return;
          }
          if (thisRequest !== requestNumber) {
            return;
          }
          showHistory(history);
        }

        function showHistory({ vehicle, points, historyUnavailable }) {
          const first = points[0];
          const last = points[points.length - 1];
          const years = points.length - 1;
          const vehicleLabel = `${vehicle.brand} ${vehicle.model} · ${vehicle.year}${vehicle.fuel ? " " + vehicle.fuel : ""}`;
          if (years < 1) {
            // Sem histórico: o site da FIPE não respondeu e só veio o valor de hoje pela fonte reserva
            const reason = historyUnavailable ? "o histórico dos anos anteriores está indisponível agora; tente mais tarde ou use a aba \"Estimar o futuro\"" : "ainda não há 1 ano de histórico na tabela";
            fipeResult.innerHTML = display("Valor na FIPE hoje", formatMoney(last.price), `${escapeHtml(vehicleLabel)} · ${reason}`);
            fipeDetails.hidden = true;
            return;
          }
          const totalChange = last.price / first.price - 1;
          // Variação média por ano (composta): quanto o valor mudou, em média, a cada ano
          const yearlyChange = Math.pow(last.price / first.price, 1 / years) - 1;
          const lostValue = totalChange < 0;
          const percent = (rate) => `${rate > 0 ? "+" : ""}${formatNumber(rate * 100, 1)}%`;
          fipeResult.innerHTML = display(
            lostValue ? `Desvalorizou em ${years} ${years === 1 ? "ano" : "anos"}` : `Valorizou em ${years} ${years === 1 ? "ano" : "anos"}`,
            percent(totalChange),
            `${escapeHtml(vehicleLabel)}${vehicle.fipeCode ? " · FIPE " + escapeHtml(vehicle.fipeCode) : ""}`,
            [
              ["Valor hoje", formatMoney(last.price)],
              [`Em ${escapeHtml(first.month)}`, formatMoney(first.price)],
              [lostValue ? "Perdeu" : "Ganhou", formatMoney(Math.abs(last.price - first.price))],
              ["Média por ano", percent(yearlyChange)],
            ],
          );

          const rows = points.map((point, index) => {
            const previous = points[index - 1];
            const change = previous ? point.price / previous.price - 1 : null;
            const changeHtml = change === null ? "—" : `<span class="change ${change < -0.0005 ? "is-down" : change > 0.0005 ? "is-up" : "is-flat"}">${change < 0 ? "▼" : change > 0 ? "▲" : "●"} ${percent(change)}</span>`;
            return `<tr><td>${escapeHtml(point.month)}</td><td>${formatMoney(point.price)}</td><td>${changeHtml}</td></tr>`;
          }).join("");
          fipeDetails.hidden = false;
          fipeDetails.innerHTML = `<h3>Valor na Tabela FIPE ano a ano</h3>
            <div class="fipe-chart"><canvas id="fipe-chart" aria-label="Gráfico do valor na Tabela FIPE nos últimos anos" role="img"></canvas></div>
            <table class="data-table payslip"><thead><tr><th>Mês de referência</th><th>Valor</th><th>Variação no ano</th></tr></thead><tbody>${rows}</tbody></table>
            <p class="notice">Fonte: Tabela FIPE (Fundação Instituto de Pesquisas Econômicas), preço médio de mercado. O valor de venda real depende de quilometragem, conservação e região. Quer projetar os próximos anos? Use a aba "Estimar o futuro".</p>`;
          drawChart(points);
        }

        async function drawChart(points) {
          let ChartLibrary;
          try {
            ChartLibrary = await loadChartJs();
          } catch {
            document.querySelector(".fipe-chart")?.remove(); // sem gráfico, a tabela continua
            return;
          }
          const canvas = document.getElementById("fipe-chart");
          if (!canvas) {
            return;
          }
          chart?.destroy();
          chart = new ChartLibrary(canvas, {
            type: "line",
            data: {
              labels: points.map((point) => point.month.replace(/^(\w{3})\w*\/(\d{4})$/, "$1/$2")),
              datasets: [{ data: points.map((point) => point.price), borderColor: "#0e6b4f", backgroundColor: "rgba(14, 107, 79, 0.12)", fill: true, tension: 0.25, pointRadius: 5, pointBackgroundColor: "#0e6b4f" }],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => formatMoney(context.parsed.y) } } },
              scales: { y: { ticks: { callback: (value) => "R$ " + formatNumber(value / 1000, 0) + " mil" } } },
            },
          });
        }

        typeSelect.addEventListener("change", loadBrands);
        brandSelect.addEventListener("change", loadModels);
        modelFilter.addEventListener("input", filterModels);
        modelSelect.addEventListener("change", loadYears);
        yearSelect.addEventListener("change", loadHistory);

        // Abas: FIPE (histórico real) ou estimativa por categoria
        setupSegmented("depreciation-modes", (mode) => {
          document.getElementById("depreciation-fipe").hidden = mode !== "fipe";
          document.getElementById("depreciation-estimate").hidden = mode !== "estimate";
        });
        resetBelow(1);
        loadBrands();
      },
    },

    "custo-por-km": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="km-month">Km rodados por mês</label><input id="km-month" inputmode="numeric" value="4000"></div>
            <div class="field"><label for="km-earning">Quanto o app paga por km (R$, opcional)</label><input id="km-earning" inputmode="numeric" data-money value="1,90"><span class="field-hint">Ganho do mês ÷ km rodados (inclua os km sem passageiro).</span></div>
          </div>
          <h3>Combustível</h3>
          <div class="field-row">
            <div class="field"><label for="km-fuel-price">Preço do litro (R$)</label><input id="km-fuel-price" inputmode="numeric" data-money value="6,19"></div>
            <div class="field"><label for="km-fuel-efficiency">Consumo (km por litro)</label><input id="km-fuel-efficiency" inputmode="decimal" value="11"></div>
          </div>
          <h3>Desgaste</h3>
          <div class="field-row">
            <div class="field"><label for="km-tires-price">Jogo de 4 pneus (R$)</label><input id="km-tires-price" inputmode="numeric" data-money value="1.800,00"></div>
            <div class="field"><label for="km-tires-life">Pneus duram (km)</label><input id="km-tires-life" inputmode="numeric" value="40000"></div>
            <div class="field"><label for="km-oil-price">Troca de óleo e filtros (R$)</label><input id="km-oil-price" inputmode="numeric" data-money value="250,00"></div>
            <div class="field"><label for="km-oil-interval">Troca a cada (km)</label><input id="km-oil-interval" inputmode="numeric" value="10000"></div>
          </div>
          <h3>Custos fixos</h3>
          <div class="field-row">
            <div class="field"><label for="km-maintenance">Manutenção por mês (R$)</label><input id="km-maintenance" inputmode="numeric" data-money value="200,00"><span class="field-hint">Freios, suspensão, revisões, lavagem.</span></div>
            <div class="field"><label for="km-insurance">Seguro por ano (R$)</label><input id="km-insurance" inputmode="numeric" data-money value="3.600,00"></div>
            <div class="field"><label for="km-ipva">IPVA + licenciamento por ano (R$)</label><input id="km-ipva" inputmode="numeric" data-money value="2.400,00"></div>
            <div class="field"><label for="km-depreciation">Depreciação por ano (R$)</label><input id="km-depreciation" inputmode="numeric" data-money value="6.000,00"></div>
            <div class="field"><label for="km-payment">Parcela ou aluguel por mês (R$)</label><input id="km-payment" inputmode="numeric" data-money value="0"></div>
          </div>
          <span class="field-hint">Não sabe o IPVA ou a depreciação? Use as calculadoras de <a href="/calculadoras/ipva">IPVA</a> e de <a href="/calculadoras/depreciacao-veiculo">depreciação</a>.</span>
        </div>
        <div id="km-result"></div>
        <div class="calculator-body" id="km-table"></div>`,
      setup() {
        const fieldIds = ["km-month", "km-earning", "km-fuel-price", "km-fuel-efficiency", "km-tires-price", "km-tires-life", "km-oil-price", "km-oil-interval", "km-maintenance", "km-insurance", "km-ipva", "km-depreciation", "km-payment"];
        function readNumber(id) {
          return parseNumber(document.getElementById(id).value) || 0;
        }
        function calculate() {
          const kmPerMonth = readNumber("km-month");
          const fuelEfficiency = readNumber("km-fuel-efficiency");
          const resultBox = document.getElementById("km-result");
          const tableBox = document.getElementById("km-table");
          if (!(kmPerMonth > 0) || !(fuelEfficiency > 0)) {
            resultBox.innerHTML = emptyDisplay("Custo por km", "Informe os km por mês e o consumo do carro.");
            tableBox.innerHTML = "";
            return;
          }
          const tiresLife = readNumber("km-tires-life");
          const oilInterval = readNumber("km-oil-interval");
          // Custos que dependem dos km rodados
          const variableCosts = [
            ["Combustível", readNumber("km-fuel-price") / fuelEfficiency],
            ["Pneus", tiresLife > 0 ? readNumber("km-tires-price") / tiresLife : 0],
            ["Óleo e filtros", oilInterval > 0 ? readNumber("km-oil-price") / oilInterval : 0],
          ];
          // Custos que existem mesmo com o carro parado, divididos pelos km do mês
          const fixedCosts = [
            ["Manutenção", readNumber("km-maintenance") / kmPerMonth],
            ["Seguro", readNumber("km-insurance") / 12 / kmPerMonth],
            ["IPVA + licenciamento", readNumber("km-ipva") / 12 / kmPerMonth],
            ["Depreciação", readNumber("km-depreciation") / 12 / kmPerMonth],
            ["Parcela ou aluguel", readNumber("km-payment") / kmPerMonth],
          ];
          const allCosts = [...variableCosts, ...fixedCosts].filter(([, costPerKm]) => costPerKm > 0);
          const costPerKm = allCosts.reduce((sum, [, cost]) => sum + cost, 0);
          const variablePerKm = variableCosts.reduce((sum, [, cost]) => sum + cost, 0);
          const monthlyCost = costPerKm * kmPerMonth;
          const earningPerKm = readNumber("km-earning");

          const gridItems = [
            ["Custo por mês", formatMoney(monthlyCost)],
            ["Só gastos variáveis por km", formatMoney(variablePerKm)],
          ];
          let detail = `rodando ${formatNumber(kmPerMonth)} km por mês`;
          if (earningPerKm > 0) {
            const profitPerKm = earningPerKm - costPerKm;
            gridItems.push(["Lucro por km", formatMoney(profitPerKm)], ["Lucro no mês", formatMoney(profitPerKm * kmPerMonth)]);
            detail = profitPerKm > 0
              ? `com ${formatMoney(earningPerKm)} por km, sobram ${formatMoney(profitPerKm)} por km (${formatNumber(profitPerKm / earningPerKm * 100, 0)}% do que o app paga)`
              : `com ${formatMoney(earningPerKm)} por km você está pagando para trabalhar: cada km dá prejuízo de ${formatMoney(-profitPerKm)}`;
          }
          resultBox.innerHTML = display("Custo real por km", formatMoney(costPerKm), detail, gridItems);
          const rows = allCosts.map(([name, cost]) => `<tr><td>${name}</td><td>${formatMoney(cost)}</td><td>${formatNumber(cost / costPerKm * 100, 1)}%</td></tr>`).join("");
          tableBox.innerHTML = `<h3>Para onde vai o dinheiro de cada km</h3>
            <table class="data-table"><thead><tr><th>Item</th><th>Por km</th><th>Parte do custo</th></tr></thead><tbody>${rows}</tbody></table>
            <p class="notice">Não aceite corridas que paguem menos que o custo por km. Os custos fixos por km caem quando você roda mais. Estimativa: não inclui multas, estacionamento, alimentação, celular nem impostos sobre a renda.</p>`;
        }
        onInputs(fieldIds, calculate);
      },
    },

    "custo-de-viagem": {
      html: `        <form class="calculator-body" id="trip-form" novalidate>
          <div class="field-row">
            <div class="field"><label for="trip-origin">Saindo de</label><input id="trip-origin" autocomplete="street-address" placeholder="Ex.: Av. Paulista, São Paulo, SP" maxlength="150" value="São Paulo, SP"></div>
            <div class="field"><label for="trip-destination">Indo para</label><input id="trip-destination" autocomplete="off" placeholder="Ex.: Santos, SP" maxlength="150" value="Santos, SP"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="trip-fuel-price">Preço do litro (R$)</label><input id="trip-fuel-price" inputmode="numeric" data-money value="6,19"></div>
            <div class="field"><label for="trip-efficiency">Consumo (km por litro)</label><input id="trip-efficiency" inputmode="decimal" value="12"></div>
            <div class="field"><label for="trip-people">Dividir entre (pessoas)</label><input id="trip-people" type="number" min="1" max="20" value="1"></div>
          </div>
          <label class="check"><input type="checkbox" id="trip-round"> Ida e volta</label>
          <button type="submit" class="action-button" id="trip-submit">Calcular viagem</button>
          <span class="field-hint">Rota e mapa: Inclua cidade e estado para achar o endereço certo.</span>
        </form>
        <div id="trip-result"></div>
        <div class="trip-map" id="trip-map" hidden></div>
        <div class="calculator-body" id="trip-tolls" hidden></div>`,
      setup() {
        // Leaflet (mapa) só carrega nesta página, do cdnjs, com verificação de integridade (SRI)
        const LEAFLET = {
          css: ["https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css", "sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw=="],
          js: ["https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js", "sha512-puJW3E/qXDqYp9IfhAI54BJEaWIfloJ7JWs7OeD5i6ruC9JZL1gERT1wjtwXFlh7CjE7ZJ+/vcRZRkIYIb6p4g=="],
        };
        let leafletPromise = null;
        function loadLeaflet() {
          if (!leafletPromise) {
            leafletPromise = new Promise((resolve, reject) => {
              const style = document.createElement("link");
              Object.assign(style, { rel: "stylesheet", href: LEAFLET.css[0], integrity: LEAFLET.css[1], crossOrigin: "anonymous" });
              document.head.appendChild(style);
              const script = document.createElement("script");
              Object.assign(script, { src: LEAFLET.js[0], integrity: LEAFLET.js[1], crossOrigin: "anonymous" });
              script.onload = () => resolve(window.L);
              script.onerror = reject;
              document.head.appendChild(script);
            });
          }
          return leafletPromise;
        }

        const form = document.getElementById("trip-form");
        const resultBox = document.getElementById("trip-result");
        const mapBox = document.getElementById("trip-map");
        const tollsBox = document.getElementById("trip-tolls");
        const submitButton = document.getElementById("trip-submit");
        let trip = null;      // última rota calculada (vem do servidor)
        let tolls = null;     // praças de pedágio (chegam depois da rota); null = ainda buscando ou indisponível
        let tollsStatus = ""; // "loading" | "ready" | "unavailable"
        let map = null;
        let mapLayers = [];

        function formatDuration(totalSeconds) {
          const totalMinutes = Math.round(totalSeconds / 60);
          const hours = Math.floor(totalMinutes / 60);
          const minutes = totalMinutes % 60;
          return hours > 0 ? `${hours} h ${String(minutes).padStart(2, "0")} min` : `${minutes} min`;
        }

        // Custos: recalculados na hora quando muda preço, consumo, ida e volta ou pessoas (sem nova consulta)
        function showCosts() {
          if (!trip) {
            return;
          }
          const fuelPrice = parseNumber(document.getElementById("trip-fuel-price").value);
          const efficiency = parseNumber(document.getElementById("trip-efficiency").value);
          const people = Math.max(1, parseInt(document.getElementById("trip-people").value, 10) || 1);
          const trips = document.getElementById("trip-round").checked ? 2 : 1;
          const kilometers = (trip.distanceMeters / 1000) * trips;
          const routeLabel = `${escapeHtml(trip.origin.label)} → ${escapeHtml(trip.destination.label)}${trips === 2 ? " (ida e volta)" : ""}`;
          if (!(fuelPrice > 0) || !(efficiency > 0)) {
            resultBox.innerHTML = emptyDisplay("Custo da viagem", "Informe o preço do litro e o consumo do carro.");
            return;
          }
          const liters = kilometers / efficiency;
          const fuelCost = liters * fuelPrice;
          const pricedTolls = (tolls || []).filter((toll) => toll.price !== null);
          const tollCost = pricedTolls.reduce((sum, toll) => sum + toll.price, 0) * trips;
          const total = fuelCost + tollCost;
          let tollText = "buscando…";
          if (tollsStatus === "unavailable") {
            tollText = "indisponível";
          } else if (tollsStatus === "ready") {
            tollText = tolls.length === 0 ? "nenhum" : formatMoney(tollCost) + (pricedTolls.length < tolls.length ? "*" : "");
          }
          const gridItems = [
            ["Distância", `${formatNumber(kilometers, 1)} km`],
            ["Tempo ao volante", formatDuration(trip.durationSeconds * trips)],
            ["Combustível", `${formatNumber(liters, 1)} L · ${formatMoney(fuelCost)}`],
            ["Pedágios", tollText],
          ];
          if (people > 1) {
            gridItems.push(["Por pessoa", formatMoney(total / people)]);
          }
          resultBox.innerHTML = display(tollsStatus === "ready" && tollCost > 0 ? "Custo da viagem (combustível + pedágios)" : "Custo da viagem (combustível)", formatMoney(total), routeLabel, gridItems);
        }

        async function drawMap() {
          mapBox.hidden = false;
          let leaflet;
          try {
            leaflet = await loadLeaflet();
          } catch {
            mapBox.innerHTML = '<p class="trip-map-error">Não foi possível carregar o mapa agora. Os valores acima continuam valendo.</p>';
            return;
          }
          if (!map) {
            map = leaflet.map(mapBox, { scrollWheelZoom: false });
            leaflet.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
              maxZoom: 19,
              attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
            }).addTo(map);
          }
          mapLayers.forEach((layer) => layer.remove());
          const line = leaflet.polyline(trip.path, { color: "#0e6b4f", weight: 5, opacity: 0.85 });
          const start = leaflet.circleMarker(trip.path[0], { radius: 8, color: "#ffffff", weight: 3, fillColor: "#0e6b4f", fillOpacity: 1 }).bindPopup(`<b>Partida</b><br>${escapeHtml(trip.origin.label)}`);
          const end = leaflet.circleMarker(trip.path[trip.path.length - 1], { radius: 8, color: "#ffffff", weight: 3, fillColor: "#b3261e", fillOpacity: 1 }).bindPopup(`<b>Destino</b><br>${escapeHtml(trip.destination.label)}`);
          mapLayers = [line, start, end];
          mapLayers.forEach((layer) => layer.addTo(map));
          map.invalidateSize();
          map.fitBounds(line.getBounds(), { padding: [24, 24] });
        }

        function drawTolls() {
          if (!map || !window.L) {
            return;
          }
          (tolls || []).forEach((toll, index) => {
            const marker = window.L.circleMarker([toll.lat, toll.lon], { radius: 7, color: "#1d1600", weight: 2, fillColor: "#f0b429", fillOpacity: 1 })
              .bindPopup(`<b>Pedágio ${index + 1}</b>${toll.name ? "<br>" + escapeHtml(toll.name) : ""}<br>${toll.price !== null ? formatMoney(toll.price) + " (carro)" : "valor não informado"}`);
            marker.addTo(map);
            mapLayers.push(marker);
          });
        }

        function showTollList() {
          tollsBox.hidden = false;
          if (tollsStatus === "loading") {
            tollsBox.innerHTML = "<p class=\"field-hint\">Procurando as praças de pedágio no caminho…</p>";
            return;
          }
          if (tollsStatus === "unavailable") {
            tollsBox.innerHTML = "<p class=\"notice\">Não conseguimos buscar os pedágios agora. Tente de novo em alguns minutos.</p>";
            return;
          }
          if (tolls.length === 0) {
            tollsBox.innerHTML = "<p class=\"notice\">Nenhuma praça de pedágio encontrada no caminho.</p>";
            return;
          }
          const rows = tolls.map((toll, index) => `<tr><td>${index + 1}. ${escapeHtml(toll.name || "Praça de pedágio")}${toll.checkedAt ? `<small class="trip-checked">valor conferido em ${escapeHtml(formatDate(toll.checkedAt.slice(0, 10).padEnd(10, "-01").slice(0, 10)))}</small>` : ""}</td><td>${toll.price !== null ? formatMoney(toll.price) : "—"}</td></tr>`).join("");
          const missing = tolls.filter((toll) => toll.price === null).length;
          tollsBox.innerHTML = `<h3>${tolls.length} ${tolls.length === 1 ? "praça de pedágio" : "praças de pedágio"} no caminho (ida)</h3>
            <table class="data-table payslip"><thead><tr><th>Praça</th><th>Carro</th></tr></thead><tbody>${rows}</tbody></table>
            <p class="notice">Valores aproximados para carro de passeio. Valores podem estar desatualizados, e algumas praças cobram só num sentido. Confira no site da concessionária ou da ANTT/agência do estado.${missing > 0 ? ` * ${missing} ${missing === 1 ? "praça está" : "praças estão"} sem valor informado e não ${missing === 1 ? "entra" : "entram"} na soma.` : ""}</p>`;
        }

        async function fetchJson(url) {
          const response = await fetch(url, { headers: { Accept: "application/json" }, credentials: "same-origin" });
          const data = await response.json().catch(() => ({}));
          if (!response.ok) {
            throw new Error(data.error || "Não foi possível calcular a rota agora.");
          }
          return data;
        }

        async function calculateTrip(submitEvent) {
          submitEvent?.preventDefault();
          const origin = document.getElementById("trip-origin").value.trim();
          const destination = document.getElementById("trip-destination").value.trim();
          if (origin.length < 3 || destination.length < 3) {
            resultBox.innerHTML = emptyDisplay("Custo da viagem", "Preencha de onde sai e para onde vai.");
            return;
          }
          submitButton.disabled = true;
          submitButton.textContent = "Calculando rota…";
          window.Vibe2000?.registerToolUse("custo-de-viagem");
          try {
            trip = await fetchJson(`/api/viagem/rota?${new URLSearchParams({ origem: origin, destino: destination })}`);
          } catch (error) {
            trip = null;
            resultBox.innerHTML = emptyDisplay("Custo da viagem", escapeHtml(error.message));
            mapBox.hidden = true;
            tollsBox.hidden = true;
            return;
          } finally {
            submitButton.disabled = false;
            submitButton.textContent = "Calcular viagem";
          }
          tolls = null;
          tollsStatus = "loading";
          showCosts();
          showTollList();
          await drawMap();
          // Pedágios: em outra consulta, para o mapa e os valores aparecerem antes
          try {
            const tollData = await fetchJson(`/api/viagem/pedagios?${new URLSearchParams({ rota: trip.routeId })}`);
            tolls = tollData.tolls;
            tollsStatus = tolls === null ? "unavailable" : "ready";
          } catch {
            tollsStatus = "unavailable";
          }
          showCosts();
          showTollList();
          drawTolls();
        }

        form.addEventListener("submit", calculateTrip);
        ["trip-fuel-price", "trip-efficiency", "trip-people", "trip-round"].forEach((id) => document.getElementById(id).addEventListener("input", showCosts));
        resultBox.innerHTML = emptyDisplay("Custo da viagem", "Preencha a partida e o destino e toque em Calcular viagem.");
      },
    },

    "salario-hora": {
      html: `        <div class="calculator-body">
          <div class="segmented" id="hourly-modes" role="group" aria-label="Tipo de cálculo">
            <button type="button" data-value="monthly" aria-pressed="true">Salário → valor da hora</button>
            <button type="button" data-value="hourly" aria-pressed="false">Valor da hora → salário</button>
            <button type="button" data-value="freelancer" aria-pressed="false">Freelancer: quanto cobrar</button>
          </div>
          <div id="hourly-fields-monthly" class="field-row">
            <div class="field"><label for="hourly-salary">Salário mensal (R$)</label><input id="hourly-salary" inputmode="numeric" data-money value="3.500,00"></div>
            <div class="field"><label for="hourly-journey">Jornada mensal (horas)</label><input id="hourly-journey" inputmode="numeric" value="220"><span class="field-hint">44h por semana = 220h · 40h = 200h · 36h = 180h · 30h = 150h</span></div>
          </div>
          <div id="hourly-fields-hourly" class="field-row" hidden>
            <div class="field"><label for="hourly-rate">Valor da hora (R$)</label><input id="hourly-rate" inputmode="numeric" data-money value="25,00"></div>
            <div class="field"><label for="hourly-weekly">Horas por semana</label><input id="hourly-weekly" inputmode="decimal" value="44"></div>
          </div>
          <div id="hourly-fields-freelancer" hidden>
            <div class="field-row">
              <div class="field"><label for="freelancer-goal">Quanto quer ganhar por mês, livre (R$)</label><input id="freelancer-goal" inputmode="numeric" data-money value="6.000,00"></div>
              <div class="field"><label for="freelancer-costs">Custos do trabalho por mês (R$)</label><input id="freelancer-costs" inputmode="numeric" data-money value="800,00"><span class="field-hint">Computador, internet, softwares, contador, INSS.</span></div>
              <div class="field"><label for="freelancer-tax">Impostos sobre o que fatura (%)</label><input id="freelancer-tax" inputmode="decimal" value="6"><span class="field-hint">Depende do regime (MEI, Simples Nacional...).</span></div>
            </div>
            <div class="field-row">
              <div class="field"><label for="freelancer-hours">Horas cobráveis por semana</label><input id="freelancer-hours" inputmode="decimal" value="30"><span class="field-hint">Só as horas pagas pelo cliente, sem reuniões de venda e tarefas administrativas.</span></div>
              <div class="field"><label for="freelancer-vacation">Semanas de folga por ano</label><input id="freelancer-vacation" inputmode="numeric" value="4"></div>
            </div>
          </div>
        </div>
        <div id="hourly-result"></div>`,
      setup() {
        // Pela CLT, a jornada mensal é a semanal × 5 (média de semanas no mês, com o descanso remunerado)
        const WEEKS_PER_MONTH_CLT = 5;
        let mode = "monthly";
        function calculate() {
          const resultBox = document.getElementById("hourly-result");
          if (mode === "monthly") {
            const salary = parseNumber(document.getElementById("hourly-salary").value);
            const journey = parseNumber(document.getElementById("hourly-journey").value);
            if (!(salary > 0) || !(journey > 0)) {
              resultBox.innerHTML = emptyDisplay("Valor da hora", "Informe o salário e a jornada.");
              return;
            }
            const hourValue = salary / journey;
            resultBox.innerHTML = display("Valor da sua hora", formatMoney(hourValue), `${formatMoney(salary)} ÷ ${formatNumber(journey)} horas`, [
              ["Hora extra 50%", formatMoney(hourValue * 1.5)],
              ["Hora extra 100%", formatMoney(hourValue * 2)],
              ["Por dia (salário ÷ 30)", formatMoney(salary / 30)],
              ["Por ano (com 13º)", formatMoney(salary * 13)],
            ]);
          } else if (mode === "hourly") {
            const hourValue = parseNumber(document.getElementById("hourly-rate").value);
            const weeklyHours = parseNumber(document.getElementById("hourly-weekly").value);
            if (!(hourValue > 0) || !(weeklyHours > 0)) {
              resultBox.innerHTML = emptyDisplay("Salário mensal", "Informe o valor da hora e as horas por semana.");
              return;
            }
            const monthlyHours = weeklyHours * WEEKS_PER_MONTH_CLT;
            resultBox.innerHTML = display("Salário mensal", formatMoney(hourValue * monthlyHours), `${formatMoney(hourValue)} × ${formatNumber(monthlyHours)} horas por mês`, [
              ["Por semana", formatMoney(hourValue * weeklyHours)],
              ["Por ano (com 13º)", formatMoney(hourValue * monthlyHours * 13)],
            ]);
          } else {
            const goal = parseNumber(document.getElementById("freelancer-goal").value);
            const costs = parseNumber(document.getElementById("freelancer-costs").value) || 0;
            const taxPercent = parseNumber(document.getElementById("freelancer-tax").value) || 0;
            const weeklyHours = parseNumber(document.getElementById("freelancer-hours").value);
            const vacationWeeks = parseNumber(document.getElementById("freelancer-vacation").value) || 0;
            const workingWeeks = 52 - vacationWeeks;
            if (!(goal > 0) || !(weeklyHours > 0) || workingWeeks <= 0 || taxPercent < 0 || taxPercent >= 100) {
              resultBox.innerHTML = emptyDisplay("Valor da hora", "Preencha a meta, as horas por semana e impostos abaixo de 100%.");
              return;
            }
            // O ano todo precisa pagar meta + custos (inclusive nas semanas de folga), só com as semanas trabalhadas
            const billableHoursPerMonth = weeklyHours * workingWeeks / 12;
            const monthlyRevenue = (goal + costs) / (1 - taxPercent / 100);
            const hourValue = monthlyRevenue / billableHoursPerMonth;
            resultBox.innerHTML = display("Cobre pelo menos por hora", formatMoney(hourValue), `para sobrar ${formatMoney(goal)} por mês, com ${formatNumber(billableHoursPerMonth, 1)} horas cobráveis por mês (média do ano)`, [
              ["Faturamento por mês", formatMoney(monthlyRevenue)],
              ["Impostos por mês", formatMoney(monthlyRevenue - goal - costs)],
              ["Diária (8 horas)", formatMoney(hourValue * 8)],
            ]);
          }
        }
        setupSegmented("hourly-modes", (newMode) => {
          mode = newMode;
          ["monthly", "hourly", "freelancer"].forEach((modeName) => {
            document.getElementById(`hourly-fields-${modeName}`).hidden = modeName !== mode;
          });
          calculate();
        });
        onInputs(["hourly-salary", "hourly-journey", "hourly-rate", "hourly-weekly", "freelancer-goal", "freelancer-costs", "freelancer-tax", "freelancer-hours", "freelancer-vacation"], calculate);
      },
    },

    "cpf-cnpj": {
      html: `        <div class="calculator-body">
          <div class="segmented" id="document-modes" role="group" aria-label="O que fazer">
            <button type="button" data-value="validate" aria-pressed="true">Validar</button>
            <button type="button" data-value="generate" aria-pressed="false">Gerar para testes</button>
          </div>
          <div id="document-validate">
            <div class="field"><label for="document-input">CPF ou CNPJ</label><input id="document-input" autocomplete="off" spellcheck="false" placeholder="000.000.000-00 ou 00.000.000/0000-00"><span class="field-hint">Aceita com ou sem pontuação, e também o novo CNPJ com letras.</span></div>
          </div>
          <div id="document-generate" hidden>
            <div class="field-row">
              <div class="field"><label for="document-type">Tipo</label><select id="document-type"><option value="cpf">CPF</option><option value="cnpj">CNPJ numérico</option><option value="cnpj-alpha">CNPJ alfanumérico (novo)</option></select></div>
              <div class="field"><label for="document-amount">Quantidade</label><select id="document-amount"><option value="1">1</option><option value="5">5</option><option value="10">10</option></select></div>
              <div class="field"><label for="document-format">Formato</label><select id="document-format"><option value="masked">Com pontuação</option><option value="plain">Só os caracteres</option></select></div>
            </div>
            <button class="action-button" type="button" id="document-generate-button">Gerar novos</button>
          </div>
        </div>
        <div id="document-result"></div>
        <div class="calculator-body"><p class="notice">Os números gerados são fictícios, feitos só para testar sistemas: passam na conta dos dígitos verificadores, mas não pertencem a pessoas ou empresas. A validação confere apenas a matemática, não se o documento existe na Receita Federal. Usar documento falso para enganar alguém é crime.</p></div>`,
      setup() {
        // Dígitos verificadores (módulo 11). No CNPJ alfanumérico (Receita Federal, a partir de julho de 2026)
        // cada caractere vale o código ASCII menos 48: "0"–"9" = 0–9, "A" = 17, "B" = 18...
        function characterValue(character) {
          return character.charCodeAt(0) - 48;
        }
        function checkDigit(characters, weights) {
          const sum = characters.split("").reduce((total, character, index) => total + characterValue(character) * weights[index], 0);
          const remainder = sum % 11;
          return remainder < 2 ? 0 : 11 - remainder;
        }
        const CPF_WEIGHTS_1 = [10, 9, 8, 7, 6, 5, 4, 3, 2];
        const CPF_WEIGHTS_2 = [11, 10, 9, 8, 7, 6, 5, 4, 3, 2];
        const CNPJ_WEIGHTS_1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        const CNPJ_WEIGHTS_2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        function completeCpf(firstNine) {
          const first = checkDigit(firstNine, CPF_WEIGHTS_1);
          return firstNine + first + checkDigit(firstNine + first, CPF_WEIGHTS_2);
        }
        function completeCnpj(firstTwelve) {
          const first = checkDigit(firstTwelve, CNPJ_WEIGHTS_1);
          return firstTwelve + first + checkDigit(firstTwelve + first, CNPJ_WEIGHTS_2);
        }
        function isAllSameCharacter(text) {
          return /^(.)\1+$/.test(text);
        }
        function formatCpf(cpf) {
          return cpf.replace(/^(.{3})(.{3})(.{3})(.{2})$/, "$1.$2.$3-$4");
        }
        function formatCnpj(cnpj) {
          return cnpj.replace(/^(.{2})(.{3})(.{3})(.{4})(.{2})$/, "$1.$2.$3/$4-$5");
        }

        function validate(text) {
          const clean = text.toUpperCase().replace(/[^0-9A-Z]/g, "");
          if (clean === "") {
            return null;
          }
          if (/^\d{11}$/.test(clean)) {
            const isValid = !isAllSameCharacter(clean) && completeCpf(clean.slice(0, 9)) === clean;
            return { type: "CPF", formatted: formatCpf(clean), isValid };
          }
          if (/^[0-9A-Z]{12}\d{2}$/.test(clean)) {
            const isValid = !isAllSameCharacter(clean) && completeCnpj(clean.slice(0, 12)) === clean;
            return { type: /[A-Z]/.test(clean) ? "CNPJ alfanumérico" : "CNPJ", formatted: formatCnpj(clean), isValid };
          }
          return { type: "", formatted: text, isValid: false, message: "Não tem o tamanho de um CPF (11 números) nem de um CNPJ (14 caracteres)." };
        }

        // crypto.getRandomValues: sorteio melhor que Math.random
        function randomCharacters(length, alphabet) {
          const randomValues = crypto.getRandomValues(new Uint32Array(length));
          return Array.from(randomValues, (randomValue) => alphabet[randomValue % alphabet.length]).join("");
        }
        const DIGITS = "0123456789";
        const DIGITS_AND_LETTERS = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        function generateOne(type) {
          let generated;
          do {
            if (type === "cpf") {
              generated = completeCpf(randomCharacters(9, DIGITS));
            } else if (type === "cnpj") {
              // Matriz: "0001" depois dos 8 primeiros números
              generated = completeCnpj(randomCharacters(8, DIGITS) + "0001");
            } else {
              generated = completeCnpj(randomCharacters(8, DIGITS_AND_LETTERS) + randomCharacters(4, DIGITS_AND_LETTERS));
            }
          } while (isAllSameCharacter(generated));
          return generated;
        }

        let mode = "validate";
        const resultBox = document.getElementById("document-result");
        function showValidation() {
          const result = validate(document.getElementById("document-input").value);
          if (result === null) {
            resultBox.innerHTML = emptyDisplay("Resultado", "Digite ou cole um CPF ou CNPJ.");
            return;
          }
          const label = result.isValid ? `${result.type} válido` : result.type ? `${result.type} inválido` : "Número inválido";
          const detail = result.message || (result.isValid ? "Os dígitos verificadores conferem." : "Os dígitos verificadores não conferem (ou todos os números são iguais).");
          resultBox.innerHTML = display(label, escapeHtml(result.formatted), detail);
        }
        function showGenerated() {
          const type = document.getElementById("document-type").value;
          const amount = Number(document.getElementById("document-amount").value);
          const isMasked = document.getElementById("document-format").value === "masked";
          const documents = Array.from({ length: amount }, () => {
            const generated = generateOne(type);
            if (!isMasked) {
              return generated;
            }
            return type === "cpf" ? formatCpf(generated) : formatCnpj(generated);
          });
          const typeLabel = { cpf: "CPF", cnpj: "CNPJ", "cnpj-alpha": "CNPJ alfanumérico" }[type];
          resultBox.innerHTML = `<div class="display"><span class="display-label">${amount > 1 ? `${amount} números de ${typeLabel}` : typeLabel} para testes</span><span class="display-text" id="document-output">${documents.join("\n")}</span><button class="copy-button" type="button" data-copy-target="document-output">copiar</button></div>`;
        }
        function calculate() {
          if (mode === "validate") {
            showValidation();
          } else {
            showGenerated();
          }
        }
        setupSegmented("document-modes", (newMode) => {
          mode = newMode;
          document.getElementById("document-validate").hidden = mode !== "validate";
          document.getElementById("document-generate").hidden = mode !== "generate";
          calculate();
        });
        document.getElementById("document-generate-button").addEventListener("click", showGenerated);
        onInputs(["document-input", "document-type", "document-amount", "document-format"], calculate);
      },
    },

    "timestamp": {
      html: `        <div class="calculator-body">
          <div class="segmented" id="timestamp-modes" role="group" aria-label="Direção">
            <button type="button" data-value="toDate" aria-pressed="true">Timestamp → data</button>
            <button type="button" data-value="toTimestamp" aria-pressed="false">Data → timestamp</button>
          </div>
          <div class="field-row">
            <div class="field"><label for="timestamp-zone">Fuso horário</label><select id="timestamp-zone"></select></div>
          </div>
          <div id="timestamp-to-date" class="field-row">
            <div class="field"><label for="timestamp-input">Timestamp Unix</label><input id="timestamp-input" inputmode="numeric" autocomplete="off"><span class="field-hint">Em segundos (10 dígitos) ou milissegundos (13 dígitos): detectado sozinho.</span></div>
          </div>
          <div id="timestamp-to-number" class="field-row" hidden>
            <div class="field"><label for="timestamp-date">Data</label><input id="timestamp-date" type="date"></div>
            <div class="field"><label for="timestamp-time">Hora</label><input id="timestamp-time" type="time" step="1" value="12:00:00"></div>
          </div>
          <button class="secondary-button" type="button" id="timestamp-now">Usar o momento atual</button>
        </div>
        <div id="timestamp-result"></div>`,
      setup() {
        const TIME_ZONES = [
          ["America/Sao_Paulo", "Brasília (BRT, UTC−3)"],
          ["America/Manaus", "Manaus e Amazonas (UTC−4)"],
          ["America/Cuiaba", "Mato Grosso (UTC−4)"],
          ["America/Rio_Branco", "Acre (UTC−5)"],
          ["America/Noronha", "Fernando de Noronha (UTC−2)"],
          ["UTC", "UTC (horário universal)"],
          ["America/New_York", "Nova York"],
          ["America/Los_Angeles", "Los Angeles"],
          ["Europe/Lisbon", "Lisboa"],
          ["Europe/London", "Londres"],
          ["Europe/Berlin", "Berlim / Paris / Madri"],
          ["Asia/Tokyo", "Tóquio"],
        ];
        const zoneSelect = document.getElementById("timestamp-zone");
        const browserZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const zoneOptions = TIME_ZONES.some(([zone]) => zone === browserZone) ? TIME_ZONES : [[browserZone, `${browserZone} (seu navegador)`], ...TIME_ZONES];
        zoneSelect.innerHTML = zoneOptions.map(([zone, label]) => `<option value="${zone}"${zone === "America/Sao_Paulo" ? " selected" : ""}>${label}</option>`).join("");

        // Partes da data (ano, mês, dia, hora...) de um instante, vistas em um fuso
        function partsInZone(date, timeZone) {
          const parts = new Intl.DateTimeFormat("en-US", { timeZone, hourCycle: "h23", year: "numeric", month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", second: "2-digit" }).formatToParts(date);
          const values = {};
          parts.forEach((part) => { values[part.type] = Number(part.value); });
          return values;
        }
        // Diferença, em milissegundos, entre o relógio do fuso e o UTC naquele instante
        function zoneOffset(date, timeZone) {
          const parts = partsInZone(date, timeZone);
          const asUtc = Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute, parts.second);
          return asUtc - Math.floor(date.getTime() / 1000) * 1000;
        }
        function formatOffset(offsetMilliseconds) {
          const totalMinutes = Math.round(offsetMilliseconds / 60000);
          const sign = totalMinutes < 0 ? "−" : "+";
          const hours = String(Math.floor(Math.abs(totalMinutes) / 60)).padStart(2, "0");
          const minutes = String(Math.abs(totalMinutes) % 60).padStart(2, "0");
          return `UTC${sign}${hours}:${minutes}`;
        }
        function formatInZone(date, timeZone) {
          return new Intl.DateTimeFormat("pt-BR", { timeZone, day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit", second: "2-digit" }).format(date).replace(",", "");
        }
        function relativeText(date) {
          const differenceSeconds = Math.round((date.getTime() - Date.now()) / 1000);
          const units = [["year", 31536000], ["month", 2592000], ["day", 86400], ["hour", 3600], ["minute", 60], ["second", 1]];
          const [unit, size] = units.find(([, unitSeconds]) => Math.abs(differenceSeconds) >= unitSeconds) || ["second", 1];
          return new Intl.RelativeTimeFormat("pt-BR", { numeric: "auto" }).format(Math.round(differenceSeconds / size), unit);
        }

        let mode = "toDate";
        const resultBox = document.getElementById("timestamp-result");
        function calculate() {
          const timeZone = zoneSelect.value;
          if (mode === "toDate") {
            const text = document.getElementById("timestamp-input").value.trim();
            if (!/^-?\d+$/.test(text)) {
              resultBox.innerHTML = emptyDisplay("Data", "Digite um timestamp só com números.");
              return;
            }
            const number = Number(text);
            const isMilliseconds = text.replace("-", "").length > 11;
            const date = new Date(isMilliseconds ? number : number * 1000);
            if (Number.isNaN(date.getTime())) {
              resultBox.innerHTML = emptyDisplay("Data", "Número fora do intervalo de datas.");
              return;
            }
            resultBox.innerHTML = display("Data e hora", formatInZone(date, timeZone), `${formatOffset(zoneOffset(date, timeZone))} · ${relativeText(date)} · lido em ${isMilliseconds ? "milissegundos" : "segundos"}`, [
              ["UTC", formatInZone(date, "UTC")],
              ["ISO 8601", date.toISOString()],
              ["Dia da semana", WEEKDAYS[new Date(date.getTime() + zoneOffset(date, timeZone)).getUTCDay()]],
            ]);
          } else {
            const dateText = document.getElementById("timestamp-date").value;
            const timeText = document.getElementById("timestamp-time").value || "00:00:00";
            if (!dateText) {
              resultBox.innerHTML = emptyDisplay("Timestamp", "Escolha a data.");
              return;
            }
            const [year, month, day] = dateText.split("-").map(Number);
            const [hour, minute, second = 0] = timeText.split(":").map(Number);
            // Começa como se fosse UTC e corrige pelo fuso (duas vezes, por causa do horário de verão)
            const wallClockAsUtc = Date.UTC(year, month - 1, day, hour, minute, second);
            let instant = wallClockAsUtc - zoneOffset(new Date(wallClockAsUtc), timeZone);
            instant = wallClockAsUtc - zoneOffset(new Date(instant), timeZone);
            const date = new Date(instant);
            resultBox.innerHTML = display("Timestamp Unix (segundos)", String(Math.floor(instant / 1000)), `${formatInZone(date, timeZone)} ${formatOffset(zoneOffset(date, timeZone))} · ${relativeText(date)}`, [
              ["Milissegundos", String(instant)],
              ["ISO 8601 (UTC)", date.toISOString()],
            ]);
          }
        }
        function useNow() {
          const now = new Date();
          document.getElementById("timestamp-input").value = String(Math.floor(now.getTime() / 1000));
          const parts = partsInZone(now, zoneSelect.value);
          const pad = (value) => String(value).padStart(2, "0");
          document.getElementById("timestamp-date").value = `${parts.year}-${pad(parts.month)}-${pad(parts.day)}`;
          document.getElementById("timestamp-time").value = `${pad(parts.hour)}:${pad(parts.minute)}:${pad(parts.second)}`;
          calculate();
        }
        setupSegmented("timestamp-modes", (newMode) => {
          mode = newMode;
          document.getElementById("timestamp-to-date").hidden = mode !== "toDate";
          document.getElementById("timestamp-to-number").hidden = mode !== "toTimestamp";
          calculate();
        });
        document.getElementById("timestamp-now").addEventListener("click", useNow);
        zoneSelect.addEventListener("change", calculate);
        useNow();
        onInputs(["timestamp-input", "timestamp-date", "timestamp-time"], calculate);
      },
    },

    "base64": {
      html: `        <div class="calculator-body">
          <div class="segmented" id="base64-modes" role="group" aria-label="O que fazer">
            <button type="button" data-value="encode" aria-pressed="true">Codificar texto</button>
            <button type="button" data-value="decode" aria-pressed="false">Decodificar</button>
            <button type="button" data-value="file" aria-pressed="false">Imagem ou arquivo → Base64</button>
          </div>
          <div id="base64-text-fields">
            <div class="field"><label for="base64-input" id="base64-input-label">Texto</label><textarea id="base64-input" spellcheck="false">Olá, Vibe2000! Acentos também funcionam: ção, ü, €.</textarea></div>
            <label class="check"><input type="checkbox" id="base64-url-safe"> Base64URL (troca + e / por - e _, sem "=" no fim; usado em tokens JWT e URLs)</label>
          </div>
          <div id="base64-file-fields" hidden>
            <div class="field"><label for="base64-file">Escolha o arquivo (até 5 MB)</label><input id="base64-file" type="file"><span class="field-hint">O arquivo é lido no seu navegador e não é enviado para lugar nenhum.</span></div>
          </div>
        </div>
        <div id="base64-result"></div>`,
      setup() {
        const MAX_FILE_BYTES = 5 * 1024 * 1024;
        let mode = "encode";
        const resultBox = document.getElementById("base64-result");

        // btoa/atob só entendem bytes; o TextEncoder converte o texto para bytes UTF-8 (acentos, emojis)
        function bytesToBase64(bytes) {
          let binary = "";
          for (let index = 0; index < bytes.length; index += 0x8000) {
            binary += String.fromCharCode(...bytes.subarray(index, index + 0x8000));
          }
          return btoa(binary);
        }
        function base64ToBytes(base64Text) {
          let normalized = base64Text.replace(/^data:[^,]*,/, "").replace(/\s/g, "").replace(/-/g, "+").replace(/_/g, "/");
          normalized += "=".repeat((4 - (normalized.length % 4)) % 4);
          const binary = atob(normalized);
          return Uint8Array.from(binary, (character) => character.charCodeAt(0));
        }
        function toUrlSafe(base64Text) {
          return base64Text.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
        }
        // Reconhece imagens comuns pelos primeiros bytes, para mostrar a prévia
        function detectImageType(bytes) {
          if (bytes[0] === 0x89 && bytes[1] === 0x50) return "image/png";
          if (bytes[0] === 0xff && bytes[1] === 0xd8) return "image/jpeg";
          if (bytes[0] === 0x47 && bytes[1] === 0x49) return "image/gif";
          if (bytes[0] === 0x52 && bytes[1] === 0x49 && bytes[8] === 0x57) return "image/webp";
          return "";
        }
        function resultWithCopy(label, text, detail) {
          return `<div class="display"><span class="display-label">${label}</span><span class="display-text base64-output" id="base64-output">${escapeHtml(text)}</span>${detail ? `<span class="display-detail">${detail}</span>` : ""}<button class="copy-button" type="button" data-copy-target="base64-output">copiar</button></div>`;
        }

        function calculate() {
          if (mode === "file") {
            return;
          }
          const input = document.getElementById("base64-input").value;
          if (input === "") {
            resultBox.innerHTML = emptyDisplay("Resultado", mode === "encode" ? "Digite um texto." : "Cole um texto em Base64.");
            return;
          }
          if (mode === "encode") {
            let encoded = bytesToBase64(new TextEncoder().encode(input));
            if (document.getElementById("base64-url-safe").checked) {
              encoded = toUrlSafe(encoded);
            }
            resultBox.innerHTML = resultWithCopy("Base64", encoded, `${formatNumber(encoded.length)} caracteres`);
            return;
          }
          let bytes;
          try {
            bytes = base64ToBytes(input);
          } catch {
            resultBox.innerHTML = emptyDisplay("Resultado", "Isso não é um Base64 válido. Confira se copiou o texto inteiro.");
            return;
          }
          const imageType = detectImageType(bytes);
          if (imageType) {
            const dataUrl = `data:${imageType};base64,${bytesToBase64(bytes)}`;
            resultBox.innerHTML = `<div class="display"><span class="display-label">Imagem (${imageType})</span><img class="base64-preview" src="${dataUrl}" alt="Imagem decodificada"><span class="display-detail">${formatNumber(bytes.length)} bytes</span></div>`;
            return;
          }
          try {
            const decoded = new TextDecoder("utf-8", { fatal: true }).decode(bytes);
            resultBox.innerHTML = resultWithCopy("Texto decodificado", decoded, `${formatNumber(bytes.length)} bytes`);
          } catch {
            resultBox.innerHTML = emptyDisplay(`Arquivo binário (${formatNumber(bytes.length)} bytes)`, "O conteúdo não é texto nem uma imagem conhecida (PNG, JPG, GIF, WebP).");
          }
        }

        document.getElementById("base64-file").addEventListener("change", (changeEvent) => {
          const file = changeEvent.target.files[0];
          if (!file) {
            return;
          }
          if (file.size > MAX_FILE_BYTES) {
            resultBox.innerHTML = emptyDisplay("Arquivo grande demais", "Escolha um arquivo de até 5 MB.");
            return;
          }
          const reader = new FileReader();
          reader.onload = () => {
            const dataUrl = String(reader.result);
            const pureBase64 = dataUrl.slice(dataUrl.indexOf(",") + 1);
            const preview = file.type.startsWith("image/") && file.type !== "image/svg+xml" ? `<img class="base64-preview" src="${escapeHtml(dataUrl)}" alt="Prévia do arquivo">` : "";
            resultBox.innerHTML = `${preview}${resultWithCopy(`Base64 de ${escapeHtml(file.name)}`, pureBase64, `${formatNumber(file.size)} bytes viraram ${formatNumber(pureBase64.length)} caracteres (+33%)`)}
              <div class="display"><span class="display-label">Data URL (para usar direto em HTML/CSS)</span><span class="display-text base64-output" id="base64-data-url">${escapeHtml(dataUrl)}</span><button class="copy-button" type="button" data-copy-target="base64-data-url">copiar</button></div>`;
          };
          reader.readAsDataURL(file);
        });

        setupSegmented("base64-modes", (newMode) => {
          mode = newMode;
          document.getElementById("base64-text-fields").hidden = mode === "file";
          document.getElementById("base64-file-fields").hidden = mode !== "file";
          document.getElementById("base64-input-label").textContent = mode === "decode" ? "Texto em Base64 (ou data URL)" : "Texto";
          if (mode === "decode") {
            const currentOutput = document.getElementById("base64-output");
            if (currentOutput) {
              document.getElementById("base64-input").value = currentOutput.textContent;
            }
          }
          resultBox.innerHTML = mode === "file" ? emptyDisplay("Resultado", "Escolha um arquivo.") : "";
          calculate();
        });
        onInputs(["base64-input", "base64-url-safe"], calculate);
      },
    },

    "contador-bytes": {
      html: `        <div class="calculator-body">
          <div class="field"><label for="bytes-input">Texto ou JSON</label><textarea id="bytes-input" spellcheck="false" rows="8">{"nome": "João", "cidade": "São Paulo", "emoji": "🚀"}</textarea></div>
        </div>
        <div id="bytes-result"></div>`,
      setup() {
        function formatSize(bytes, base) {
          const units = base === 1024 ? ["bytes", "KiB", "MiB", "GiB"] : ["bytes", "KB", "MB", "GB"];
          let size = bytes;
          let unitIndex = 0;
          while (size >= base && unitIndex < units.length - 1) {
            size /= base;
            unitIndex++;
          }
          return `${formatNumber(size, unitIndex === 0 ? 0 : 2)} ${units[unitIndex]}`;
        }
        function calculate() {
          const text = document.getElementById("bytes-input").value;
          const resultBox = document.getElementById("bytes-result");
          // Tamanho real enviado pela rede/API: bytes em UTF-8 (acento = 2 bytes, emoji = 4 bytes)
          const utf8Bytes = new TextEncoder().encode(text).length;
          const characters = Array.from(text).length;
          const gridItems = [
            ["Caracteres", formatNumber(characters)],
            ["Em KB (1000)", formatSize(utf8Bytes, 1000)],
            ["Em KiB (1024)", formatSize(utf8Bytes, 1024)],
            ["UTF-16 (JavaScript .length)", `${formatNumber(text.length * 2)} bytes`],
            ["Em Base64", `${formatNumber(Math.ceil(utf8Bytes / 3) * 4)} bytes`],
            ["Linhas", formatNumber(text === "" ? 0 : text.split("\n").length)],
          ];
          let detail = utf8Bytes > characters ? `${formatNumber(utf8Bytes - characters)} bytes a mais por causa de acentos, emojis e outros caracteres especiais` : "só caracteres simples (1 byte cada)";
          const trimmed = text.trim();
          if (trimmed.startsWith("{") || trimmed.startsWith("[")) {
            try {
              const minified = JSON.stringify(JSON.parse(trimmed));
              const minifiedBytes = new TextEncoder().encode(minified).length;
              gridItems.push(["JSON minificado", `${formatNumber(minifiedBytes)} bytes`]);
              detail += " · JSON válido";
            } catch (jsonError) {
              detail += ` · JSON inválido: ${escapeHtml(jsonError.message)}`;
            }
          }
          resultBox.innerHTML = display("Tamanho em UTF-8", `${formatNumber(utf8Bytes)} bytes`, detail, gridItems);
        }
        onInputs(["bytes-input"], calculate);
      },
    },

    "cron": {
      html: `        <div class="calculator-body">
          <h3>1. Monte o agendamento</h3>
          <div class="field-row">
            <div class="field"><label for="cron-frequency">Rodar</label><select id="cron-frequency">
              <option value="minutes">a cada N minutos</option>
              <option value="hourly">a cada hora</option>
              <option value="daily" selected>todo dia</option>
              <option value="weekdays">de segunda a sexta</option>
              <option value="weekly">toda semana</option>
              <option value="monthly">todo mês</option>
            </select></div>
            <div class="field" data-cron-for="minutes"><label for="cron-interval">Intervalo (minutos)</label><select id="cron-interval"><option>5</option><option>10</option><option selected>15</option><option>20</option><option>30</option></select></div>
            <div class="field" data-cron-for="weekly"><label for="cron-weekday">Dia da semana</label><select id="cron-weekday"><option value="1">segunda-feira</option><option value="2">terça-feira</option><option value="3">quarta-feira</option><option value="4">quinta-feira</option><option value="5">sexta-feira</option><option value="6">sábado</option><option value="0">domingo</option></select></div>
            <div class="field" data-cron-for="monthly"><label for="cron-monthday">Dia do mês</label><input id="cron-monthday" type="number" min="1" max="31" value="1"></div>
            <div class="field" data-cron-for="daily weekdays weekly monthly"><label for="cron-hour">Hora</label><input id="cron-hour" type="number" min="0" max="23" value="3"></div>
            <div class="field" data-cron-for="hourly daily weekdays weekly monthly"><label for="cron-minute">Minuto</label><input id="cron-minute" type="number" min="0" max="59" value="0"></div>
          </div>
          <h3>2. Ou cole uma expressão para entender</h3>
          <div class="field"><label for="cron-expression">Expressão cron (minuto hora dia mês dia-da-semana)</label><input id="cron-expression" autocomplete="off" spellcheck="false" class="mono-input"></div>
        </div>
        <div id="cron-result"></div>`,
      setup() {
        const FIELDS = [
          { name: "minuto", min: 0, max: 59 },
          { name: "hora", min: 0, max: 23 },
          { name: "dia do mês", min: 1, max: 31 },
          { name: "mês", min: 1, max: 12, names: ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"] },
          { name: "dia da semana", min: 0, max: 7, names: ["SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT"] },
        ];
        const MONTH_NAMES = ["", "janeiro", "fevereiro", "março", "abril", "maio", "junho", "julho", "agosto", "setembro", "outubro", "novembro", "dezembro"];
        const SHORTCUTS = { "@yearly": "0 0 1 1 *", "@annually": "0 0 1 1 *", "@monthly": "0 0 1 * *", "@weekly": "0 0 * * 0", "@daily": "0 0 * * *", "@midnight": "0 0 * * *", "@hourly": "0 * * * *" };
        const expressionInput = document.getElementById("cron-expression");

        // Transforma um campo ("*/15", "1-5", "1,15", "MON-FRI") na lista de valores permitidos
        function parseField(text, field) {
          let normalized = text.toUpperCase();
          (field.names || []).forEach((name, index) => {
            normalized = normalized.replace(new RegExp(name, "g"), String(index + (field.min === 1 ? 1 : 0)));
          });
          const values = new Set();
          for (const part of normalized.split(",")) {
            const match = part.match(/^(\*|\d+)(?:-(\d+))?(?:\/(\d+))?$/);
            if (!match) {
              throw new Error(`"${text}" não é válido no campo ${field.name}`);
            }
            const start = match[1] === "*" ? field.min : Number(match[1]);
            const end = match[1] === "*" ? field.max : match[2] !== undefined ? Number(match[2]) : match[3] ? field.max : start;
            const step = match[3] ? Number(match[3]) : 1;
            if (start < field.min || end > field.max || start > end || step < 1) {
              throw new Error(`"${text}" está fora do intervalo do campo ${field.name} (${field.min} a ${field.max})`);
            }
            for (let value = start; value <= end; value += step) {
              values.add(field.name === "dia da semana" && value === 7 ? 0 : value);
            }
          }
          return values;
        }
        function parseExpression(expression) {
          const trimmed = expression.trim().toLowerCase();
          const parts = (SHORTCUTS[trimmed] || expression.trim()).split(/\s+/);
          if (parts.length !== 5) {
            throw new Error("A expressão precisa ter 5 campos separados por espaço: minuto hora dia mês dia-da-semana.");
          }
          return { parts, sets: parts.map((part, index) => parseField(part, FIELDS[index])) };
        }

        function listText(values, formatter = String) {
          const items = [...values].sort((first, second) => first - second).map(formatter);
          return items.length === 1 ? items[0] : `${items.slice(0, -1).join(", ")} e ${items[items.length - 1]}`;
        }
        function pad(value) {
          return String(value).padStart(2, "0");
        }
        // Explicação em português, campo por campo
        function describe({ parts, sets }) {
          const [minuteText, hourText, dayText, monthText, weekdayText] = parts;
          let timeText;
          if (minuteText === "*" && hourText === "*") {
            timeText = "a cada minuto";
          } else if (/^\*\/\d+$/.test(minuteText) && hourText === "*") {
            timeText = `a cada ${minuteText.slice(2)} minutos`;
          } else if (hourText === "*") {
            timeText = `a cada hora, no${sets[0].size > 1 ? "s minutos" : " minuto"} ${listText(sets[0])}`;
          } else if (sets[0].size * sets[1].size <= 6) {
            const times = [];
            [...sets[1]].sort((a, b) => a - b).forEach((hour) => [...sets[0]].sort((a, b) => a - b).forEach((minute) => times.push(`${pad(hour)}:${pad(minute)}`)));
            timeText = `às ${listText(times.map((_, index) => index), (index) => times[index])}`;
          } else {
            timeText = `no${sets[0].size > 1 ? "s minutos" : " minuto"} ${listText(sets[0])} das horas ${listText(sets[1])}`;
          }
          const dayParts = [];
          if (dayText !== "*") {
            dayParts.push(`no${sets[2].size > 1 ? "s dias" : " dia"} ${listText(sets[2])} do mês`);
          }
          if (weekdayText !== "*") {
            const weekdayList = listText(sets[4], (day) => WEEKDAYS[day]);
            dayParts.push(weekdayText.replace(/\s/g, "") === "1-5" ? "de segunda a sexta" : `${dayText !== "*" ? "e também " : ""}toda ${weekdayList}`);
          }
          const dayDescription = dayParts.length ? dayParts.join(" ") : "todos os dias";
          const monthDescription = monthText === "*" ? "" : `, só em ${listText(sets[3], (month) => MONTH_NAMES[month])}`;
          return `Roda ${timeText}, ${dayDescription}${monthDescription}.`;
        }
        // Próximas execuções: avança minuto a minuto (no máximo 1 ano) procurando datas que batem
        function nextRuns({ parts, sets }, count) {
          const runs = [];
          const date = new Date();
          date.setSeconds(0, 0);
          date.setMinutes(date.getMinutes() + 1);
          const dayRestricted = parts[2] !== "*";
          const weekdayRestricted = parts[4] !== "*";
          for (let step = 0; step < 527040 && runs.length < count; step++) {
            const dayMatches = sets[2].has(date.getDate());
            const weekdayMatches = sets[4].has(date.getDay());
            // Regra do cron: se dia do mês e dia da semana estão definidos, basta um deles bater
            const matchesDay = dayRestricted && weekdayRestricted ? dayMatches || weekdayMatches : dayMatches && weekdayMatches;
            if (sets[0].has(date.getMinutes()) && sets[1].has(date.getHours()) && sets[3].has(date.getMonth() + 1) && matchesDay) {
              runs.push(new Date(date));
            }
            date.setMinutes(date.getMinutes() + 1);
          }
          return runs;
        }

        function calculate() {
          const resultBox = document.getElementById("cron-result");
          const expression = expressionInput.value;
          if (expression.trim() === "") {
            resultBox.innerHTML = emptyDisplay("Expressão cron", "Monte o agendamento ou cole uma expressão.");
            return;
          }
          let parsed;
          try {
            parsed = parseExpression(expression);
          } catch (parseError) {
            resultBox.innerHTML = emptyDisplay("Expressão inválida", escapeHtml(parseError.message));
            return;
          }
          const runs = nextRuns(parsed, 5);
          const runsHtml = runs.length
            ? `<ol class="cron-runs">${runs.map((run) => `<li>${WEEKDAYS[run.getDay()]}, ${pad(run.getDate())}/${pad(run.getMonth() + 1)}/${run.getFullYear()} às ${pad(run.getHours())}:${pad(run.getMinutes())}</li>`).join("")}</ol>`
            : "<p>Nenhuma execução no próximo ano (confira a data, por exemplo 31 de fevereiro).</p>";
          resultBox.innerHTML = `<div class="display"><span class="display-label">Expressão cron</span><span class="display-value" id="cron-output">${escapeHtml(parsed.parts.join(" "))}</span><span class="display-detail">${escapeHtml(describe(parsed))}</span><button class="copy-button" type="button" data-copy-target="cron-output">copiar</button></div>
            <div class="calculator-body"><h3>Próximas execuções (horário do seu computador)</h3>${runsHtml}<p class="notice">Formato padrão de 5 campos (Linux crontab, cPanel). Alguns sistemas usam 6 campos, com os segundos no começo (Quartz, Spring), ou rodam em UTC: confira o fuso do servidor.</p></div>`;
        }

        // Gerador: monta a expressão a partir das escolhas
        function buildExpression() {
          const frequency = document.getElementById("cron-frequency").value;
          const clamp = (id, min, max) => Math.min(max, Math.max(min, parseInt(document.getElementById(id).value, 10) || 0));
          const minute = clamp("cron-minute", 0, 59);
          const hour = clamp("cron-hour", 0, 23);
          document.querySelectorAll("[data-cron-for]").forEach((field) => {
            field.hidden = !field.dataset.cronFor.split(" ").includes(frequency);
          });
          const expressions = {
            minutes: `*/${document.getElementById("cron-interval").value} * * * *`,
            hourly: `${minute} * * * *`,
            daily: `${minute} ${hour} * * *`,
            weekdays: `${minute} ${hour} * * 1-5`,
            weekly: `${minute} ${hour} * * ${document.getElementById("cron-weekday").value}`,
            monthly: `${minute} ${hour} ${clamp("cron-monthday", 1, 31)} * *`,
          };
          expressionInput.value = expressions[frequency];
          calculate();
        }
        ["cron-frequency", "cron-interval", "cron-weekday", "cron-monthday", "cron-hour", "cron-minute"].forEach((id) => document.getElementById(id).addEventListener("input", buildExpression));
        buildExpression();
        onInputs(["cron-expression"], calculate);
      },
    },

    "jwt": {
      html: `        <div class="calculator-body">
          <div class="field"><label for="jwt-input">Token JWT</label><textarea id="jwt-input" spellcheck="false" rows="5" class="mono-input" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...."></textarea><span class="field-hint">O token é lido só no seu navegador: nada é enviado para servidor nenhum.</span></div>
        </div>
        <div id="jwt-result"></div>`,
      setup() {
        // Exemplo com dados e assinatura fictícios
        document.getElementById("jwt-input").value = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NSIsIm5hbWUiOiJNYXJpYSBTaWx2YSIsInJvbGUiOiJhZG1pbiIsImlhdCI6MTc5MDc4NDAwMCwiZXhwIjoxNzkwNzkxMjAwfQ.8zjzHxwEBXXqjeWGx4c9yI7JONxk5rT3TZRnbB6uvNM";
        const DATE_CLAIMS = { exp: "expira em", iat: "emitido em", nbf: "válido a partir de", auth_time: "login em" };

        function decodeBase64Url(part) {
          let base64 = part.replace(/-/g, "+").replace(/_/g, "/");
          base64 += "=".repeat((4 - (base64.length % 4)) % 4);
          const bytes = Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
          return new TextDecoder().decode(bytes);
        }
        function formatDateTime(seconds) {
          return new Date(seconds * 1000).toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit", second: "2-digit" }).replace(",", "");
        }
        function jsonBlock(label, value, id) {
          return `<div class="display"><span class="display-label">${label}</span><pre class="display-text jwt-json" id="${id}">${escapeHtml(JSON.stringify(value, null, 2))}</pre><button class="copy-button" type="button" data-copy-target="${id}">copiar</button></div>`;
        }

        function calculate() {
          const resultBox = document.getElementById("jwt-result");
          const token = document.getElementById("jwt-input").value.trim().replace(/^Bearer\s+/i, "");
          if (token === "") {
            resultBox.innerHTML = emptyDisplay("JWT", "Cole um token.");
            return;
          }
          const parts = token.split(".");
          if (parts.length !== 3) {
            resultBox.innerHTML = emptyDisplay("Token inválido", `Um JWT tem 3 partes separadas por ponto; este tem ${parts.length}.`);
            return;
          }
          let header;
          let payload;
          try {
            header = JSON.parse(decodeBase64Url(parts[0]));
            payload = JSON.parse(decodeBase64Url(parts[1]));
          } catch {
            resultBox.innerHTML = emptyDisplay("Token inválido", "O cabeçalho ou o payload não é um JSON em Base64URL.");
            return;
          }
          const nowSeconds = Date.now() / 1000;
          const claimRows = Object.entries(DATE_CLAIMS)
            .filter(([claim]) => typeof payload[claim] === "number")
            .map(([claim, label]) => `<tr><td><code>${claim}</code> (${label})</td><td>${formatDateTime(payload[claim])}</td></tr>`)
            .join("");
          let status = "Sem data de expiração (exp)";
          if (typeof payload.exp === "number") {
            status = payload.exp < nowSeconds ? `Expirado em ${formatDateTime(payload.exp)}` : `Válido até ${formatDateTime(payload.exp)}`;
          }
          if (typeof payload.nbf === "number" && payload.nbf > nowSeconds) {
            status = `Ainda não vale: começa em ${formatDateTime(payload.nbf)}`;
          }
          resultBox.innerHTML = display(status, escapeHtml(String(header.alg || "sem algoritmo")), `algoritmo · tipo ${escapeHtml(String(header.typ || "não informado"))}`)
            + jsonBlock("Cabeçalho (header)", header, "jwt-header")
            + jsonBlock("Dados (payload)", payload, "jwt-payload")
            + `<div class="calculator-body">${claimRows ? `<table class="data-table"><tbody>${claimRows}</tbody></table>` : ""}
              <p class="notice">A assinatura NÃO é verificada: decodificar não prova que o token é autêntico. Qualquer pessoa consegue ler o payload de um JWT, por isso nunca guarde senhas ou dados sensíveis nele.</p></div>`;
        }
        onInputs(["jwt-input"], calculate);
      },
    },

    "json-csv": {
      html: `        <div class="calculator-body">
          <div class="segmented" id="csv-direction" role="group" aria-label="Direção">
            <button type="button" data-value="json-to-csv" aria-pressed="true">JSON → CSV</button>
            <button type="button" data-value="csv-to-json" aria-pressed="false">CSV → JSON</button>
          </div>
          <div class="field"><label for="csv-input" id="csv-input-label">JSON (lista de objetos)</label><textarea id="csv-input" spellcheck="false" rows="9" class="mono-input">[{"id":1,"nome":"Ana","cidade":"São Paulo","salario":4500.5,"endereco":{"uf":"SP","cep":"01000-000"}},{"id":2,"nome":"Bruno","cidade":"Recife","salario":3200,"endereco":{"uf":"PE","cep":"50000-000"}}]</textarea></div>
          <div class="field"><label for="csv-file">Ou abra um arquivo (até 50 MB)</label><input id="csv-file" type="file" accept=".json,.csv,.txt,application/json,text/csv,text/plain"><span class="field-hint" id="csv-file-info">O arquivo é lido no seu navegador e não é enviado para lugar nenhum.</span></div>
          <div class="field-row">
            <div class="field"><label for="csv-separator">Separador do CSV</label><select id="csv-separator">
              <option value=";">; Excel em português (vírgula nos decimais)</option>
              <option value=",">, padrão internacional</option>
              <option value="tab">Tab</option>
            </select></div>
          </div>
          <label class="check" id="csv-flatten-option"><input type="checkbox" id="csv-flatten" checked> Separar objetos dentro de objetos em colunas (endereco.uf, endereco.cep)</label>
        </div>
        <div id="csv-result"></div>`,
      setup() {
        const MAX_FILE_BYTES = 50 * 1024 * 1024;
        const TEXTAREA_LIMIT = 1024 * 1024;
        const PREVIEW_CHARACTERS = 200000;
        const PREVIEW_ROWS = 50;
        const input = document.getElementById("csv-input");
        let direction = "json-to-csv";
        let fileText = null;
        let fileName = "";
        let output = { text: "", extension: "csv", type: "text/csv" };
        const separator = () => (document.getElementById("csv-separator").value === "tab" ? "\t" : document.getElementById("csv-separator").value);
        const isExcelBr = () => document.getElementById("csv-separator").value === ";";

        // "usuario": {"nome": "Ana"} → coluna "usuario.nome"; listas viram texto JSON
        function flatten(value, prefix, row, shouldFlatten) {
          Object.entries(value).forEach(([key, item]) => {
            const column = prefix ? `${prefix}.${key}` : key;
            if (shouldFlatten && item && typeof item === "object" && !Array.isArray(item)) {
              flatten(item, column, row, shouldFlatten);
            } else {
              row[column] = item;
            }
          });
          return row;
        }
        function csvCell(value, separatorText) {
          let text;
          if (value === null || value === undefined) {
            text = "";
          } else if (typeof value === "object") {
            text = JSON.stringify(value);
          } else if (typeof value === "number" && isExcelBr()) {
            text = String(value).replace(".", ","); // Excel em português lê 4500,5
          } else {
            text = String(value);
          }
          // Campo com separador, aspas ou quebra de linha vai entre aspas (aspas internas dobradas)
          return text.includes(separatorText) || /["\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
        }
        function jsonToCsv(text) {
          let data = JSON.parse(text);
          if (!Array.isArray(data)) {
            // Objeto com uma lista dentro (ex.: {"itens": [...]}): usa a lista; senão vira uma linha só
            const innerList = data && typeof data === "object" ? Object.values(data).find(Array.isArray) : null;
            data = innerList ?? [data];
          }
          const shouldFlatten = document.getElementById("csv-flatten").checked;
          const rows = data.map((item) => (item && typeof item === "object" && !Array.isArray(item) ? flatten(item, "", {}, shouldFlatten) : { valor: item }));
          const columns = [];
          rows.forEach((row) => Object.keys(row).forEach((column) => { if (!columns.includes(column)) columns.push(column); }));
          const separatorText = separator();
          const lines = [columns.map((column) => csvCell(column, separatorText)).join(separatorText)];
          rows.forEach((row) => lines.push(columns.map((column) => csvCell(row[column], separatorText)).join(separatorText)));
          return { columns, rows: rows.map((row) => columns.map((column) => row[column])), text: lines.join("\r\n") };
        }
        // Lê CSV com campos entre aspas (inclusive com quebra de linha dentro)
        function parseCsv(text, separatorText) {
          const rows = [];
          let row = [];
          let cell = "";
          let insideQuotes = false;
          for (let index = 0; index < text.length; index++) {
            const character = text[index];
            if (insideQuotes) {
              if (character === '"' && text[index + 1] === '"') {
                cell += '"';
                index++;
              } else if (character === '"') {
                insideQuotes = false;
              } else {
                cell += character;
              }
            } else if (character === '"') {
              insideQuotes = true;
            } else if (character === separatorText) {
              row.push(cell);
              cell = "";
            } else if (character === "\n" || character === "\r") {
              if (character === "\r" && text[index + 1] === "\n") {
                index++;
              }
              row.push(cell);
              rows.push(row);
              row = [];
              cell = "";
            } else {
              cell += character;
            }
          }
          if (cell !== "" || row.length) {
            row.push(cell);
            rows.push(row);
          }
          return rows.filter((cells) => cells.some((value) => value !== ""));
        }
        // Separador mais frequente na primeira linha
        function detectSeparator(text) {
          const firstLine = text.slice(0, text.indexOf("\n") === -1 ? text.length : text.indexOf("\n"));
          return [";", ",", "\t"].reduce((best, candidate) => (firstLine.split(candidate).length > firstLine.split(best).length ? candidate : best), ";");
        }
        // "4500,5" e "4500.5" viram número; true/false viram booleano; o resto continua texto (CEP com zero à esquerda também)
        function typedValue(text, separatorText) {
          const trimmed = text.trim();
          if (/^(true|false)$/i.test(trimmed)) {
            return trimmed.toLowerCase() === "true";
          }
          const numberText = separatorText === ";" ? trimmed.replace(",", ".") : trimmed;
          return /^-?(0|[1-9]\d*)(\.\d+)?$/.test(numberText) && numberText.length < 16 ? Number(numberText) : text;
        }
        function csvToJson(text) {
          const separatorText = detectSeparator(text);
          const [header = [], ...lines] = parseCsv(text.replace(/^﻿/, ""), separatorText);
          const objects = lines.map((cells) => Object.fromEntries(header.map((column, index) => [column.trim() || `coluna${index + 1}`, typedValue(cells[index] ?? "", separatorText)])));
          return { columns: header, rows: lines, objects, separatorText, text: JSON.stringify(objects, null, 2) };
        }

        function previewTable(columns, rows) {
          const shownColumns = columns.slice(0, 20);
          const head = shownColumns.map((column) => `<th>${escapeHtml(String(column))}</th>`).join("");
          const body = rows.slice(0, PREVIEW_ROWS).map((cells) => `<tr>${shownColumns.map((_, index) => {
            const value = cells[index];
            return `<td>${escapeHtml(value === null || value === undefined ? "" : typeof value === "object" ? JSON.stringify(value) : String(value))}</td>`;
          }).join("")}</tr>`).join("");
          return `<div class="table-scroll csv-preview"><table class="data-table"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
        }

        function calculate() {
          const text = fileText ?? input.value;
          const resultBox = document.getElementById("csv-result");
          const isJsonToCsv = direction === "json-to-csv";
          if (text.trim() === "") {
            resultBox.innerHTML = emptyDisplay(isJsonToCsv ? "CSV" : "JSON", isJsonToCsv ? "Cole um JSON ou abra um arquivo." : "Cole um CSV ou abra um arquivo.");
            return;
          }
          let converted;
          try {
            converted = isJsonToCsv ? jsonToCsv(text) : csvToJson(text);
          } catch (error) {
            resultBox.innerHTML = display("JSON inválido", "—", `${escapeHtml(error.message)}. Confira no <a href="/calculadoras/json-formatter">JSON formatter</a>, que mostra a linha do erro.`);
            return;
          }
          output = isJsonToCsv
            ? { text: (isExcelBr() ? "﻿" : "") + converted.text, extension: "csv", type: "text/csv;charset=utf-8" } // BOM: o Excel reconhece os acentos
            : { text: converted.text, extension: "json", type: "application/json" };
          const lineCount = converted.rows.length;
          const separatorName = { ";": "ponto e vírgula", ",": "vírgula", "\t": "tab" }[converted.separatorText] ?? "";
          const label = isJsonToCsv
            ? `CSV · ${formatNumber(lineCount)} linhas · ${formatNumber(converted.columns.length)} colunas`
            : `JSON · ${formatNumber(lineCount)} objetos · separador detectado: ${separatorName}`;
          const preview = converted.text.length > PREVIEW_CHARACTERS ? `${converted.text.slice(0, PREVIEW_CHARACTERS)}\n\n… (resultado grande: use "copiar" ou "baixar")` : converted.text;
          resultBox.innerHTML = `<div class="display"><span class="display-label">${label}</span>
            ${previewTable(converted.columns, converted.rows)}${lineCount > PREVIEW_ROWS ? `<span class="display-detail">Tabela mostra as primeiras ${PREVIEW_ROWS} linhas.</span>` : ""}
            <pre class="display-text jwt-json" id="csv-output">${escapeHtml(preview)}</pre>
            <div class="button-row"><button class="copy-button" type="button" id="csv-copy">copiar</button><button class="copy-button" type="button" id="csv-download">baixar .${output.extension}</button></div></div>`;
          document.getElementById("csv-copy").addEventListener("click", async (clickEvent) => {
            try {
              await navigator.clipboard.writeText(output.text.replace(/^﻿/, ""));
              clickEvent.currentTarget.textContent = "copiado!";
            } catch {
              clickEvent.currentTarget.textContent = "não foi possível copiar: use baixar";
            }
          });
          document.getElementById("csv-download").addEventListener("click", () => {
            const link = document.createElement("a");
            link.href = URL.createObjectURL(new Blob([output.text], { type: output.type }));
            link.download = `${fileName.replace(/\.(json|csv|txt)$/i, "") || "convertido"}.${output.extension}`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 1000);
          });
        }

        setupSegmented("csv-direction", (value) => {
          direction = value;
          const isJsonToCsv = value === "json-to-csv";
          document.getElementById("csv-input-label").textContent = isJsonToCsv ? "JSON (lista de objetos)" : "CSV (a primeira linha é o cabeçalho)";
          document.getElementById("csv-flatten-option").hidden = !isJsonToCsv;
          document.getElementById("csv-separator").closest(".field").hidden = !isJsonToCsv;
          if (fileText === null) {
            input.value = isJsonToCsv
              ? '[{"id":1,"nome":"Ana","cidade":"São Paulo","salario":4500.5},{"id":2,"nome":"Bruno","cidade":"Recife","salario":3200}]'
              : "id;nome;cidade;salario\n1;Ana;São Paulo;4500,5\n2;Bruno;Recife;3200";
          }
          calculate();
        });
        input.addEventListener("input", () => {
          fileText = null;
          fileName = "";
          document.getElementById("csv-file-info").textContent = "O arquivo é lido no seu navegador e não é enviado para lugar nenhum.";
          calculate();
        });
        document.getElementById("csv-file").addEventListener("change", async (changeEvent) => {
          const file = changeEvent.target.files[0];
          const info = document.getElementById("csv-file-info");
          if (!file) {
            return;
          }
          if (file.size > MAX_FILE_BYTES) {
            info.textContent = "Arquivo maior que 50 MB: grande demais para converter no navegador.";
            return;
          }
          const text = await file.text();
          fileName = file.name;
          if (text.length > TEXTAREA_LIMIT) {
            fileText = text;
            input.value = "";
            input.placeholder = `Arquivo ${file.name} carregado (grande demais para mostrar aqui).`;
          } else {
            fileText = null;
            input.value = text;
          }
          info.textContent = `Arquivo: ${file.name}`;
          calculate();
        });
        onInputs(["csv-separator", "csv-flatten"], calculate);
      },
    },

    "regex": {
      html: `        <div class="calculator-body">
          <div class="field"><label for="regex-preset">Comece com um padrão pronto (opcional)</label><select id="regex-preset"><option value="">Escolha para preencher…</option></select></div>
          <div class="field"><label for="regex-pattern">Expressão regular</label><div class="regex-input"><span aria-hidden="true">/</span><input id="regex-pattern" class="mono-input" spellcheck="false" autocomplete="off" value="\\b\\d{5}-?\\d{3}\\b"><span aria-hidden="true">/</span></div></div>
          <div class="regex-flags" role="group" aria-label="Opções (flags)">
            <label class="check"><input type="checkbox" id="regex-flag-g" checked> <b>g</b> todas as ocorrências</label>
            <label class="check"><input type="checkbox" id="regex-flag-i"> <b>i</b> ignora maiúsculas</label>
            <label class="check"><input type="checkbox" id="regex-flag-m"> <b>m</b> ^ e $ por linha</label>
            <label class="check"><input type="checkbox" id="regex-flag-s"> <b>s</b> ponto pega quebra de linha</label>
            <label class="check"><input type="checkbox" id="regex-flag-u"> <b>u</b> unicode</label>
          </div>
          <div class="field"><label for="regex-text">Texto para testar</label><textarea id="regex-text" spellcheck="false" rows="6" class="mono-input">Entrega na Av. Paulista, 1000 - CEP 01310-100, São Paulo.
Retirada: Rua da Aurora, 50, CEP 50050000, Recife.
Código inválido: 1234-567</textarea></div>
          <div class="field"><label for="regex-replace">Substituir por (opcional; use $1, $2 para os grupos)</label><input id="regex-replace" class="mono-input" spellcheck="false" autocomplete="off" placeholder="ex.: [CEP]"></div>
        </div>
        <div id="regex-result"></div>`,
      setup() {
        const MAX_TEXT = 200000;
        const MAX_MATCHES = 1000;
        // Padrões prontos para começar (o "criador"): ajustados para formatos brasileiros
        const PRESETS = [
          ["E-mail", "[\\w.+-]+@[\\w-]+(\\.[\\w-]+)+", "i", "Fale com ana.silva@empresa.com.br ou suporte+site@exemplo.com"],
          ["CPF (com ou sem pontos)", "\\b\\d{3}\\.?\\d{3}\\.?\\d{3}-?\\d{2}\\b", "", "CPF 123.456.789-09 ou 12345678909"],
          ["CNPJ (com ou sem pontos)", "\\b\\d{2}\\.?\\d{3}\\.?\\d{3}\\/?\\d{4}-?\\d{2}\\b", "", "CNPJ 12.345.678/0001-95 ou 12345678000195"],
          ["CEP", "\\b\\d{5}-?\\d{3}\\b", "", "CEP 01310-100 e 50050000"],
          ["Telefone com DDD", "\\(?\\b\\d{2}\\)?\\s?9?\\d{4}-?\\d{4}\\b", "", "Ligue (11) 98765-4321 ou 81 3333-4444"],
          ["Data dd/mm/aaaa", "\\b(0[1-9]|[12]\\d|3[01])\\/(0[1-9]|1[0-2])\\/(\\d{4})\\b", "", "Vencimento 05/11/2026; início 31/12/2025; errado 32/13/2026"],
          ["Hora hh:mm", "\\b([01]\\d|2[0-3]):[0-5]\\d\\b", "", "Abre 08:30, fecha 18:00, inválido 25:61"],
          ["Placa de carro (Mercosul e antiga)", "\\b[A-Z]{3}-?\\d[A-Z\\d]\\d{2}\\b", "i", "Placas ABC1D23, ABC-1234 e XYZ9876"],
          ["Valor em reais", "R\\$\\s?\\d{1,3}(\\.\\d{3})*(,\\d{2})?", "", "Total R$ 1.234,56, frete R$ 20,00 e taxa R$5"],
          ["URL", "https?:\\/\\/[\\w.-]+(\\/[\\w\\-./?%&=#]*)?", "i", "Veja https://vibe2000.com.br/calculadoras e http://exemplo.com/a?b=1"],
          ["Endereço IPv4", "\\b((25[0-5]|2[0-4]\\d|1?\\d?\\d)\\.){3}(25[0-5]|2[0-4]\\d|1?\\d?\\d)\\b", "", "Servidores 192.168.0.1 e 10.0.0.254; inválido 300.1.1.1"],
          ["Cor HEX", "#([0-9a-f]{6}|[0-9a-f]{3})\\b", "i", "Cores #0e6b4f, #FFF e #12345 (inválida)"],
          ["Senha forte (8+, maiúscula, minúscula, número)", "^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d).{8,}$", "m", "fraca123\nForte2026\nSemNumero"],
          ["Palavras repetidas seguidas", "\\b(\\w+)\\s+\\1\\b", "gi", "Isso é é um teste com com palavras repetidas."],
          ["Espaços sobrando no fim da linha", "[ \\t]+$", "m", "linha com espaço   \nlinha certa\noutra com tab\t"],
        ];
        const presetSelect = document.getElementById("regex-preset");
        presetSelect.innerHTML += PRESETS.map(([name], index) => `<option value="${index}">${escapeHtml(name)}</option>`).join("");
        presetSelect.addEventListener("change", () => {
          const preset = PRESETS[Number(presetSelect.value)];
          if (!preset) {
            return;
          }
          const [, pattern, flags, sample] = preset;
          document.getElementById("regex-pattern").value = pattern;
          ["g", "i", "m", "s", "u"].forEach((flag) => { document.getElementById(`regex-flag-${flag}`).checked = flag === "g" || flags.includes(flag); });
          document.getElementById("regex-text").value = sample;
          calculate();
        });

        function calculate() {
          const pattern = document.getElementById("regex-pattern").value;
          const text = document.getElementById("regex-text").value.slice(0, MAX_TEXT);
          const replacement = document.getElementById("regex-replace").value;
          const flags = ["g", "i", "m", "s", "u"].filter((flag) => document.getElementById(`regex-flag-${flag}`).checked).join("");
          const resultBox = document.getElementById("regex-result");
          if (pattern === "") {
            resultBox.innerHTML = emptyDisplay("Ocorrências", "Digite a expressão regular.");
            return;
          }
          let regex;
          try {
            regex = new RegExp(pattern, flags.includes("g") ? flags : flags + "g"); // g interno para listar; sem g mostra só a 1ª
          } catch (error) {
            resultBox.innerHTML = display("Expressão inválida", "—", escapeHtml(error.message.replace(/^Invalid regular expression: /, "")));
            return;
          }
          const allMatches = [];
          for (const match of text.matchAll(regex)) {
            allMatches.push(match);
            if (allMatches.length >= MAX_MATCHES || !flags.includes("g")) {
              break;
            }
          }
          // Texto com as ocorrências marcadas
          let highlighted = "";
          let position = 0;
          allMatches.forEach((match) => {
            highlighted += escapeHtml(text.slice(position, match.index));
            highlighted += match[0] === "" ? '<mark class="regex-empty"></mark>' : `<mark>${escapeHtml(match[0])}</mark>`;
            position = match.index + match[0].length;
          });
          highlighted += escapeHtml(text.slice(position));

          const groupCount = Math.max(0, ...allMatches.map((match) => match.length - 1));
          const groupNames = Object.keys(allMatches[0]?.groups ?? {});
          const rows = allMatches.slice(0, 100).map((match, index) => {
            const groups = Array.from({ length: groupCount }, (_, groupIndex) => `<td>${match[groupIndex + 1] === undefined ? "—" : escapeHtml(match[groupIndex + 1])}</td>`).join("");
            return `<tr><td>${index + 1}</td><td><code>${escapeHtml(match[0]) || "(vazio)"}</code></td><td>${match.index}</td>${groups}</tr>`;
          }).join("");
          const groupHeaders = Array.from({ length: groupCount }, (_, groupIndex) => `<th>Grupo ${groupIndex + 1}${groupNames[groupIndex] ? ` (${escapeHtml(groupNames[groupIndex])})` : ""}</th>`).join("");
          const table = allMatches.length ? `<div class="table-scroll"><table class="data-table"><thead><tr><th>#</th><th>Ocorrência</th><th>Posição</th>${groupHeaders}</tr></thead><tbody>${rows}</tbody></table></div>` : "";
          let replaced = "";
          if (replacement !== "") {
            replaced = `<span class="display-label">Texto depois da substituição</span><pre class="display-text jwt-json" id="regex-replaced">${escapeHtml(text.replace(new RegExp(pattern, flags), replacement))}</pre><button class="copy-button" type="button" data-copy-target="regex-replaced">copiar</button>`;
          }
          const count = allMatches.length;
          resultBox.innerHTML = `<div class="display"><span class="display-label">Ocorrências · /${escapeHtml(pattern)}/${flags}</span>
            <span class="display-value">${count === MAX_MATCHES ? `${formatNumber(count)}+` : formatNumber(count)}</span>
            <span class="display-detail">${count === 0 ? "Nenhuma parte do texto combina com a expressão." : count === 1 ? "1 ocorrência encontrada" : `${formatNumber(count)} ocorrências encontradas`}${allMatches.length > 100 ? " (a tabela mostra as 100 primeiras)" : ""}</span>
            <pre class="display-text jwt-json regex-highlight">${highlighted}</pre>${table}${replaced}</div>`;
        }
        onInputs(["regex-pattern", "regex-text", "regex-replace", "regex-flag-g", "regex-flag-i", "regex-flag-m", "regex-flag-s", "regex-flag-u"], calculate);
      },
    },

    "cores": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="color-input">Cor (HEX, RGB, HSL ou nome em inglês)</label><input id="color-input" class="mono-input" spellcheck="false" autocomplete="off" value="#0e6b4f"></div>
            <div class="field color-picker-field"><label for="color-picker">Escolher</label><input id="color-picker" type="color" value="#0e6b4f"></div>
          </div>
          <p class="field-hint">Exemplos: <code>#ff8800</code>, <code>rgb(14, 107, 79)</code>, <code>hsl(162, 77%, 24%)</code>, <code>tomato</code>.</p>
        </div>
        <div id="color-result"></div>`,
      setup() {
        const colorInput = document.getElementById("color-input");
        const colorPicker = document.getElementById("color-picker");
        // O próprio navegador entende o texto da cor (HEX, rgb(), hsl(), nomes): o canvas devolve em #rrggbb
        const canvasContext = document.createElement("canvas").getContext("2d");
        function parseColor(text) {
          const value = text.trim();
          if (value === "") {
            return null;
          }
          // HEX sem # ("0e6b4f") também vale
          const candidate = /^[0-9a-f]{3}([0-9a-f]{3})?$/i.test(value) ? `#${value}` : value;
          canvasContext.fillStyle = "#010203";
          canvasContext.fillStyle = candidate;
          const first = canvasContext.fillStyle;
          canvasContext.fillStyle = "#040506";
          canvasContext.fillStyle = candidate;
          if (first !== canvasContext.fillStyle) {
            return null; // o navegador recusou a cor (ficou o valor anterior)
          }
          const hexMatch = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(first);
          if (hexMatch) {
            return { red: parseInt(hexMatch[1], 16), green: parseInt(hexMatch[2], 16), blue: parseInt(hexMatch[3], 16), alpha: 1 };
          }
          const rgbaMatch = /rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)/.exec(first);
          return rgbaMatch ? { red: Math.round(rgbaMatch[1]), green: Math.round(rgbaMatch[2]), blue: Math.round(rgbaMatch[3]), alpha: rgbaMatch[4] === undefined ? 1 : Number(rgbaMatch[4]) } : null;
        }
        const toHex = ({ red, green, blue }) => `#${[red, green, blue].map((channel) => channel.toString(16).padStart(2, "0")).join("")}`;
        function rgbToHsl({ red, green, blue }) {
          const [r, g, b] = [red / 255, green / 255, blue / 255];
          const max = Math.max(r, g, b);
          const min = Math.min(r, g, b);
          const lightness = (max + min) / 2;
          if (max === min) {
            return { hue: 0, saturation: 0, lightness: lightness * 100 };
          }
          const delta = max - min;
          const saturation = lightness > 0.5 ? delta / (2 - max - min) : delta / (max + min);
          const hue = max === r ? ((g - b) / delta + (g < b ? 6 : 0)) : max === g ? (b - r) / delta + 2 : (r - g) / delta + 4;
          return { hue: hue * 60, saturation: saturation * 100, lightness: lightness * 100 };
        }
        function hslToRgb({ hue, saturation, lightness }) {
          const s = saturation / 100;
          const l = lightness / 100;
          const chroma = (1 - Math.abs(2 * l - 1)) * s;
          const huePart = (((hue % 360) + 360) % 360) / 60;
          const second = chroma * (1 - Math.abs((huePart % 2) - 1));
          const [r, g, b] = huePart < 1 ? [chroma, second, 0] : huePart < 2 ? [second, chroma, 0] : huePart < 3 ? [0, chroma, second] : huePart < 4 ? [0, second, chroma] : huePart < 5 ? [second, 0, chroma] : [chroma, 0, second];
          const match = l - chroma / 2;
          return { red: Math.round((r + match) * 255), green: Math.round((g + match) * 255), blue: Math.round((b + match) * 255) };
        }
        function rgbToHsv({ red, green, blue }) {
          const max = Math.max(red, green, blue) / 255;
          const min = Math.min(red, green, blue) / 255;
          return { hue: rgbToHsl({ red, green, blue }).hue, saturation: max === 0 ? 0 : ((max - min) / max) * 100, value: max * 100 };
        }
        function rgbToCmyk({ red, green, blue }) {
          const black = 1 - Math.max(red, green, blue) / 255;
          if (black === 1) {
            return { cyan: 0, magenta: 0, yellow: 0, black: 100 };
          }
          const part = (channel) => ((1 - channel / 255 - black) / (1 - black)) * 100;
          return { cyan: part(red), magenta: part(green), yellow: part(blue), black: black * 100 };
        }
        // Contraste (WCAG): 4,5 para texto normal (AA), 7 para AAA
        function luminance({ red, green, blue }) {
          const linear = (channel) => {
            const value = channel / 255;
            return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
          };
          return 0.2126 * linear(red) + 0.7152 * linear(green) + 0.0722 * linear(blue);
        }
        const contrast = (first, second) => {
          const [lighter, darker] = [luminance(first), luminance(second)].sort((a, b) => b - a);
          return (lighter + 0.05) / (darker + 0.05);
        };
        const contrastLevel = (ratio) => (ratio >= 7 ? "AAA" : ratio >= 4.5 ? "AA" : ratio >= 3 ? "AA só p/ texto grande" : "reprovado");
        const round = (value) => Math.round(value);
        const swatch = (rgb, label = "") => {
          const hex = toHex(rgb);
          const textColor = contrast(rgb, { red: 255, green: 255, blue: 255 }) >= contrast(rgb, { red: 0, green: 0, blue: 0 }) ? "#fff" : "#111";
          return `<button type="button" class="color-swatch" data-color="${hex}" style="background:${hex};color:${textColor}" title="Usar ${hex}">${label ? `<small>${label}</small>` : ""}${hex}</button>`;
        };

        function calculate() {
          const resultBox = document.getElementById("color-result");
          const rgb = parseColor(colorInput.value);
          if (!rgb) {
            resultBox.innerHTML = emptyDisplay("Cor", "Cor não reconhecida. Use HEX (#0e6b4f), rgb(), hsl() ou um nome em inglês (red, navy…).");
            return;
          }
          const hex = toHex(rgb);
          colorPicker.value = hex;
          const hsl = rgbToHsl(rgb);
          const hsv = rgbToHsv(rgb);
          const cmyk = rgbToCmyk(rgb);
          const formats = [
            ["HEX", hex.toUpperCase()],
            ["RGB", `rgb(${rgb.red}, ${rgb.green}, ${rgb.blue})`],
            ["HSL", `hsl(${round(hsl.hue)}, ${round(hsl.saturation)}%, ${round(hsl.lightness)}%)`],
            ["HSV / HSB", `hsv(${round(hsv.hue)}, ${round(hsv.saturation)}%, ${round(hsv.value)}%)`],
            ["CMYK", `cmyk(${round(cmyk.cyan)}%, ${round(cmyk.magenta)}%, ${round(cmyk.yellow)}%, ${round(cmyk.black)}%)`],
            ["RGB em 0–1", `${(rgb.red / 255).toFixed(3)}, ${(rgb.green / 255).toFixed(3)}, ${(rgb.blue / 255).toFixed(3)}`],
            ["Variável CSS", `--cor-principal: ${hex};`],
          ];
          if (rgb.alpha < 1) {
            formats.splice(2, 0, ["RGBA", `rgba(${rgb.red}, ${rgb.green}, ${rgb.blue}, ${rgb.alpha})`]);
          }
          const formatRows = formats.map(([name, value], index) => `<div class="color-format"><span>${name}</span><code id="color-format-${index}">${escapeHtml(value)}</code><button type="button" class="copy-button" data-copy-target="color-format-${index}">copiar</button></div>`).join("");
          const white = { red: 255, green: 255, blue: 255 };
          const black = { red: 0, green: 0, blue: 0 };
          const onWhite = contrast(rgb, white);
          const onBlack = contrast(rgb, black);
          // Tons: mesma cor do mais claro ao mais escuro; harmonias: girando o matiz no círculo de cores
          const shades = [95, 85, 75, 65, 55, 45, 35, 25, 15].map((lightness) => swatch(hslToRgb({ ...hsl, lightness })));
          const rotate = (degrees) => hslToRgb({ ...hsl, hue: hsl.hue + degrees });
          resultBox.innerHTML = `<div class="display"><span class="display-label">Cor</span>
            <div class="color-preview" style="background:${hex}"></div>
            <div class="color-formats">${formatRows}</div>
            <span class="display-label">Contraste (acessibilidade · WCAG)</span>
            <div class="color-contrast">
              <div style="background:${hex};color:#fff">Texto branco · ${formatNumber(onWhite, 2)}:1 · ${contrastLevel(onWhite)}</div>
              <div style="background:${hex};color:#000">Texto preto · ${formatNumber(onBlack, 2)}:1 · ${contrastLevel(onBlack)}</div>
            </div>
            <span class="display-label">Tons (do claro ao escuro) · toque para usar</span>
            <div class="color-swatches">${shades.join("")}</div>
            <span class="display-label">Combinações</span>
            <div class="color-swatches">${swatch(rotate(180), "complementar")}${swatch(rotate(-30), "análoga")}${swatch(rotate(30), "análoga")}${swatch(rotate(120), "tríade")}${swatch(rotate(240), "tríade")}</div>
          </div>`;
        }
        colorPicker.addEventListener("input", () => {
          colorInput.value = colorPicker.value;
          calculate();
        });
        document.getElementById("color-result").addEventListener("click", (clickEvent) => {
          const swatchButton = clickEvent.target.closest(".color-swatch");
          if (swatchButton) {
            colorInput.value = swatchButton.dataset.color;
            calculate();
            colorInput.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        });
        onInputs(["color-input"], calculate);
      },
    },

    "tamanho-dados": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="data-size-value">Valor</label><input id="data-size-value" inputmode="decimal" value="500"></div>
            <div class="field"><label for="data-size-unit">Unidade</label><select id="data-size-unit">
              <option value="bit">bits (b)</option><option value="kbit">Kilobits (Kb)</option><option value="mbit">Megabits (Mb)</option><option value="gbit">Gigabits (Gb)</option><option value="tbit">Terabits (Tb)</option>
              <option value="byte">Bytes (B)</option><option value="kb">Kilobytes (KB)</option><option value="mb" selected>Megabytes (MB)</option><option value="gb">Gigabytes (GB)</option><option value="tb">Terabytes (TB)</option><option value="pb">Petabytes (PB)</option>
              <option value="kib">Kibibytes (KiB)</option><option value="mib">Mebibytes (MiB)</option><option value="gib">Gibibytes (GiB)</option><option value="tib">Tebibytes (TiB)</option>
            </select></div>
            <div class="field"><label for="data-size-base">KB, MB, GB valem</label><select id="data-size-base"><option value="1000">1.000 (decimal, padrão SI)</option><option value="1024">1.024 (como o Windows mostra)</option></select></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="data-size-speed">Velocidade da internet (Mbps, opcional)</label><input id="data-size-speed" inputmode="decimal" value="100"></div>
          </div>
        </div>
        <div id="data-size-result"></div>`,
      setup() {
        // Tudo é convertido para bits. Unidades decimais (SI) usam 1.000; KiB, MiB… sempre 1.024
        const BIT_UNITS = { bit: 0, kbit: 1, mbit: 2, gbit: 3, tbit: 4 };
        const BYTE_UNITS = { byte: 0, kb: 1, mb: 2, gb: 3, tb: 4, pb: 5 };
        const BINARY_UNITS = { kib: 1, mib: 2, gib: 3, tib: 4 };
        function toBits(value, unit, base) {
          if (unit in BIT_UNITS) {
            return value * Math.pow(base, BIT_UNITS[unit]);
          }
          if (unit in BYTE_UNITS) {
            return value * 8 * Math.pow(base, BYTE_UNITS[unit]);
          }
          return value * 8 * Math.pow(1024, BINARY_UNITS[unit]);
        }
        // Até 6 casas significativas, sem notação científica para números comuns
        function formatAmount(value) {
          if (value === 0) {
            return "0";
          }
          if (Math.abs(value) >= 1e15 || Math.abs(value) < 1e-6) {
            return value.toExponential(4).replace(".", ",");
          }
          return new Intl.NumberFormat("pt-BR", { maximumSignificantDigits: Math.abs(value) >= 1e6 ? 16 : 6 }).format(value);
        }
        function formatDuration(totalSeconds) {
          if (totalSeconds < 1) {
            return "menos de 1 segundo";
          }
          const days = Math.floor(totalSeconds / 86400);
          const hours = Math.floor((totalSeconds % 86400) / 3600);
          const minutes = Math.floor((totalSeconds % 3600) / 60);
          const seconds = Math.round(totalSeconds % 60);
          return [days && `${days} d`, hours && `${hours} h`, minutes && `${minutes} min`, !days && seconds && `${seconds} s`].filter(Boolean).join(" ");
        }

        function calculate() {
          const value = parseNumber(document.getElementById("data-size-value").value);
          const unit = document.getElementById("data-size-unit").value;
          const base = Number(document.getElementById("data-size-base").value);
          const speed = parseNumber(document.getElementById("data-size-speed").value);
          const resultBox = document.getElementById("data-size-result");
          if (Number.isNaN(value) || value < 0) {
            resultBox.innerHTML = emptyDisplay("Tamanho", "Digite um valor.");
            return;
          }
          const bits = toBits(value, unit, base);
          const bytes = bits / 8;
          const unitLabel = document.getElementById("data-size-unit").selectedOptions[0].textContent.match(/\((.+)\)/)[1];
          const row = (label, amount) => `<tr><td>${label}</td><td><b>${formatAmount(amount)}</b></td></tr>`;
          const decimalRows = [["Bits (b)", bits], ["Kilobits (Kb)", bits / base], ["Megabits (Mb)", bits / base ** 2], ["Gigabits (Gb)", bits / base ** 3], ["Terabits (Tb)", bits / base ** 4],
            ["Bytes (B)", bytes], ["Kilobytes (KB)", bytes / base], ["Megabytes (MB)", bytes / base ** 2], ["Gigabytes (GB)", bytes / base ** 3], ["Terabytes (TB)", bytes / base ** 4], ["Petabytes (PB)", bytes / base ** 5]];
          const binaryRows = [["Kibibytes (KiB)", bytes / 1024], ["Mebibytes (MiB)", bytes / 1024 ** 2], ["Gibibytes (GiB)", bytes / 1024 ** 3], ["Tebibytes (TiB)", bytes / 1024 ** 4]];
          // Velocidade de internet é em megabits por segundo (sempre 1.000.000 bits)
          const downloadTime = speed > 0 ? formatDuration(bits / (speed * 1e6)) : null;
          resultBox.innerHTML = display(
            `${formatAmount(value)} ${unitLabel} =`,
            `${formatAmount(bytes / base ** 2)} MB`,
            `${formatAmount(bytes / base ** 3)} GB · ${formatAmount(bytes)} bytes · ${formatAmount(bits / base ** 2)} Mb (megabits)`,
            downloadTime ? [[`Download a ${formatNumber(speed, 1)} Mbps`, downloadTime]] : [],
            `<div class="table-scroll"><table class="data-table"><thead><tr><th>Unidade (base ${formatNumber(base)})</th><th>Valor</th></tr></thead><tbody>${decimalRows.map(([label, amount]) => row(label, amount)).join("")}</tbody></table></div>
             <div class="table-scroll"><table class="data-table"><thead><tr><th>Binárias (sempre 1.024)</th><th>Valor</th></tr></thead><tbody>${binaryRows.map(([label, amount]) => row(label, amount)).join("")}</tbody></table></div>`,
          );
        }
        onInputs(["data-size-value", "data-size-unit", "data-size-base", "data-size-speed"], calculate);
      },
    },

    "http-status": {
      html: `        <div class="calculator-body">
          <div class="field"><label for="http-search">Procure pelo código ou por uma palavra</label><input id="http-search" class="mono-input" autocomplete="off" spellcheck="false" placeholder="ex.: 404, redirect, timeout, cloudflare"></div>
          <div class="segmented http-families" id="http-family" role="group" aria-label="Família">
            <button type="button" data-value="all" aria-pressed="true">Todos</button>
            <button type="button" data-value="1" aria-pressed="false">1xx</button>
            <button type="button" data-value="2" aria-pressed="false">2xx</button>
            <button type="button" data-value="3" aria-pressed="false">3xx</button>
            <button type="button" data-value="4" aria-pressed="false">4xx</button>
            <button type="button" data-value="5" aria-pressed="false">5xx</button>
          </div>
        </div>
        <div id="http-result"></div>`,
      setup() {
        const FAMILIES = {
          1: ["Informação", "O servidor recebeu o pedido e o processo continua."],
          2: ["Sucesso", "Deu certo."],
          3: ["Redirecionamento", "O conteúdo está em outro lugar; o navegador segue para lá."],
          4: ["Erro do cliente", "O problema está no pedido (endereço, dados, login ou permissão)."],
          5: ["Erro do servidor", "O pedido parecia certo, mas o servidor falhou ao responder."],
        };
        // [código, nome, o que significa, o que verificar]
        const CODES = [
          [100, "Continue", "O servidor recebeu o cabeçalho e o cliente pode mandar o corpo do pedido.", "Normal em uploads grandes com o cabeçalho Expect: 100-continue."],
          [101, "Switching Protocols", "O servidor aceitou trocar de protocolo, como de HTTP para WebSocket.", "Esperado ao abrir um WebSocket."],
          [103, "Early Hints", "Dicas antecipadas para o navegador já carregar CSS e scripts.", "Otimização de desempenho; não exige ação."],
          [200, "OK", "Deu certo. A resposta traz o conteúdo pedido.", "Se a tela está errada mesmo com 200, o problema está no conteúdo devolvido (veja o corpo da resposta)."],
          [201, "Created", "O recurso foi criado (ex.: cadastro salvo).", "O cabeçalho Location costuma trazer o endereço do item novo."],
          [202, "Accepted", "O pedido foi aceito, mas ainda vai ser processado (fila).", "Consulte o status depois; o resultado não vem agora."],
          [204, "No Content", "Deu certo e não há conteúdo para devolver.", "Comum em DELETE e PUT. Não tente ler JSON da resposta: o corpo é vazio."],
          [206, "Partial Content", "Só um pedaço do arquivo foi enviado (Range).", "Normal em vídeos e downloads que podem ser retomados."],
          [301, "Moved Permanently", "O endereço mudou de vez. Navegadores e o Google passam a usar o novo.", "Use para trocar de domínio ou de URL; o Google transfere a relevância. Fica guardado no navegador: cuidado ao testar."],
          [302, "Found", "Redirecionamento temporário: o endereço original continua valendo.", "Comum depois de login ou de enviar formulário. Para mudança definitiva, use 301."],
          [303, "See Other", "Depois de um POST, busque o resultado em outro endereço com GET.", "Padrão para evitar reenvio do formulário ao atualizar a página."],
          [304, "Not Modified", "O conteúdo não mudou; o navegador usa a cópia em cache.", "Bom sinal de cache funcionando (ETag / Last-Modified)."],
          [307, "Temporary Redirect", "Como o 302, mas mantém o método (um POST continua POST).", "Use em APIs quando o método não pode virar GET."],
          [308, "Permanent Redirect", "Como o 301, mas mantém o método.", "Redirecionamento definitivo em APIs."],
          [400, "Bad Request", "O pedido está malformado: JSON inválido, campo faltando ou formato errado.", "Confira o corpo enviado, o Content-Type e os parâmetros. A resposta costuma dizer qual campo está errado."],
          [401, "Unauthorized", "Falta login ou o token é inválido ou expirou.", "Veja o cabeçalho Authorization, a validade do token e se o cookie de sessão foi enviado."],
          [402, "Payment Required", "Reservado para pagamento; algumas APIs usam quando o plano acabou.", "Confira a assinatura ou os créditos do serviço."],
          [403, "Forbidden", "O servidor entendeu quem você é, mas não deixa acessar.", "Permissão do usuário, regra de firewall/WAF, bloqueio por IP ou país, ou permissões de arquivo no servidor."],
          [404, "Not Found", "O endereço não existe no servidor.", "Erro de digitação na URL, rota não criada, arquivo apagado ou link antigo. Em SPA, falta configurar o fallback para o index.html."],
          [405, "Method Not Allowed", "O endereço existe, mas não aceita esse método (ex.: POST onde só há GET).", "Confira o método da chamada e as rotas do servidor. O cabeçalho Allow lista os aceitos."],
          [406, "Not Acceptable", "O servidor não tem o formato pedido no cabeçalho Accept.", "Ajuste o Accept (ex.: application/json)."],
          [408, "Request Timeout", "O cliente demorou demais para enviar o pedido.", "Conexão lenta ou upload grande; tente de novo."],
          [409, "Conflict", "O pedido conflita com o estado atual (ex.: cadastro duplicado, versão desatualizada).", "Recarregue os dados e tente de novo; confira campos únicos."],
          [410, "Gone", "O conteúdo foi removido de propósito e não volta.", "Diferente do 404: avisa o Google para tirar a página do índice mais rápido."],
          [411, "Length Required", "Falta o cabeçalho Content-Length.", "Envie o tamanho do corpo do pedido."],
          [413, "Payload Too Large", "O corpo do pedido é maior que o limite do servidor.", "Arquivo grande demais: aumente upload_max_filesize/post_max_size (PHP) ou client_max_body_size (Nginx)."],
          [414, "URI Too Long", "O endereço é longo demais.", "Mande os dados no corpo (POST) em vez de na URL."],
          [415, "Unsupported Media Type", "O formato do corpo não é aceito.", "Confira o Content-Type (ex.: application/json) e se o corpo bate com ele."],
          [418, "I'm a teapot", "Piada de 1º de abril (RFC 2324): \"sou um bule de chá\".", "Alguns servidores usam para recusar robôs."],
          [422, "Unprocessable Content", "O formato está certo, mas os dados não passam na validação.", "Leia a mensagem de erro: ela indica quais campos estão inválidos."],
          [425, "Too Early", "O servidor não quer processar um pedido que pode ser repetido.", "Raro; ligado ao TLS 1.3 com dados antecipados."],
          [428, "Precondition Required", "O servidor exige um cabeçalho de condição (ex.: If-Match).", "Envie a versão (ETag) do recurso que você está alterando."],
          [429, "Too Many Requests", "Pedidos demais em pouco tempo (limite de uso).", "Espere o tempo do cabeçalho Retry-After, diminua a frequência e use cache."],
          [431, "Request Header Fields Too Large", "Cabeçalhos grandes demais.", "Normalmente cookies acumulados: apague os cookies do site."],
          [451, "Unavailable For Legal Reasons", "Bloqueado por motivo legal (ordem judicial, censura).", "Não há ação técnica."],
          [500, "Internal Server Error", "Erro genérico: o código do servidor quebrou.", "Olhe o log de erros do servidor (PHP error_log, logs da aplicação). Causas comuns: exceção não tratada, erro de sintaxe, .htaccess inválido."],
          [501, "Not Implemented", "O servidor não suporta a função pedida.", "Método HTTP não reconhecido pelo servidor."],
          [502, "Bad Gateway", "O servidor intermediário (proxy, CDN) recebeu uma resposta inválida do servidor de origem.", "A aplicação caiu ou travou (PHP-FPM, Node), ou fechou a conexão. Veja se o serviço de origem está rodando e o log dele."],
          [503, "Service Unavailable", "Servidor indisponível: sobrecarga ou manutenção.", "Temporário: confira o uso de CPU/memória, limites da hospedagem e se há manutenção. Pode vir com Retry-After."],
          [504, "Gateway Timeout", "O intermediário cansou de esperar a resposta da origem.", "A página demora demais: consultas lentas no banco, API externa travada ou limite de tempo do proxy baixo."],
          [505, "HTTP Version Not Supported", "O servidor não suporta a versão do HTTP usada.", "Raro; confira a configuração do cliente."],
          [507, "Insufficient Storage", "O servidor ficou sem espaço para concluir.", "Disco cheio: libere espaço."],
          [508, "Loop Detected", "O servidor encontrou um laço infinito ao processar.", "Também usado por hospedagens quando o site passa do limite de recursos."],
          [520, "Web Server Returned an Unknown Error (Cloudflare)", "A origem devolveu algo vazio ou inesperado ao Cloudflare.", "O servidor fechou a conexão ou mandou resposta inválida: veja os logs da origem e cabeçalhos grandes demais."],
          [521, "Web Server Is Down (Cloudflare)", "O servidor de origem recusou a conexão do Cloudflare.", "Servidor desligado ou firewall bloqueando os IPs do Cloudflare."],
          [522, "Connection Timed Out (Cloudflare)", "O Cloudflare não conseguiu conectar na origem a tempo.", "Servidor sobrecarregado, firewall ou IP errado no DNS."],
          [523, "Origin Is Unreachable (Cloudflare)", "O Cloudflare não acha o caminho até a origem.", "Confira o IP do registro DNS no Cloudflare."],
          [524, "A Timeout Occurred (Cloudflare)", "A origem conectou, mas demorou mais de 100 segundos para responder.", "Processo lento demais: rode em segundo plano ou otimize a consulta."],
          [525, "SSL Handshake Failed (Cloudflare)", "Falhou a conexão segura entre o Cloudflare e a origem.", "Certificado SSL do servidor ausente ou mal configurado (modo Full)."],
          [526, "Invalid SSL Certificate (Cloudflare)", "O certificado da origem é inválido (modo Full strict).", "Renove o certificado ou use um certificado de origem do Cloudflare."],
        ];
        let family = "all";
        const searchInput = document.getElementById("http-search");

        function calculate() {
          const words = normalizeText(searchInput.value).split(/\s+/).filter(Boolean);
          const resultBox = document.getElementById("http-result");
          // Código exato (ex.: "404"): mostra só ele, mesmo que outros textos citem o número
          const exactMatch = CODES.filter(([code]) => String(code) === searchInput.value.trim());
          const matches = exactMatch.length ? exactMatch : CODES.filter(([code, name, meaning, tip]) => {
            if (family !== "all" && String(code)[0] !== family) {
              return false;
            }
            const haystack = normalizeText(`${code} ${name} ${meaning} ${tip} ${FAMILIES[String(code)[0]][0]}`);
            return words.every((word) => haystack.includes(word));
          });
          if (matches.length === 0) {
            resultBox.innerHTML = emptyDisplay("Códigos HTTP", "Nenhum código encontrado com essa busca.");
            return;
          }
          // Busca por um código exato: já abre a explicação dele
          const exactCode = /^\d{3}$/.test(searchInput.value.trim());
          resultBox.innerHTML = `<div class="http-list">${matches.map(([code, name, meaning, tip]) => {
            const familyDigit = String(code)[0];
            return `<details class="http-code http-${familyDigit}xx"${exactCode || matches.length === 1 ? " open" : ""}>
              <summary><b>${code}</b><span>${escapeHtml(name)}</span><small>${FAMILIES[familyDigit][0]}</small></summary>
              <p>${escapeHtml(meaning)}</p>
              <p class="http-tip"><b>O que verificar:</b> ${escapeHtml(tip)}</p>
            </details>`;
          }).join("")}</div>`;
        }
        setupSegmented("http-family", (value) => {
          family = value;
          calculate();
        });
        onInputs(["http-search"], calculate);
      },
    },

    "json-formatter": {
      html: `        <div class="calculator-body">
          <div class="field"><label for="json-input">JSON</label><textarea id="json-input" spellcheck="false" rows="10" class="mono-input">{"usuario":{"id":42,"nome":"Ana","ativo":true,"tags":["admin","dev"]},"pedidos":[{"id":1,"total":99.9},{"id":2,"total":150}]}</textarea></div>
          <div class="field"><label for="json-file">Ou abra um arquivo .json (até 50 MB)</label><input id="json-file" type="file" accept=".json,application/json,text/plain"><span class="field-hint" id="json-file-info">O arquivo é lido no seu navegador e não é enviado para lugar nenhum.</span></div>
          <div class="field-row">
            <div class="field"><label for="json-indent">Indentação</label><select id="json-indent"><option value="2">2 espaços</option><option value="4">4 espaços</option><option value="tab">Tab</option><option value="0">Minificar (tudo em uma linha)</option></select></div>
          </div>
          <label class="check"><input type="checkbox" id="json-sort"> Ordenar as chaves em ordem alfabética</label>
        </div>
        <div id="json-result"></div>`,
      setup() {
        // Ordena as chaves de todos os objetos, em qualquer profundidade
        function sortKeys(value) {
          if (Array.isArray(value)) {
            return value.map(sortKeys);
          }
          if (value && typeof value === "object") {
            const sorted = {};
            Object.keys(value).sort().forEach((key) => { sorted[key] = sortKeys(value[key]); });
            return sorted;
          }
          return value;
        }
        function countStats(value, depth = 1) {
          if (Array.isArray(value)) {
            return value.reduce((stats, item) => {
              const itemStats = countStats(item, depth + 1);
              return { keys: stats.keys + itemStats.keys, depth: Math.max(stats.depth, itemStats.depth) };
            }, { keys: 0, depth });
          }
          if (value && typeof value === "object") {
            return Object.values(value).reduce((stats, item) => {
              const itemStats = countStats(item, depth + 1);
              return { keys: stats.keys + itemStats.keys, depth: Math.max(stats.depth, itemStats.depth) };
            }, { keys: Object.keys(value).length, depth });
          }
          return { keys: 0, depth: depth - 1 };
        }
        // Mostra a linha e a coluna do erro, a partir da posição que o navegador informa
        function errorLocation(text, errorMessage) {
          const positionMatch = errorMessage.match(/position (\d+)/i);
          if (!positionMatch) {
            const lineMatch = errorMessage.match(/line (\d+) column (\d+)/i);
            return lineMatch ? { line: Number(lineMatch[1]), column: Number(lineMatch[2]) } : null;
          }
          const before = text.slice(0, Number(positionMatch[1]));
          const lines = before.split("\n");
          return { line: lines.length, column: lines[lines.length - 1].length + 1 };
        }
        // Arquivos grandes: o texto fica guardado aqui (não na caixa de texto, que travaria a página)
        const MAX_FILE_BYTES = 50 * 1024 * 1024;
        const TEXTAREA_LIMIT = 1024 * 1024; // acima de 1 MB o arquivo não é colocado na caixa
        const PREVIEW_LIMIT = 300000; // mostra só o começo de resultados muito grandes
        const jsonInput = document.getElementById("json-input");
        let fileText = null;
        let fileName = "";
        let lastOutput = "";

        function sourceText() {
          return fileText ?? jsonInput.value;
        }
        function downloadOutput() {
          const blob = new Blob([lastOutput], { type: "application/json" });
          const link = document.createElement("a");
          link.href = URL.createObjectURL(blob);
          link.download = fileName ? fileName.replace(/(\.json)?$/i, "-formatado.json") : "formatado.json";
          link.click();
          setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        }
        async function copyOutput(button) {
          try {
            await navigator.clipboard.writeText(lastOutput);
            button.textContent = "copiado!";
          } catch {
            button.textContent = "não foi possível copiar: use baixar";
          }
        }

        function calculate() {
          const text = sourceText();
          const resultBox = document.getElementById("json-result");
          if (text.trim() === "") {
            resultBox.innerHTML = emptyDisplay("JSON", "Cole um JSON ou abra um arquivo.");
            return;
          }
          let parsed;
          try {
            parsed = JSON.parse(text);
          } catch (parseError) {
            const location = errorLocation(text, parseError.message);
            const where = location ? `Erro na linha ${location.line}, coluna ${location.column}.` : "";
            const errorLine = location ? text.split("\n")[location.line - 1] : "";
            const pointer = location ? `<pre class="display-text jwt-json">${escapeHtml(errorLine.slice(Math.max(0, location.column - 40), location.column + 40))}\n${" ".repeat(Math.min(location.column - 1, 39))}^</pre>` : "";
            resultBox.innerHTML = `<div class="display"><span class="display-label">JSON inválido</span><span class="display-detail">${escapeHtml(where)} ${escapeHtml(parseError.message)}</span>${pointer}<span class="display-detail">Erros comuns: vírgula sobrando no fim, aspas simples em vez de duplas, chave sem aspas, comentários.</span></div>`;
            return;
          }
          const indentChoice = document.getElementById("json-indent").value;
          const indent = indentChoice === "tab" ? "\t" : Number(indentChoice);
          const value = document.getElementById("json-sort").checked ? sortKeys(parsed) : parsed;
          const output = JSON.stringify(value, null, indent);
          lastOutput = output;
          const stats = countStats(parsed);
          const bytes = new TextEncoder().encode(output).length;
          const isTruncated = output.length > PREVIEW_LIMIT;
          const preview = isTruncated ? `${output.slice(0, PREVIEW_LIMIT)}\n\n… (resultado grande: aqui aparece só o começo. Use "copiar tudo" ou "baixar")` : output;
          resultBox.innerHTML = `<div class="display"><span class="display-label">JSON válido · ${formatSize(bytes)} · ${formatNumber(stats.keys)} chaves · ${formatNumber(stats.depth)} níveis</span><pre class="display-text jwt-json">${escapeHtml(preview)}</pre>
            <div class="button-row"><button class="copy-button" type="button" id="json-copy">copiar tudo</button><button class="copy-button" type="button" id="json-download">baixar .json</button></div></div>`;
          document.getElementById("json-copy").addEventListener("click", (clickEvent) => copyOutput(clickEvent.currentTarget));
          document.getElementById("json-download").addEventListener("click", downloadOutput);
        }
        function formatSize(bytes) {
          if (bytes < 1024) {
            return `${formatNumber(bytes)} bytes`;
          }
          return bytes < 1024 * 1024 ? `${formatNumber(bytes / 1024, 1)} KB` : `${formatNumber(bytes / 1024 / 1024, 1)} MB`;
        }

        // Com textos grandes, espera a pessoa parar de digitar antes de formatar
        let typingTimer = null;
        function scheduleCalculate() {
          clearTimeout(typingTimer);
          typingTimer = setTimeout(calculate, sourceText().length > 100000 ? 500 : 0);
        }
        jsonInput.addEventListener("input", () => {
          // Editou a caixa: deixa de usar o arquivo grande carregado
          if (fileText !== null) {
            fileText = null;
            fileName = "";
            document.getElementById("json-file-info").textContent = "";
          }
          scheduleCalculate();
        });

        document.getElementById("json-file").addEventListener("change", (changeEvent) => {
          const file = changeEvent.target.files[0];
          const fileInfo = document.getElementById("json-file-info");
          if (!file) {
            return;
          }
          if (file.size > MAX_FILE_BYTES) {
            fileInfo.textContent = `Arquivo de ${formatSize(file.size)}: o limite é 50 MB.`;
            return;
          }
          fileInfo.textContent = "Lendo o arquivo…";
          const reader = new FileReader();
          reader.onload = () => {
            const text = String(reader.result);
            fileName = file.name;
            if (text.length <= TEXTAREA_LIMIT) {
              fileText = null;
              jsonInput.value = text;
              fileInfo.textContent = `${file.name} (${formatSize(file.size)}) carregado.`;
            } else {
              fileText = text;
              jsonInput.value = "";
              jsonInput.placeholder = `${file.name} carregado (${formatSize(file.size)}). Digite aqui para colar outro JSON.`;
              fileInfo.textContent = `${file.name} (${formatSize(file.size)}) carregado. Por ser grande, ele não aparece na caixa de texto.`;
            }
            calculate();
          };
          reader.readAsText(file);
        });
        onInputs(["json-indent", "json-sort"], calculate);
      },
    },

    "caracteres": {
      html: `        <div class="calculator-body">
          <div class="field">
            <label for="text-input">Seu texto</label>
            <textarea id="text-input">A conta que você precisa, feita na hora. Cole aqui a sua redação, legenda ou post e veja todos os números.

Funciona com textos de qualquer tamanho.</textarea>
          </div>
        </div>
        <div id="text-result"></div>`,
      setup() {
        const WORDS_PER_MINUTE = 200;
        function calculate() {
          const text = document.getElementById("text-input").value;
          const words = text.trim() === "" ? 0 : text.trim().split(/\s+/).length;
          const sentences = (text.match(/[^.!?]+[.!?]+/g) || []).length;
          const paragraphs = text.split(/\n\s*\n/).filter((paragraph) => paragraph.trim() !== "").length;
          document.getElementById("text-result").innerHTML = display("Caracteres", formatNumber(text.length, 0), "", [
            ["Sem espaços", formatNumber(text.replace(/\s/g, "").length, 0)],
            ["Palavras", formatNumber(words, 0)],
            ["Frases", sentences],
            ["Parágrafos", paragraphs],
            ["Leitura", words === 0 ? "—" : `~${Math.max(1, Math.ceil(words / WORDS_PER_MINUTE))} min`],
          ]);
        }
        onInputs(["text-input"], calculate);
      },
    },

    "maiusculas": {
      html: `        <div class="calculator-body">
          <div class="field"><label for="case-input">Texto</label><textarea id="case-input">bem-vindo ao vibe2000. aqui você converte textos em um clique.</textarea></div>
          <div class="segmented" id="case-modes" role="group" aria-label="Conversão">
            <button type="button" data-value="upper" aria-pressed="true">MAIÚSCULAS</button>
            <button type="button" data-value="lower" aria-pressed="false">minúsculas</button>
            <button type="button" data-value="title" aria-pressed="false">Cada Palavra</button>
            <button type="button" data-value="sentence" aria-pressed="false">Início de frase</button>
            <button type="button" data-value="heading" aria-pressed="false">Título (de, da, e em minúsculas)</button>
          </div>
        </div>
        <div id="case-result"></div>`,
      setup() {
        let mode = "upper";
        // Palavras que ficam em minúsculas no meio de títulos em português
        const SMALL_WORDS = new Set(["a", "à", "as", "às", "o", "os", "ao", "aos", "e", "é", "de", "da", "das", "do", "dos", "em", "na", "nas", "no", "nos", "num", "numa", "pelo", "pela", "pelos", "pelas", "por", "para", "pra", "com", "sem", "um", "uma", "ou", "que"]);
        function capitalize(word) {
          return word.charAt(0).toLocaleUpperCase("pt-BR") + word.slice(1);
        }
        const converters = {
          heading: (text) => text.toLocaleLowerCase("pt-BR").split("\n").map((line) => {
            let isFirstWord = true;
            return line.replace(/\S+/g, (word) => {
              const keepSmall = !isFirstWord && SMALL_WORDS.has(word);
              isFirstWord = false;
              return keepSmall ? word : capitalize(word);
            });
          }).join("\n"),
          upper: (text) => text.toLocaleUpperCase("pt-BR"),
          lower: (text) => text.toLocaleLowerCase("pt-BR"),
          title: (text) => text.toLocaleLowerCase("pt-BR").replace(/(^|\s)(\S)/g, (match, space, letter) => space + letter.toLocaleUpperCase("pt-BR")),
          sentence: (text) => text.toLocaleLowerCase("pt-BR").replace(/(^\s*|[.!?]\s+)(\S)/g, (match, start, letter) => start + letter.toLocaleUpperCase("pt-BR")),
        };
        function calculate() {
          const converted = converters[mode](document.getElementById("case-input").value);
          // escapeHtml: o texto da pessoa nunca vira HTML
          document.getElementById("case-result").innerHTML = `<div class="display"><span class="display-label">Resultado</span><span class="display-text" id="case-output">${escapeHtml(converted)}</span><button class="copy-button" type="button" data-copy-target="case-output">copiar</button></div>`;
        }
        setupSegmented("case-modes", (newMode) => { mode = newMode; calculate(); });
        onInputs(["case-input"], calculate);
      },
    },

    "link-whatsapp": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="whatsapp-country">Código do país</label><input id="whatsapp-country" inputmode="numeric" value="55"><span class="field-hint">Brasil = 55 · Portugal = 351 · EUA = 1</span></div>
            <div class="field"><label for="whatsapp-phone">Número com DDD</label><input id="whatsapp-phone" type="tel" inputmode="numeric" autocomplete="off" placeholder="(11) 91234-5678"></div>
          </div>
          <div class="field"><label for="whatsapp-message">Mensagem pronta (opcional)</label><textarea id="whatsapp-message" rows="3">Olá! Vi seu anúncio e gostaria de mais informações.</textarea><span class="field-hint" id="whatsapp-counter"></span></div>
        </div>
        <div id="whatsapp-result"></div>`,
      setup() {
        const phoneInput = document.getElementById("whatsapp-phone");
        const resultBox = document.getElementById("whatsapp-result");
        let qrLibraryPromise = null;
        let renderNumber = 0; // evita que um QR Code antigo apareça depois de um novo

        // A biblioteca de QR Code (public/assets/js/vendor/qrcode.js, licença MIT) só carrega nesta página
        function loadQrLibrary() {
          if (!qrLibraryPromise) {
            qrLibraryPromise = new Promise((resolve, reject) => {
              if (window.qrcode) {
                resolve(window.qrcode);
                return;
              }
              const script = document.createElement("script");
              script.src = "/assets/js/vendor/qrcode.js";
              script.onload = () => resolve(window.qrcode);
              script.onerror = reject;
              document.head.appendChild(script);
            });
          }
          return qrLibraryPromise;
        }

        // Máscara para números do Brasil: (11) 91234-5678 ou (11) 1234-5678
        function maskBrazilianPhone(digits) {
          const clean = digits.slice(0, 11);
          if (clean.length <= 2) return clean.length ? `(${clean}` : "";
          if (clean.length <= 6) return `(${clean.slice(0, 2)}) ${clean.slice(2)}`;
          if (clean.length <= 10) return `(${clean.slice(0, 2)}) ${clean.slice(2, 6)}-${clean.slice(6)}`;
          return `(${clean.slice(0, 2)}) ${clean.slice(2, 7)}-${clean.slice(7)}`;
        }

        // Desenha o QR Code (lista de módulos claros/escuros) em SVG e em PNG
        function qrMatrix(qrcodeFactory, text) {
          const qr = qrcodeFactory(0, "M");
          qr.addData(text);
          qr.make();
          const size = qr.getModuleCount();
          return { size, isDark: (row, column) => qr.isDark(row, column) };
        }
        function qrSvg(matrix, margin = 4) {
          const total = matrix.size + margin * 2;
          let path = "";
          for (let row = 0; row < matrix.size; row++) {
            for (let column = 0; column < matrix.size; column++) {
              if (matrix.isDark(row, column)) {
                path += `M${column + margin} ${row + margin}h1v1h-1z`;
              }
            }
          }
          return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${total}" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#ffffff"/><path d="${path}" fill="#000000"/></svg>`;
        }
        function qrPngDataUrl(matrix, pixelsPerModule = 12, margin = 4) {
          const total = (matrix.size + margin * 2) * pixelsPerModule;
          const canvas = document.createElement("canvas");
          canvas.width = total;
          canvas.height = total;
          const context = canvas.getContext("2d");
          context.fillStyle = "#ffffff";
          context.fillRect(0, 0, total, total);
          context.fillStyle = "#000000";
          for (let row = 0; row < matrix.size; row++) {
            for (let column = 0; column < matrix.size; column++) {
              if (matrix.isDark(row, column)) {
                context.fillRect((column + margin) * pixelsPerModule, (row + margin) * pixelsPerModule, pixelsPerModule, pixelsPerModule);
              }
            }
          }
          return canvas.toDataURL("image/png");
        }

        async function calculate() {
          const countryCode = document.getElementById("whatsapp-country").value.replace(/\D/g, "");
          const isBrazil = countryCode === "55";
          const phoneDigits = phoneInput.value.replace(/\D/g, "");
          if (isBrazil) {
            phoneInput.value = maskBrazilianPhone(phoneDigits);
          }
          const message = document.getElementById("whatsapp-message").value;
          document.getElementById("whatsapp-counter").textContent = `${formatNumber(Array.from(message).length)} caracteres`;

          if (!countryCode || phoneDigits.length < 8) {
            resultBox.innerHTML = emptyDisplay("Link do WhatsApp", "Informe o número com DDD.");
            return;
          }
          if (isBrazil && (phoneDigits.length < 10 || Number(phoneDigits.slice(0, 2)) < 11)) {
            resultBox.innerHTML = emptyDisplay("Link do WhatsApp", "Confira o número: no Brasil são 2 dígitos de DDD e mais 8 ou 9 do telefone.");
            return;
          }
          // Formato oficial do WhatsApp: wa.me/<país+número só com dígitos>?text=<mensagem codificada>
          const link = `https://wa.me/${countryCode}${phoneDigits}${message.trim() ? `?text=${encodeURIComponent(message)}` : ""}`;
          const warning = isBrazil && phoneDigits.length === 11 && phoneDigits[2] !== "9"
            ? "<span class=\"display-detail\">Atenção: celulares no Brasil começam com 9 depois do DDD.</span>"
            : "";
          resultBox.innerHTML = `<div class="display"><span class="display-label">Seu link do WhatsApp</span><span class="display-text base64-output" id="whatsapp-link">${escapeHtml(link)}</span>${warning}
              <div class="button-row"><button class="copy-button" type="button" data-copy-target="whatsapp-link">copiar link</button><a class="copy-button" href="${escapeHtml(link)}" target="_blank" rel="noopener">testar no WhatsApp</a></div></div>
            <div class="calculator-body whatsapp-qr"><h3>QR Code</h3><div id="whatsapp-qr-image" class="whatsapp-qr-image">gerando…</div><div class="button-row" id="whatsapp-qr-buttons"></div>
              <p class="notice">Quem apontar a câmera do celular para o QR Code abre a conversa com a mensagem já escrita. O link wa.me é o formato curto oficial do WhatsApp e não expira. Não usamos encurtadores de terceiros, que podem sair do ar ou mostrar anúncios.</p></div>`;
          const thisRender = ++renderNumber;
          try {
            const qrcodeFactory = await loadQrLibrary();
            if (thisRender !== renderNumber) {
              return;
            }
            const matrix = qrMatrix(qrcodeFactory, link);
            const svg = qrSvg(matrix);
            document.getElementById("whatsapp-qr-image").innerHTML = svg;
            const svgDataUrl = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
            document.getElementById("whatsapp-qr-buttons").innerHTML = `<a class="action-button" href="${qrPngDataUrl(matrix)}" download="qrcode-whatsapp.png">baixar PNG</a><a class="secondary-button" href="${svgDataUrl}" download="qrcode-whatsapp.svg">baixar SVG (para gráfica)</a>`;
          } catch {
            document.getElementById("whatsapp-qr-image").textContent = "Não foi possível gerar o QR Code agora. O link acima funciona normalmente.";
          }
        }
        onInputs(["whatsapp-country", "whatsapp-phone", "whatsapp-message"], calculate);
      },
    },

    "senha": {
      html: `        <div class="calculator-body">
          <div class="field"><label for="password-length">Tamanho: <b id="password-length-value">16</b> caracteres</label><input id="password-length" type="range" min="8" max="40" value="16"></div>
          <div class="field-row">
            <label class="check"><input type="checkbox" id="password-upper" checked> Letras maiúsculas</label>
            <label class="check"><input type="checkbox" id="password-lower" checked> Letras minúsculas</label>
            <label class="check"><input type="checkbox" id="password-numbers" checked> Números</label>
            <label class="check"><input type="checkbox" id="password-symbols" checked> Símbolos</label>
          </div>
          <button class="action-button" type="button" id="password-generate">gerar outra senha</button>
        </div>
        <div id="password-result"></div>`,
      setup() {
        const characterSets = {
          "password-upper": "ABCDEFGHJKLMNPQRSTUVWXYZ",
          "password-lower": "abcdefghijkmnopqrstuvwxyz",
          "password-numbers": "23456789",
          "password-symbols": "!@#$%&*?-_+=",
        };
        function generate() {
          const length = Number(document.getElementById("password-length").value);
          document.getElementById("password-length-value").textContent = length;
          const allowedCharacters = Object.entries(characterSets).filter(([checkboxId]) => document.getElementById(checkboxId).checked).map(([, characters]) => characters).join("");
          const resultBox = document.getElementById("password-result");
          if (allowedCharacters === "") {
            resultBox.innerHTML = emptyDisplay("Senha", "Marque pelo menos um tipo de caractere.");
            return;
          }
          // crypto.getRandomValues gera números realmente imprevisíveis (Math.random não serve para senhas)
          const randomValues = new Uint32Array(length);
          crypto.getRandomValues(randomValues);
          const password = Array.from(randomValues, (value) => allowedCharacters[value % allowedCharacters.length]).join("");
          const strength = length >= 16 && allowedCharacters.length > 60 ? "muito forte" : length >= 12 ? "forte" : "média";
          resultBox.innerHTML = `<div class="display"><span class="display-label">Senha (${strength})</span><span class="display-value" id="password-output">${escapeHtml(password)}</span><button class="copy-button" type="button" data-copy-target="password-output">copiar</button></div>`;
        }
        document.getElementById("password-generate").addEventListener("click", generate);
        onInputs(["password-length", "password-upper", "password-lower", "password-numbers", "password-symbols"], generate);
      },
    },

    "media-ponderada": {
      html: `        <div class="calculator-body">
          <div id="weighted-rows" class="weighted-rows"></div>
          <div class="button-row">
            <button type="button" class="secondary-button" id="weighted-add">+ adicionar nota</button>
            <button type="button" class="secondary-button" id="weighted-remove">− remover a última</button>
          </div>
          <div class="field-row">
            <div class="field"><label for="weighted-target">Média para passar (opcional)</label><input id="weighted-target" inputmode="decimal" value="7"></div>
            <div class="field"><label for="weighted-next-weight">Peso da prova que falta (opcional)</label><input id="weighted-next-weight" inputmode="decimal" value=""></div>
          </div>
          <span class="field-hint">Preencha o peso da prova que ainda vai fazer para saber a nota mínima que precisa tirar.</span>
        </div>
        <div id="weighted-result"></div>`,
      setup() {
        const MIN_ROWS = 2;
        const MAX_ROWS = 20;
        const EXAMPLE = [["7,5", "2"], ["6", "3"], ["8", "5"]];
        const rowsBox = document.getElementById("weighted-rows");

        function addRow(value = "", weight = "1") {
          const number = rowsBox.children.length + 1;
          rowsBox.insertAdjacentHTML("beforeend", `<div class="field-row">
            <div class="field"><label for="weighted-value-${number}">Nota ou valor ${number}</label><input id="weighted-value-${number}" inputmode="decimal" value="${value}"></div>
            <div class="field"><label for="weighted-weight-${number}">Peso ${number}</label><input id="weighted-weight-${number}" inputmode="decimal" value="${weight}"></div>
          </div>`);
        }

        // Link compartilhado com mais linhas que o exemplo: cria as linhas antes de preencher
        let sharedRows = 0;
        new URLSearchParams(location.search).forEach((value, key) => {
          const match = key.match(/^weighted-(?:value|weight)-(\d+)$/);
          if (match) {
            sharedRows = Math.max(sharedRows, Number(match[1]));
          }
        });
        EXAMPLE.forEach(([value, weight]) => addRow(value, weight));
        while (rowsBox.children.length < Math.min(sharedRows, MAX_ROWS)) {
          addRow();
        }

        function calculate() {
          const resultBox = document.getElementById("weighted-result");
          let weightedSum = 0;
          let weightSum = 0;
          let simpleSum = 0;
          let count = 0;
          for (let number = 1; number <= rowsBox.children.length; number++) {
            const value = parseNumber(document.getElementById(`weighted-value-${number}`).value);
            const weight = parseNumber(document.getElementById(`weighted-weight-${number}`).value);
            if (Number.isNaN(value) || Number.isNaN(weight) || weight < 0) {
              continue;
            }
            weightedSum += value * weight;
            weightSum += weight;
            simpleSum += value;
            count++;
          }
          if (weightSum <= 0) {
            resultBox.innerHTML = emptyDisplay("Média ponderada", "Preencha pelo menos uma nota com peso maior que zero.");
            return;
          }

          const average = weightedSum / weightSum;
          const target = parseNumber(document.getElementById("weighted-target").value);
          const nextWeight = parseNumber(document.getElementById("weighted-next-weight").value);
          const gridItems = [
            ["Média simples", formatNumber(simpleSum / count, 2)],
            ["Soma dos pesos", formatNumber(weightSum, 2)],
            ["Soma de nota × peso", formatNumber(weightedSum, 2)],
          ];
          let detail = `${count} ${count === 1 ? "nota" : "notas"}`;
          if (!Number.isNaN(target)) {
            detail += average >= target ? ` · acima da média ${formatNumber(target, 2)} ✔` : ` · abaixo da média ${formatNumber(target, 2)}`;
          }
          if (!Number.isNaN(target) && nextWeight > 0) {
            // (soma atual + nota × peso novo) ÷ (pesos + peso novo) = média desejada
            const neededGrade = (target * (weightSum + nextWeight) - weightedSum) / nextWeight;
            gridItems.unshift(["Nota mínima na próxima prova", neededGrade <= 0 ? "já passou" : formatNumber(neededGrade, 2)]);
          }
          resultBox.innerHTML = display("Média ponderada", formatNumber(average, 2), detail, gridItems);
        }

        document.getElementById("weighted-add").addEventListener("click", () => {
          if (rowsBox.children.length < MAX_ROWS) {
            addRow();
            calculate();
          }
        });
        document.getElementById("weighted-remove").addEventListener("click", () => {
          if (rowsBox.children.length > MIN_ROWS) {
            rowsBox.lastElementChild.remove();
            calculate();
          }
        });
        rowsBox.addEventListener("input", calculate);
        onInputs(["weighted-target", "weighted-next-weight"], calculate);
      },
    },

    "dias-ate-data": {
      html: `        <div class="calculator-body">
          <div class="button-row" id="countdown-presets">
            <button type="button" class="secondary-button" data-preset="natal">Natal</button>
            <button type="button" class="secondary-button" data-preset="ano-novo">Ano-novo</button>
            <button type="button" class="secondary-button" data-preset="carnaval">Carnaval</button>
            <button type="button" class="secondary-button" data-preset="maes">Dia das Mães</button>
            <button type="button" class="secondary-button" data-preset="pais">Dia dos Pais</button>
            <button type="button" class="secondary-button" data-preset="black-friday">Black Friday</button>
          </div>
          <div class="field-row">
            <div class="field"><label for="countdown-date">Data</label><input id="countdown-date" type="date"></div>
            <div class="field"><label for="countdown-name">Nome do evento (opcional)</label><input id="countdown-name" maxlength="40" placeholder="ex.: minhas férias"></div>
          </div>
        </div>
        <div id="countdown-result"></div>`,
      setup() {
        const dateInput = document.getElementById("countdown-date");
        const nameInput = document.getElementById("countdown-name");
        const resultBox = document.getElementById("countdown-result");
        let liveTimer = null;

        const toIso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
        // N-ésimo dia da semana do mês (ex.: 2º domingo de maio)
        function nthWeekday(year, month, weekday, nth) {
          const first = new Date(year, month, 1, 12);
          const offset = (weekday - first.getDay() + 7) % 7;
          return new Date(year, month, 1 + offset + (nth - 1) * 7, 12);
        }
        const PRESETS = {
          natal: { name: "o Natal", dateOf: (year) => new Date(year, 11, 25, 12) },
          "ano-novo": { name: "o Ano-novo", dateOf: (year) => new Date(year, 0, 1, 12) },
          // Sábado de Carnaval (50 dias antes da Páscoa)
          carnaval: { name: "o Carnaval", dateOf: (year) => new Date(easterSunday(year).getTime() - 50 * MILLISECONDS_PER_DAY) },
          maes: { name: "o Dia das Mães", dateOf: (year) => nthWeekday(year, 4, 0, 2) },
          pais: { name: "o Dia dos Pais", dateOf: (year) => nthWeekday(year, 7, 0, 2) },
          // Sexta-feira depois da 4ª quinta-feira de novembro (Ação de Graças nos EUA)
          "black-friday": { name: "a Black Friday", dateOf: (year) => new Date(nthWeekday(year, 10, 4, 4).getTime() + MILLISECONDS_PER_DAY) },
        };
        // Próxima ocorrência a partir de hoje
        function nextOccurrence(preset) {
          const today = toIso(new Date());
          const year = new Date().getFullYear();
          const thisYear = toIso(preset.dateOf(year));
          return thisYear >= today ? thisYear : toIso(preset.dateOf(year + 1));
        }

        // Meses completos + dias restantes entre duas datas
        function monthsAndDays(start, end) {
          let months = (end.getFullYear() - start.getFullYear()) * 12 + end.getMonth() - start.getMonth();
          if (end.getDate() < start.getDate()) {
            months--;
          }
          const afterMonths = new Date(start.getFullYear(), start.getMonth() + months, 1, 12);
          const lastDay = new Date(afterMonths.getFullYear(), afterMonths.getMonth() + 1, 0).getDate();
          afterMonths.setDate(Math.min(start.getDate(), lastDay));
          return { months, days: Math.round((end - afterMonths) / MILLISECONDS_PER_DAY) };
        }

        // De amanhã até a data: dias úteis (segunda a sexta, sem feriados nacionais) e fins de semana (sábados)
        function countDaysUntil(start, end) {
          const holidaysByYear = new Map();
          let businessDays = 0;
          let weekends = 0;
          for (let date = new Date(start.getTime() + MILLISECONDS_PER_DAY); date <= end; date = new Date(date.getTime() + MILLISECONDS_PER_DAY)) {
            const year = date.getFullYear();
            if (!holidaysByYear.has(year)) {
              holidaysByYear.set(year, holidaysOfYear(year, false));
            }
            const weekday = date.getDay();
            if (weekday === 6) {
              weekends++;
            } else if (weekday !== 0 && !holidaysByYear.get(year).has(toIso(date))) {
              businessDays++;
            }
          }
          return { businessDays, weekends };
        }

        function plural(amount, singular, pluralWord) {
          return `${formatNumber(amount, 0)} ${amount === 1 ? singular : pluralWord}`;
        }

        function updateLiveCountdown(target) {
          const liveBox = document.getElementById("countdown-live");
          if (!liveBox) {
            return;
          }
          const remaining = Math.max(0, target - Date.now());
          const totalSeconds = Math.floor(remaining / 1000);
          const days = Math.floor(totalSeconds / 86400);
          const clock = [Math.floor(totalSeconds / 3600) % 24, Math.floor(totalSeconds / 60) % 60, totalSeconds % 60].map((part) => String(part).padStart(2, "0")).join(":");
          liveBox.textContent = `${plural(days, "dia", "dias")} e ${clock}`;
        }

        function calculate() {
          clearInterval(liveTimer);
          const target = dateFromInput("countdown-date");
          if (!target) {
            resultBox.innerHTML = emptyDisplay("Dias até a data", "Escolha uma data.");
            return;
          }
          const today = new Date(`${toIso(new Date())}T12:00:00`);
          const eventName = nameInput.value.trim();
          const dayDifference = Math.round((target - today) / MILLISECONDS_PER_DAY);
          const weekdayText = `${WEEKDAYS[target.getDay()]}, ${formatDate(dateInput.value)}`;
          const label = eventName ? `Dias até ${escapeHtml(eventName)}` : "Dias até a data";

          if (dayDifference === 0) {
            resultBox.innerHTML = display(label, "É hoje!", weekdayText);
            return;
          }
          if (dayDifference < 0) {
            resultBox.innerHTML = display(eventName ? `Desde ${escapeHtml(eventName)}` : "Essa data já passou", plural(-dayDifference, "dia", "dias"), `${weekdayText} foi há ${plural(-dayDifference, "dia", "dias")}`);
            return;
          }

          const split = monthsAndDays(today, target);
          const weeks = Math.floor(dayDifference / 7);
          const counts = countDaysUntil(today, target);
          resultBox.innerHTML = display(label, plural(dayDifference, "dia", "dias"), weekdayText, [
            ["Em semanas", `${plural(weeks, "semana", "semanas")}${dayDifference % 7 ? ` e ${plural(dayDifference % 7, "dia", "dias")}` : ""}`],
            ["Em meses", `${plural(split.months, "mês", "meses")}${split.days ? ` e ${plural(split.days, "dia", "dias")}` : ""}`],
            ["Dias úteis", formatNumber(counts.businessDays, 0)],
            ["Fins de semana", formatNumber(counts.weekends, 0)],
            ["Horas", formatNumber(dayDifference * 24, 0)],
          ], `<span class="display-detail">Contagem regressiva até 0h: <b id="countdown-live"></b></span>`);

          const midnightTarget = new Date(`${dateInput.value}T00:00:00`).getTime();
          updateLiveCountdown(midnightTarget);
          liveTimer = setInterval(() => updateLiveCountdown(midnightTarget), 1000);
        }

        document.getElementById("countdown-presets").addEventListener("click", (clickEvent) => {
          const button = clickEvent.target.closest("[data-preset]");
          if (!button) {
            return;
          }
          const preset = PRESETS[button.dataset.preset];
          dateInput.value = nextOccurrence(preset);
          nameInput.value = preset.name;
          calculate();
        });
        dateInput.value = nextOccurrence(PRESETS.natal);
        nameInput.value = PRESETS.natal.name;
        onInputs(["countdown-date", "countdown-name"], calculate);
      },
    },

    "signo-lunar": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="moon-date">Data de nascimento</label><input id="moon-date" type="date" value="1995-06-15"></div>
            <div class="field"><label for="moon-time">Hora (opcional)</label><input id="moon-time" type="time"></div>
            <div class="field"><label for="moon-zone">Fuso horário</label><select id="moon-zone">
              <option value="-3">Brasília (maioria dos estados)</option>
              <option value="-4">Amazonas, MT, MS, RO, RR</option>
              <option value="-5">Acre</option>
              <option value="-2">Fernando de Noronha</option>
              <option value="0">Londres / UTC</option>
              <option value="1">Portugal (horário de verão) / Europa Central</option>
            </select></div>
          </div>
          <span class="field-hint">Nasceu no horário de verão? Diminua 1 hora. Sem a hora, mostramos os dois signos possíveis se a Lua trocou de signo naquele dia.</span>
        </div>
        <div id="moon-result"></div>`,
      setup() {
        const SIGNS = ["Áries", "Touro", "Gêmeos", "Câncer", "Leão", "Virgem", "Libra", "Escorpião", "Sagitário", "Capricórnio", "Aquário", "Peixes"];
        const PHASES = ["Lua nova", "Lua crescente", "Quarto crescente", "Crescente gibosa", "Lua cheia", "Minguante gibosa", "Quarto minguante", "Lua minguante"];
        const SYNODIC_MONTH = 29.530589; // dias de uma lunação (nova a nova)
        const LUNAR_YEAR = SYNODIC_MONTH * 12;
        const toRadians = (degrees) => degrees * Math.PI / 180;
        const normalizeDegrees = (degrees) => ((degrees % 360) + 360) % 360;

        /* Posição aproximada do Sol e da Lua (fórmulas simplificadas do Astronomical Almanac,
           erro de cerca de 0,5°: a Lua anda uns 13° por dia, então o signo pode errar perto da troca). */
        function skyAt(timestamp) {
          const days = timestamp / MILLISECONDS_PER_DAY + 2440587.5 - 2451545.0;
          const sunAnomaly = toRadians(357.529 + 0.98560028 * days);
          const sunLongitude = normalizeDegrees(280.460 + 0.9856474 * days + 1.915 * Math.sin(sunAnomaly) + 0.020 * Math.sin(2 * sunAnomaly));
          const moonMeanLongitude = 218.316 + 13.176396 * days;
          const moonAnomaly = toRadians(134.963 + 13.064993 * days);
          const elongation = toRadians(297.850 + 12.190749 * days);
          const latitudeArgument = toRadians(93.272 + 13.229350 * days);
          const moonLongitude = normalizeDegrees(moonMeanLongitude
            + 6.289 * Math.sin(moonAnomaly)
            + 1.274 * Math.sin(2 * elongation - moonAnomaly)
            + 0.658 * Math.sin(2 * elongation)
            + 0.214 * Math.sin(2 * moonAnomaly)
            - 0.186 * Math.sin(sunAnomaly)
            - 0.114 * Math.sin(2 * latitudeArgument));
          const moonSunAngle = normalizeDegrees(moonLongitude - sunLongitude);
          return {
            sunSign: Math.floor(sunLongitude / 30),
            moonSign: Math.floor(moonLongitude / 30),
            moonAge: moonSunAngle / 360 * SYNODIC_MONTH,
            illumination: (1 - Math.cos(toRadians(moonSunAngle))) / 2,
            phase: PHASES[Math.floor(moonSunAngle / 45 + 0.5) % 8],
          };
        }

        // Momento (em UTC) de uma data e hora locais no fuso escolhido
        function timestampOf(dateText, timeText, zoneHours) {
          const [year, month, day] = dateText.split("-").map(Number);
          const [hours, minutes] = timeText.split(":").map(Number);
          return Date.UTC(year, month - 1, day, hours, minutes) - zoneHours * 3600000;
        }

        function calculate() {
          const dateText = document.getElementById("moon-date").value;
          const timeText = document.getElementById("moon-time").value;
          const zoneHours = Number(document.getElementById("moon-zone").value);
          const resultBox = document.getElementById("moon-result");
          if (!dateText) {
            resultBox.innerHTML = emptyDisplay("Signo lunar", "Informe a data de nascimento.");
            return;
          }

          const hasTime = timeText !== "";
          const birth = timestampOf(dateText, hasTime ? timeText : "12:00", zoneHours);
          const sky = skyAt(birth);
          let moonSignText = SIGNS[sky.moonSign];
          let detail = hasTime ? "Lua no momento do nascimento" : "sem a hora: posição ao meio-dia";
          if (!hasTime) {
            // A Lua passa uns 2 dias e meio em cada signo: confere o começo e o fim do dia
            const startSign = skyAt(timestampOf(dateText, "00:00", zoneHours)).moonSign;
            const endSign = skyAt(timestampOf(dateText, "23:59", zoneHours)).moonSign;
            if (startSign !== endSign) {
              moonSignText = `${SIGNS[startSign]} ou ${SIGNS[endSign]}`;
              detail = "a Lua trocou de signo nesse dia: informe a hora para saber qual";
            }
          }

          const daysLived = (Date.now() - birth) / MILLISECONDS_PER_DAY;
          const lunarAgeText = daysLived > 0
            ? `${formatNumber(Math.floor(daysLived / LUNAR_YEAR), 0)} anos lunares`
            : "—";
          resultBox.innerHTML = display("Seu signo lunar", moonSignText, detail, [
            ["Signo solar", SIGNS[sky.sunSign]],
            ["Fase da Lua", `${sky.phase} (${formatNumber(sky.illumination * 100, 0)}% iluminada)`],
            ["Idade da Lua no dia", `${formatNumber(sky.moonAge, 1)} dias desde a lua nova`],
            ["Sua idade lunar", lunarAgeText],
            ["Lunações vividas", daysLived > 0 ? formatNumber(Math.floor(daysLived / SYNODIC_MONTH), 0) : "—"],
          ]);
        }
        onInputs(["moon-date", "moon-time", "moon-zone"], calculate);
      },
    },

    "encurtador-url": {
      html: `        <div class="calculator-body">
          <div class="field"><label for="shorten-url">Link longo</label><input id="shorten-url" type="url" inputmode="url" autocomplete="off" placeholder="https://site.com.br/uma-pagina-com-endereco-bem-comprido"></div>
          <button type="button" class="action-button" id="shorten-button">Encurtar link</button>
        </div>
        <div id="shorten-result"></div>`,
      setup() {
        const urlInput = document.getElementById("shorten-url");
        const button = document.getElementById("shorten-button");
        const resultBox = document.getElementById("shorten-result");
        resultBox.innerHTML = emptyDisplay("Link curto", "Cole o link e toque em Encurtar.");

        async function shorten() {
          const longUrl = urlInput.value.trim();
          if (!longUrl) {
            resultBox.innerHTML = emptyDisplay("Link curto", "Cole o link que quer encurtar.");
            return;
          }
          button.disabled = true;
          resultBox.innerHTML = emptyDisplay("Link curto", "Encurtando…");
          try {
            const response = await fetch("/api/encurtar", {
              method: "POST",
              headers: { "Content-Type": "application/json", Accept: "application/json" },
              credentials: "same-origin",
              body: JSON.stringify({ url: longUrl }),
            });
            const data = await response.json();
            if (!response.ok) {
              resultBox.innerHTML = emptyDisplay("Link curto", escapeHtml(data.error || "Não foi possível encurtar agora."));
              return;
            }
            const savedCharacters = data.url.length - data.short_url.length;
            resultBox.innerHTML = `<div class="display"><span class="display-label">Seu link curto</span><span class="display-text base64-output" id="shorten-output">${escapeHtml(data.short_url)}</span>
              <span class="display-detail">leva para ${escapeHtml(data.url)}${savedCharacters > 0 ? ` · ${formatNumber(savedCharacters, 0)} caracteres a menos` : ""}</span>
              <div class="button-row"><button class="copy-button" type="button" data-copy-target="shorten-output">copiar link</button><a class="copy-button" href="${escapeHtml(data.short_url)}" target="_blank" rel="noopener">testar</a></div></div>`;
          } catch {
            resultBox.innerHTML = emptyDisplay("Link curto", "Sem conexão com o site. Tente de novo.");
          } finally {
            button.disabled = false;
          }
        }
        button.addEventListener("click", shorten);
        urlInput.addEventListener("keydown", (keyEvent) => {
          if (keyEvent.key === "Enter") {
            shorten();
          }
        });
      },
    },

    "quanto-posso-gastar": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="spend-income">Renda líquida do mês (R$)</label><input id="spend-income" inputmode="numeric" data-money value="4.000,00"><span class="field-hint">o que cai na conta, já sem INSS e IR</span></div>
            <div class="field"><label for="spend-fixed">Gastos fixos essenciais (R$)</label><input id="spend-fixed" inputmode="numeric" data-money value="2.000,00"><span class="field-hint">aluguel, contas, mercado, transporte, escola</span></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="spend-debts">Parcelas e dívidas (R$)</label><input id="spend-debts" inputmode="numeric" data-money value="300,00"><span class="field-hint">cartão parcelado, empréstimos, financiamentos</span></div>
            <div class="field"><label for="spend-save-rate">Quanto quer guardar (% da renda)</label><input id="spend-save-rate" inputmode="decimal" value="10"></div>
            <div class="field"><label for="spend-days">Dias até o próximo salário</label><input id="spend-days" type="number" min="1" max="31" value="30"></div>
          </div>
        </div>
        <div id="spend-result"></div>
        <div class="calculator-body" id="spend-table"></div>`,
      setup() {
        function calculate() {
          const [income, fixedCosts, debts] = ["spend-income", "spend-fixed", "spend-debts"].map((id) => parseNumber(document.getElementById(id).value) || 0);
          const saveRate = Math.max(0, parseNumber(document.getElementById("spend-save-rate").value) || 0) / 100;
          const daysUntilPay = Math.max(1, parseInt(document.getElementById("spend-days").value, 10) || 30);
          const resultBox = document.getElementById("spend-result");
          const tableBox = document.getElementById("spend-table");
          if (!(income > 0)) {
            resultBox.innerHTML = emptyDisplay("Pode gastar por mês", "Informe a renda líquida.");
            tableBox.innerHTML = "";
            return;
          }

          const savings = roundCents(income * saveRate);
          const freeToSpend = income - fixedCosts - debts - savings;
          const share = (amount) => formatPercent(amount / income);
          const warnings = [];
          if (debts / income > 0.3) {
            warnings.push(`As parcelas levam ${share(debts)} da renda. O recomendado é até 30%: evite novas compras parceladas.`);
          }
          if (freeToSpend < 0) {
            warnings.push(`Faltam ${formatMoney(-freeToSpend)} por mês para fechar a conta. Corte gastos, renegocie dívidas ou diminua o quanto vai guardar por enquanto.`);
          }

          resultBox.innerHTML = display(
            "Pode gastar por mês (lazer e extras)",
            formatMoney(Math.max(0, freeToSpend)),
            freeToSpend > 0 ? `${formatMoney(freeToSpend / daysUntilPay)} por dia · ${formatMoney(freeToSpend / daysUntilPay * 7)} por semana, até o próximo salário` : "não sobra nada para gastos livres",
            [
              ["Guardar todo mês", `${formatMoney(savings)} (${share(savings)})`],
              ["Gastos fixos", `${formatMoney(fixedCosts)} (${share(fixedCosts)})`],
              ["Parcelas e dívidas", `${formatMoney(debts)} (${share(debts)})`],
            ],
            warnings.map((warning) => `<span class="display-detail">⚠ ${warning}</span>`).join(""),
          );

          // Regra 50-30-20: 50% necessidades, 30% desejos, 20% guardar (ou pagar dívidas)
          const rows = [
            ["Necessidades (fixos + parcelas)", 0.5, fixedCosts + debts],
            ["Desejos (lazer, compras, extras)", 0.3, Math.max(0, freeToSpend)],
            ["Guardar ou investir", 0.2, savings],
          ].map(([label, idealRate, yours]) => `<tr><td>${label}</td><td>${formatMoney(income * idealRate)} (${formatPercent(idealRate)})</td><td>${formatMoney(yours)} (${share(yours)})</td></tr>`).join("");
          tableBox.innerHTML = `<h3>Comparação com a regra 50-30-20</h3>
            <table class="data-table"><thead><tr><th>Parte</th><th>Sugerido</th><th>Seu orçamento</th></tr></thead><tbody>${rows}</tbody></table>
            <p class="notice">A regra 50-30-20 é uma referência, não uma regra fixa. Com dívidas caras (cartão, cheque especial), vale usar os 20% para quitá-las primeiro.</p>`;
        }
        onInputs(["spend-income", "spend-fixed", "spend-debts", "spend-save-rate", "spend-days"], calculate);
      },
    },

    "quanto-guardar-por-mes": {
      html: `        <div class="calculator-body">
          <div class="field-row">
            <div class="field"><label for="goal-amount">Meta (R$)</label><input id="goal-amount" inputmode="numeric" data-money value="50.000,00"></div>
            <div class="field"><label for="goal-current">Já tenho guardado (R$)</label><input id="goal-current" inputmode="numeric" data-money value="0"></div>
            <div class="field"><label for="goal-months">Prazo (meses)</label><input id="goal-months" type="number" min="1" max="600" value="36"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="goal-rate">Rendimento (% ao ano)</label><input id="goal-rate" inputmode="decimal" value="10"></div>
          </div>
          <div class="button-row" id="goal-rate-shortcuts">
            <button type="button" class="secondary-button" data-rate="cdi">100% do CDI</button>
            <button type="button" class="secondary-button" data-rate="savings">Poupança</button>
            <button type="button" class="secondary-button" data-rate="none">Sem rendimento</button>
          </div>
          <span class="field-hint" id="goal-rate-hint">Buscando as taxas de hoje…</span>
        </div>
        <div id="goal-result"></div>
        <div class="calculator-body" id="goal-table"></div>`,
      setup() {
        const rateInput = document.getElementById("goal-rate");
        const currentRates = { none: 0 };

        function calculate() {
          const [goal, current] = ["goal-amount", "goal-current"].map((id) => parseNumber(document.getElementById(id).value) || 0);
          const months = parseInt(document.getElementById("goal-months").value, 10) || 0;
          const yearlyRate = (parseNumber(rateInput.value) || 0) / 100;
          const resultBox = document.getElementById("goal-result");
          const tableBox = document.getElementById("goal-table");
          if (!(goal > 0) || months <= 0) {
            resultBox.innerHTML = emptyDisplay("Guardar por mês", "Informe a meta e o prazo.");
            tableBox.innerHTML = "";
            return;
          }

          const monthlyRate = (1 + yearlyRate) ** (1 / 12) - 1;
          const growth = (1 + monthlyRate) ** months;
          const currentAtEnd = current * growth;
          const goalDate = new Date();
          goalDate.setMonth(goalDate.getMonth() + months);
          const goalDateText = goalDate.toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
          if (currentAtEnd >= goal) {
            resultBox.innerHTML = display("Guardar por mês", formatMoney(0), `o que você já tem chega a ${formatMoney(currentAtEnd)} em ${goalDateText}, sem novos depósitos`);
            tableBox.innerHTML = "";
            return;
          }

          // Depósito no fim de cada mês: meta = guardado × (1+i)ⁿ + depósito × [(1+i)ⁿ − 1] ÷ i
          const monthlyDeposit = monthlyRate > 0 ? (goal - currentAtEnd) * monthlyRate / (growth - 1) : (goal - current) / months;
          const totalDeposited = current + monthlyDeposit * months;
          const interestEarned = goal - totalDeposited;
          const withoutInterest = Math.max(0, (goal - current) / months);
          resultBox.innerHTML = display("Guardar por mês", formatMoney(monthlyDeposit), `por ${plural(months)} para juntar ${formatMoney(goal)} até ${goalDateText}`, [
            ["Total depositado", formatMoney(totalDeposited)],
            ["Juros ganhos", formatMoney(interestEarned)],
            ["Sem rendimento seria", `${formatMoney(withoutInterest)}/mês`],
            ["Rendimento ao mês", formatPercent(monthlyRate)],
          ]);

          // Saldo ao fim de cada ano (e no último mês)
          const rows = [];
          for (let month = 12; month < months + 12; month += 12) {
            const monthNumber = Math.min(month, months);
            const periodGrowth = (1 + monthlyRate) ** monthNumber;
            const balance = current * periodGrowth + (monthlyRate > 0 ? monthlyDeposit * (periodGrowth - 1) / monthlyRate : monthlyDeposit * monthNumber);
            const deposited = current + monthlyDeposit * monthNumber;
            rows.push(`<tr><td>${monthNumber === months && months % 12 ? `Mês ${monthNumber}` : `Ano ${monthNumber / 12}`}</td><td>${formatMoney(deposited)}</td><td>${formatMoney(balance - deposited)}</td><td>${formatMoney(balance)}</td></tr>`);
          }
          tableBox.innerHTML = `<table class="data-table"><thead><tr><th>Quando</th><th>Depositado</th><th>Juros</th><th>Saldo</th></tr></thead><tbody>${rows.join("")}</tbody></table>
            <p class="notice">Simulação bruta, com a mesma taxa o prazo todo. Em CDB e Tesouro, o IR (de 22,5% a 15%) sai do rendimento; poupança, LCI e LCA são isentas. Para comparar investimentos, use a calculadora de CDB, LCI, Tesouro ou poupança.</p>`;
        }
        function plural(months) {
          return months === 1 ? "1 mês" : `${formatNumber(months, 0)} meses`;
        }

        document.getElementById("goal-rate-shortcuts").addEventListener("click", (clickEvent) => {
          const button = clickEvent.target.closest("[data-rate]");
          if (button && currentRates[button.dataset.rate] !== undefined) {
            rateInput.value = formatNumber(currentRates[button.dataset.rate], 2);
            calculate();
          }
        });
        onInputs(["goal-amount", "goal-current", "goal-months", "goal-rate"], calculate);

        // CDI e poupança atuais (Banco Central, pela faixa de indicadores do site)
        const rateHint = document.getElementById("goal-rate-hint");
        let typedByPerson = false;
        rateInput.addEventListener("input", () => { typedByPerson = true; }, { once: true });
        fetch("/api/indicadores", { headers: { Accept: "application/json" }, credentials: "same-origin" })
          .then((response) => (response.ok ? response.json() : Promise.reject()))
          .then((data) => {
            const parts = [];
            if (data.cdi) {
              currentRates.cdi = data.cdi.yearly;
              parts.push(`CDI de ${formatNumber(data.cdi.yearly, 2)}% ao ano`);
            }
            if (data.savings) {
              currentRates.savings = ((1 + data.savings.monthly / 100) ** 12 - 1) * 100;
              parts.push(`poupança de ${formatNumber(currentRates.savings, 2)}% ao ano`);
            }
            if (currentRates.cdi !== undefined && !typedByPerson && !new URLSearchParams(location.search).has("goal-rate")) {
              rateInput.value = formatNumber(currentRates.cdi, 2);
              calculate();
            }
            rateHint.textContent = parts.length ? `Taxas de hoje (Banco Central): ${parts.join(" e ")}.` : "Digite o rendimento esperado ao ano.";
          })
          .catch(() => {
            rateHint.textContent = "Não foi possível buscar as taxas de hoje; digite o rendimento esperado ao ano.";
          });
      },
    },

  };

  // Diferença em anos, meses e dias (usada em idade e diferença entre datas)
  function yearsMonthsDays(start, end) {
    let years = end.getFullYear() - start.getFullYear();
    let months = end.getMonth() - start.getMonth();
    let days = end.getDate() - start.getDate();
    if (days < 0) {
      months -= 1;
      days += new Date(end.getFullYear(), end.getMonth(), 0).getDate();
    }
    if (months < 0) {
      years -= 1;
      months += 12;
    }
    return { years, months, days };
  }


  /* ---------- Compartilhar a conta (WhatsApp ou link) ----------
     O link leva os números da calculadora na URL (?id-do-campo=valor) e quem abre já vê a mesma conta.
     Vão só campos curtos (nada de textos grandes, como JSON ou JWT). utm_source mostra no painel de onde veio a visita. */
  const SHARE_MAX_VALUE_LENGTH = 40;

  function shareableFields(container) {
    return [...container.querySelectorAll("input[id], select[id]")]
      .filter((field) => !["file", "password", "checkbox", "radio", "hidden"].includes(field.type) && !field.disabled && field.value !== "" && field.value.length <= SHARE_MAX_VALUE_LENGTH);
  }
  function shareUrl(container, source) {
    const params = new URLSearchParams();
    shareableFields(container).forEach((field) => params.set(field.id, field.value));
    // Botões de escolha (ex.: tipo de cálculo da porcentagem): guarda o botão marcado
    container.querySelectorAll(".segmented[id]").forEach((group) => {
      const pressed = group.querySelector('button[aria-pressed="true"]');
      if (pressed?.dataset.value) {
        params.set(group.id, pressed.dataset.value);
      }
    });
    params.set("utm_source", source);
    return `${location.origin}${location.pathname}?${params}`;
  }
  // Abriu um link compartilhado: preenche os campos e refaz a conta
  function applySharedValues(container) {
    const params = new URLSearchParams(location.search);
    params.forEach((value, id) => {
      const element = document.getElementById(id);
      if (!element || !container.contains(element)) {
        return;
      }
      if (element.classList.contains("segmented")) {
        element.querySelector(`button[data-value="${CSS.escape(value)}"]`)?.click();
      } else if (element.matches("input, select") && value.length <= SHARE_MAX_VALUE_LENGTH) {
        if (element.tagName === "SELECT" && ![...element.options].some((option) => option.value === value)) {
          return; // opção que não existe (ou ainda não carregou)
        }
        element.value = value;
        element.dispatchEvent(new Event("input", { bubbles: true }));
        element.dispatchEvent(new Event("change", { bubbles: true }));
      }
    });
  }
  function setupShareBar(container) {
    const shareBar = document.getElementById("share-bar");
    if (!shareBar) {
      return;
    }
    shareBar.hidden = false;
    // Texto da mensagem: o resultado principal da calculadora, se já houver um
    const resultText = () => {
      const label = container.querySelector(".display-label")?.textContent.trim();
      const value = container.querySelector(".display-value")?.textContent.trim();
      const toolName = document.querySelector(".tool-title")?.textContent.trim() || "Vibe2000";
      return label && value && value !== "—" ? `📊 ${toolName}: ${label} ${value}` : `📊 ${toolName}`;
    };
    document.getElementById("share-whatsapp").addEventListener("click", () => {
      const message = `${resultText()}\nFiz essa conta no Vibe2000. Veja ou refaça com os seus números:\n${shareUrl(container, "whatsapp")}`;
      window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, "_blank", "noopener");
    });
    const copyButton = document.getElementById("share-copy");
    copyButton.addEventListener("click", async () => {
      const link = shareUrl(container, "link");
      try {
        await navigator.clipboard.writeText(link);
        copyButton.textContent = "Link copiado!";
      } catch {
        window.prompt("Copie o link:", link);
      }
      setTimeout(() => { copyButton.textContent = "Copiar link"; }, 2500);
    });
  }

  // Monta a calculadora da página (o id vem do atributo data-tool)
  const calculatorElement = document.getElementById("calculator");
  if (calculatorElement) {
    const calculator = CALCULATORS[calculatorElement.dataset.tool];
    if (calculator) {
      calculatorElement.innerHTML = calculator.html;
      formatMoneyFields(calculatorElement);
      calculator.setup();
      applySharedValues(calculatorElement);
      setupShareBar(calculatorElement);
      // Avisa o contador de visitas no primeiro cálculo feito nesta página
      calculatorElement.addEventListener("input", () => window.Vibe2000?.registerToolUse(calculatorElement.dataset.tool), { once: true });
      calculatorElement.addEventListener("click", (clickEvent) => {
        if (clickEvent.target.closest("button")) {
          window.Vibe2000?.registerToolUse(calculatorElement.dataset.tool);
        }
      });
    }
  }

  // Conta rápida de porcentagem (lateral da página inicial)
  const quickPercent = document.getElementById("quick-percent");
  if (quickPercent) {
    onInputs(["quick-percent", "quick-value"], () => {
      const percent = parseNumber(document.getElementById("quick-percent").value);
      const value = parseNumber(document.getElementById("quick-value").value);
      document.getElementById("quick-result").textContent = Number.isNaN(percent) || Number.isNaN(value) ? "—" : formatNumber((percent / 100) * value, 4);
    });
  }

  // Botões de copiar dentro dos resultados (senha, texto convertido)
  document.addEventListener("click", async (clickEvent) => {
    const copyButton = clickEvent.target.closest("[data-copy-target]");
    if (!copyButton) {
      return;
    }
    const target = document.getElementById(copyButton.dataset.copyTarget);
    try {
      await navigator.clipboard.writeText(target.textContent);
      copyButton.textContent = "copiado!";
    } catch {
      const range = document.createRange();
      range.selectNodeContents(target);
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
      copyButton.textContent = "selecionado: Ctrl+C";
    }
  });
})();
