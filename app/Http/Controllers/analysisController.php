<?php

namespace App\Http\Controllers;

use App\Models\CarbonAnalysis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalysisController extends Controller
{
    public function analyze(Request $request)
    {
        try {
            $data = $request->validate([
                'area_total'     => 'required|numeric',
                'area_cultivo'   => 'required|numeric',
                'tipo_solo'      => 'required|string',
                'cidade'         => 'required|string',
                'estado'         => 'required|string',
                'area_nativa'    => 'required|numeric',
                'metodo_plantio' => 'required|string',
                'email'          => 'sometimes|email',
                'telefone'       => 'sometimes|string',
            ]);

            if ($data['area_nativa'] == 0) {
                $area_nativa = $data['area_total'] - $data['area_cultivo'];
            } else {
                $area_nativa = $data['area_nativa'];
            }

            $promptUsuario = sprintf(
                "Com base nos dados abaixo, estime e RETORNE APENAS o JSON no formato do schema fornecido (sem texto extra).\n" .
                    "- Área total: %s ha\n- Área de cultivo: %s ha\n- Tipo de solo: %s\n" .
                    "- Local: %s/%s\n- Área nativa: %s ha\n- Método de plantio: %s\n\n" .
                    "Regras: valores podem ser FAIXAS (ex.: '15–30'), use ponto decimal, e use sempre as chaves exatamente como no schema.",
                $data['area_total'],
                $data['area_cultivo'],
                $data['tipo_solo'],
                $data['cidade'],
                $data['estado'],
                $area_nativa,
                $data['metodo_plantio']
            );

            Log::channel('api-request')->info('Request enviado para análise', [
                'request' => $promptUsuario,
            ]);

            $store = filter_var(env('OPENAI_STORE_RESPONSES', true), FILTER_VALIDATE_BOOLEAN);

            $payload = [
                'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
                'store' => $store,
                'input' => [
                    [
                        'role' => 'system',
                        'content' =>
                        'Você é um consultor técnico de créditos de carbono. ' .
                            'Responda em português e apenas com JSON válido conforme o schema. ' .
                            'Os valores monetários devem estar em reais (R$) e o preço do crédito fixo em R$25,00.'
                    ],
                    ['role' => 'user', 'content' => $promptUsuario],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'analise_topicos',
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'area_cultivo' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'hectares' => ['type' => 'number'],
                                        'creditos_por_ha' => ['type' => 'string'],
                                        'creditos_total'  => ['type' => 'string']
                                    ],
                                    'required' => ['hectares', 'creditos_por_ha', 'creditos_total']
                                ],
                                'area_desmatamento_evitado' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'hectares' => ['type' => 'number'],
                                        'creditos_por_ha' => ['type' => 'string'],
                                        'creditos_total'  => ['type' => 'string']
                                    ],
                                    'required' => ['hectares', 'creditos_por_ha', 'creditos_total']
                                ],
                                'potencial_geracao' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'creditos_total' => ['type' => 'string']
                                    ],
                                    'required' => ['creditos_total']
                                ],
                                'valor_estimado' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'preco_credito'   => ['type' => 'number'],
                                        'cultivo_anual'   => ['type' => 'string'],   // ex: "R$250–R$1.000/ano"
                                        'evitado_one_time' => ['type' => 'string'],   // ex: "R$11.250–R$37.500"
                                        'observacao'      => ['type' => 'string']    // ex: "Evitado é estoque preservado (não recorrente)."
                                    ],
                                    // ✅ inclui todas as chaves de properties
                                    'required' => ['preco_credito', 'cultivo_anual', 'evitado_one_time', 'observacao']
                                ],
                                'observacoes' => ['type' => 'string']
                            ],
                            'required' => [
                                'area_cultivo',
                                'area_desmatamento_evitado',
                                'potencial_geracao',
                                'valor_estimado',
                                'observacoes'
                            ]
                        ]
                    ]
                ]
            ];

            $resp = Http::withToken(env('OPENAI_API_KEY'))
                ->acceptJson()
                ->asJson()
                ->timeout(90)
                ->post('https://api.openai.com/v1/responses', $payload);

            if ($resp->failed()) {
                // retorna erro da API com status correspondente
                return response()->json([
                    'ok' => false,
                    'upstream_error' => $resp->json(),
                ], $resp->status());
            }

            $json = $resp->json();

            $structured = null;
            $output = data_get($json, 'output', []);
            foreach ($output as $block) {
                $parts = data_get($block, 'content', []);
                foreach ($parts as $p) {
                    if (data_get($p, 'type') === 'output_text') {
                        $text = data_get($p, 'text', '');
                        if ($this->isJson($text)) {
                            $structured = json_decode($text, true);
                        }
                    }
                    if (data_get($p, 'type') === 'json') {
                        $structured = data_get($p, 'json', null);
                    }
                }
            }

            if ($structured) {
                $cultivo = $structured['area_cultivo'] ?? [];
                $evitado = $structured['area_desmatamento_evitado'] ?? [];
                $potencial = $structured['potencial_geracao'] ?? [];
                $valor = $structured['valor_estimado'] ?? [];

                [$credCultMin, $credCultMax] = $this->parseRange($cultivo['creditos_total'] ?? null);
                [$credEvitMin, $credEvitMax] = $this->parseRange($evitado['creditos_total'] ?? null);
                [$credTotMin, $credTotMax] = $this->parseRange($potencial['creditos_total'] ?? null);
                [$valMin, $valMax] = $this->parseRange($valor['valor_anual'] ?? null);

                CarbonAnalysis::create([
                    'response_id' => $json['id'] ?? $json['response_id'] ?? null,
                    'email' => $request->input('email'),
                    'telefone' => $request->input('telefone'),
                    'data' => $structured,

                    'area_cultivo_hectares' => $cultivo['hectares'] ?? null,
                    'area_desmatamento_hectares' => $evitado['hectares'] ?? null,

                    'cred_cultivo_min' => $credCultMin,
                    'cred_cultivo_max' => $credCultMax,
                    'cred_evitado_min' => $credEvitMin,
                    'cred_evitado_max' => $credEvitMax,
                    'cred_total_min' => $credTotMin,
                    'cred_total_max' => $credTotMax,

                    'preco_credito' => $valor['preco_credito'] ?? null,
                    'valor_anual_min' => $valMin,
                    'valor_anual_max' => $valMax,
                    'observacoes' => $structured['observacoes'] ?? null,
                ]);
            }

            Log::channel('api-response')->info('Resposta da OpenAI', [
                'status' => $resp->status(),
                'body' => $resp->json(),
            ]);

            return response()->json([
                'ok' => true,
                'stored' => $store,
                'response_id' => $json['id'] ?? null,
                'data' => $structured ?? $json,
            ]);
        } catch (Throwable $e) {
            // loga a exception e retorna 500 com mensagem clara
            Log::error('[analise-fazenda] exception', [
                'msg' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                // 'trace' => $e->getTraceAsString(), // ative se precisar
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Erro interno no servidor',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function isJson($str): bool
    {
        if (!is_string($str)) return false;
        json_decode($str);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function parseRange(?string $str): array
    {
        if (!$str) return [null, null];
        $numbers = preg_split('/[^\d\.]+/', $str);
        $numbers = array_filter($numbers, fn($n) => is_numeric($n));
        $min = isset($numbers[0]) ? (float)$numbers[0] : null;
        $max = isset($numbers[1]) ? (float)$numbers[1] : $min;
        return [$min, $max];
    }
}
