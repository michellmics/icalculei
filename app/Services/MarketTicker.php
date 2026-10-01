<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ErrorHandler;
use App\Core\FileCache;
use App\Core\HttpClient;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Faixa "Indicadores" da página inicial. Fontes gratuitas:
 *   Bitcoin       AwesomeAPI (a mesma do dólar)                      cache 10 minutos
 *   CDI           Banco Central, SGS série 4389 (% ao ano)          cache 6 horas
 *   Combustíveis  ANP, planilha semanal "resumo_semanal_lpc" (aba BRASIL), semana atual × anterior   cache 1 dia
 *   Poupança      Banco Central, série 195 (via MonetaryIndexes)    cache dela (12 horas)
 *   Focus         Banco Central, expectativas de IPCA e Selic para o ano   cache 6 horas
 * Cada item tem cache próprio; se uma fonte falhar, usa o último valor guardado ou o item sai da faixa.
 */
class MarketTicker
{
    private const ANP_PAGE = 'https://www.gov.br/anp/pt-br/assuntos/precos-e-defesa-da-concorrencia/precos/levantamento-de-precos-de-combustiveis-ultimas-semanas-pesquisadas';
    private const FOCUS_URL = "https://olinda.bcb.gov.br/olinda/servico/Expectativas/versao/v1/odata/ExpectativasMercadoAnuais?\$top=1&\$filter=Indicador%%20eq%%20'%s'%%20and%%20DataReferencia%%20eq%%20'%d'%%20and%%20baseCalculo%%20eq%%200&\$orderby=Data%%20desc&\$format=json&\$select=Data,Mediana";
    // Produtos da planilha da ANP mostrados na faixa
    private const FUELS = ['gasoline' => 'GASOLINA COMUM', 'ethanol' => 'ETANOL HIDRATADO', 'diesel' => 'OLEO DIESEL S10'];

    private static function cache(): FileCache
    {
        return new FileCache('ticker');
    }

    /**
     * Todos os itens disponíveis: ['bitcoin' => [...], 'cdi' => [...], ...]. Item que falhou e nunca foi guardado fica de fora.
     */
    public static function all(): array
    {
        $sources = [
            'bitcoin' => [600, fn () => self::bitcoin()],
            'cdi' => [6 * 3600, fn () => self::cdi()],
            'fuel' => [86400, fn () => self::fuel()],
            'savings' => [12 * 3600, fn () => self::savings()],
            'focus' => [6 * 3600, fn () => self::focus()],
        ];
        $items = [];
        foreach ($sources as $key => [$maxAge, $fetch]) {
            $value = self::cache()->get($key, $maxAge);
            if ($value === null) {
                try {
                    $value = $fetch();
                    self::cache()->set($key, $value);
                } catch (Throwable $exception) {
                    ErrorHandler::log("[indicadores] {$key}: " . $exception->getMessage());
                    $value = self::cache()->get($key); // último valor guardado, mesmo antigo
                    if ($value !== null) {
                        // Guarda de novo para reiniciar o prazo: a próxima tentativa só depois do ciclo normal
                        // (sem baixar de novo a cada visita enquanto a fonte estiver fora do ar)
                        self::cache()->set($key, $value);
                    }
                }
            }
            if ($value !== null) {
                $items[$key] = $value;
            }
        }

        return $items;
    }

    private static function getJson(string $url, int $timeoutSeconds = 15): array
    {
        [$status, $body] = HttpClient::request('GET', $url, ['Accept: application/json'], null, $timeoutSeconds);
        $data = json_decode($body, true);
        if ($status !== 200 || !is_array($data)) {
            throw new RuntimeException("HTTP {$status}");
        }

        return $data;
    }

    private static function bitcoin(): array
    {
        $quote = self::getJson('https://economia.awesomeapi.com.br/json/last/BTC-BRL')['BTCBRL'] ?? null;
        if (!isset($quote['bid'])) {
            throw new RuntimeException('resposta sem preço');
        }

        return ['value' => (float) $quote['bid'], 'changePercent' => (float) $quote['pctChange']];
    }

    private static function cdi(): array
    {
        $rows = self::getJson('https://api.bcb.gov.br/dados/serie/bcdata.sgs.4389/dados/ultimos/1?formato=json');
        if (!isset($rows[0]['valor'])) {
            throw new RuntimeException('resposta vazia');
        }

        return ['yearly' => (float) $rows[0]['valor'], 'date' => $rows[0]['data']];
    }

    private static function savings(): array
    {
        $months = MonetaryIndexes::monthly('poupanca')['months'];
        [$month, $rate] = end($months);

        return ['monthly' => $rate, 'month' => $month];
    }

    private static function focus(): array
    {
        $year = (int) date('Y');
        $result = ['year' => $year];
        foreach (['ipca' => 'IPCA', 'selic' => 'Selic'] as $key => $indicator) {
            $row = self::getJson(sprintf(self::FOCUS_URL, $indicator, $year), 20)['value'][0] ?? null;
            if ($row === null) {
                throw new RuntimeException("Focus sem {$indicator}");
            }
            $result[$key] = round((float) $row['Mediana'], 2);
            $result['date'] = $row['Data'];
        }

        return $result;
    }

    /**
     * Preço médio no Brasil (gasolina, etanol, diesel S10) da última semana da ANP e a variação sobre a anterior.
     */
    private static function fuel(): array
    {
        [$status, $page] = HttpClient::request('GET', self::ANP_PAGE, [], null, 20);
        preg_match_all('#href="(https://www\.gov\.br/anp/[^"]*resumo_semanal_lpc[^"]*\.xlsx)"#', $page, $matches);
        $links = array_values(array_unique($matches[1]));
        if ($status !== 200 || count($links) < 1) {
            throw new RuntimeException('planilha da ANP não encontrada');
        }
        $latest = self::anpBrazilPrices($links[0]);
        $previous = isset($links[1]) ? self::anpBrazilPrices($links[1]) : [];
        $fuels = [];
        foreach (self::FUELS as $key => $product) {
            if (!isset($latest['prices'][$product])) {
                continue;
            }
            $price = $latest['prices'][$product];
            $before = $previous['prices'][$product] ?? null;
            $fuels[$key] = ['value' => $price, 'changePercent' => $before ? round(($price / $before - 1) * 100, 2) : null];
        }
        if ($fuels === []) {
            throw new RuntimeException('produtos não encontrados na planilha');
        }

        return ['week' => $latest['week'], 'prices' => $fuels];
    }

    /**
     * Lê a aba "BRASIL" da planilha semanal da ANP: [produto => preço médio] e a semana pesquisada.
     */
    private static function anpBrazilPrices(string $url): array
    {
        [$status, $content] = HttpClient::request('GET', $url, [], null, 30);
        if ($status !== 200 || $content === '') {
            throw new RuntimeException("planilha HTTP {$status}");
        }
        $file = tempnam(sys_get_temp_dir(), 'anp');
        file_put_contents($file, $content);
        $zip = new ZipArchive();
        try {
            if ($zip->open($file) !== true) {
                throw new RuntimeException('planilha não abriu');
            }
            // Textos repetidos ficam em sharedStrings; as células guardam o número do texto
            $sharedStrings = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                foreach (simplexml_load_string($sharedXml)->si as $item) {
                    $text = (string) $item->t;
                    foreach ($item->r ?? [] as $run) {
                        $text .= (string) $run->t;
                    }
                    $sharedStrings[] = trim($text);
                }
            }
            $sheetXml = $zip->getFromName(self::sheetPath($zip, 'BRASIL'));
            if ($sheetXml === false) {
                throw new RuntimeException('aba BRASIL não encontrada');
            }
            $prices = [];
            $week = '';
            foreach (simplexml_load_string($sheetXml)->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $value = (string) $cell->v;
                    $cells[preg_replace('/\d+/', '', (string) $cell['r'])] = (string) $cell['t'] === 's' ? ($sharedStrings[(int) $value] ?? '') : $value;
                }
                // Colunas: A data inicial, B data final, C "BRASIL", D produto, G preço médio de revenda
                if (($cells['C'] ?? '') === 'BRASIL' && isset($cells['D'], $cells['G']) && is_numeric($cells['G'])) {
                    $prices[$cells['D']] = round((float) $cells['G'], 2);
                    $week = self::excelDate((float) $cells['B']);
                }
            }

            return ['prices' => $prices, 'week' => $week];
        } finally {
            $zip->close();
            @unlink($file);
        }
    }

    /**
     * Caminho do XML de uma aba pelo nome (workbook.xml + workbook.xml.rels).
     */
    private static function sheetPath(ZipArchive $zip, string $sheetName): string
    {
        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $relations = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        foreach ($workbook->sheets->sheet as $sheet) {
            if (strtoupper((string) $sheet['name']) !== $sheetName) {
                continue;
            }
            $relationId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            foreach ($relations->Relationship as $relation) {
                if ((string) $relation['Id'] === $relationId) {
                    return 'xl/' . ltrim((string) $relation['Target'], '/');
                }
            }
        }

        return '';
    }

    /**
     * Data do Excel (dias desde 30/12/1899) → "dd/mm/aaaa".
     */
    private static function excelDate(float $serial): string
    {
        return gmdate('d/m/Y', (int) round(($serial - 25569) * 86400)); // gmdate: a data do Excel não tem fuso
    }
}
