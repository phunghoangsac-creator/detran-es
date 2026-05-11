<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/app_storage.php';
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=UTF-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// 1. Tenta ler o JSON do corpo da requisição (POST)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 2. Se não for JSON, tenta ler da URL (GET)
if (!$data) {
    $data = [
        'placa' => $_GET['placa'] ?? null,
        'renavam' => $_GET['renavam'] ?? null
    ];
}

// 3. Validação final
if (empty($data['placa']) || empty($data['renavam'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos. Envie placa e renavam via POST JSON ou URL GET.']);
    exit;
}

$placa = strtoupper(trim($data['placa']));
$renavam = trim($data['renavam']);


$capsolverKey = 'CAP-357A4F5B45FE78D76CA7D491D38300C0';
$cookieFile = tempnam(sys_get_temp_dir(), 'cookies');

// Funções Auxiliares
function getStr($string, $start, $end) {
    $str = explode($start, $string);
    if (isset($str[1])) {
        $str = explode($end, $str[1]);
        return $str[0];
    }
    return null;
}

function getCapsolverToken($key) {
    $url = 'https://api.capsolver.com/createTask';
    $payload = json_encode([
        'clientKey' => $key,
        'task' => [
            'type' => 'AntiTurnstileTaskProxyLess',
            'websiteURL' => 'https://servicos.detrannet.es.gov.br/',
            'websiteKey' => '0x4AAAAAAAy6XXSbwPTDYHHM',
            'metadata' => ['action' => 'login']
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($response['taskId'])) return null;

    $taskId = $response['taskId'];

    // Polling Silencioso
    while (true) {
        sleep(3);
        $ch = curl_init('https://api.capsolver.com/getTaskResult');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['clientKey' => $key, 'taskId' => $taskId]));
        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($result['status'] === 'ready') return $result['solution']['token'];
        if ($result['status'] === 'failed') return null;
    }
}

function getInnerHtml(DOMNode $node) {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    return trim($html);
}

function getFirstNodeHtmlByClass($html, $tagName, array $classes, $innerOnly = true) {
    libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($document);

    $query = '//' . $tagName;
    foreach ($classes as $className) {
        $query .= '[contains(concat(" ", normalize-space(@class), " "), " ' . $className . ' ")]';
    }

    $node = $xpath->query($query)->item(0);

    if (!$node) {
        return null;
    }

    return $innerOnly ? getInnerHtml($node) : trim($document->saveHTML($node));
}

function getInputValueById($html, $inputId) {
    libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($document);
    $node = $xpath->query('//input[@id="' . $inputId . '"]')->item(0);

    if (!$node) {
        return null;
    }

    return $node->getAttribute('value');
}

function getInputValueByName($html, $inputName) {
    libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($document);
    $node = $xpath->query('//input[@name="' . $inputName . '"]')->item(0);

    if (!$node) {
        return null;
    }

    return $node->getAttribute('value');
}

// 1. OBTER TOKEN CAPCHA
$turnstileToken = getCapsolverToken($capsolverKey);
if (!$turnstileToken) {
    echo json_encode(['success' => false, 'message' => 'Erro ao resolver Captcha']);
    exit;
}
//-------------------------------------------------------------------------------

$cfg = app_config_read('pix_config.json');
if ($cfg['apiCookie'] !== '') {
    $cookie = trim($cfg['apiCookie']);
}

//-------------------------------------------------------------------------------


$url = "https://servicos.detrannet.es.gov.br/CentralVeiculo/ConsultarVeiculo";

$data = [
    "Servico" => "DossieConsolidadoVeiculo",
    "Placa" => $placa,
    "Renavam" => $renavam,
    "TurnstileToken" => $turnstileToken
];

$jsonData = json_encode($data);

// Definição dos Headers conforme sua especificação
$headers = [
    "Accept: */*",
    "Accept-Encoding: gzip, deflate, br, zstd",
    "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
    "Connection: keep-alive",
    "Content-Type: application/json",
    'Cookie: '.$cookie.'',
    "Host: servicos.detrannet.es.gov.br",
    "Origin: https://servicos.detrannet.es.gov.br",
    "Referer: https://servicos.detrannet.es.gov.br/CentralVeiculo?Servico=DossieConsolidadoVeiculo",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36",
    "sec-ch-ua: \"Chromium\";v=\"146\", \"Not-A.Brand\";v=\"24\", \"Google Chrome\";v=\"146\"",
    "sec-ch-ua-mobile: ?0",
    "sec-ch-ua-platform: \"Windows\"",
    "Sec-Fetch-Dest: empty",
    "Sec-Fetch-Mode: cors",
    "Sec-Fetch-Site: same-origin"
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, ""); 

// 1. ADICIONE ESTA LINHA para incluir o header no output
curl_setopt($ch, CURLOPT_HEADER, true);



 $response = curl_exec($ch);



// 1. Pegue o cabeçalho da sua resposta
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headerContent = substr($response, 0, $headerSize);

if (preg_match('/set-cookie:\s*([^;]+)/i', $headerContent, $matches)) {
    $cookieCompleto = trim($matches[1]); // Ex: "0d2e8aa...=CfDJ8..."
    
    // 1. Criando o $cookieFinal2 pegando apenas o que vem antes do "="
    $partes = explode('=', $cookieCompleto);
    $cookieFinal2 = $partes[0]; 
    
    // Opcional: manter o $cookieFinal original se precisar dele
    $cookieFinal = $cookieCompleto . ";";
} else {
    $cookieFinal2 = "Não encontrado";
}

// Resultado: 0d2e8aab93a44e9885dabd33e7c39404



    
    $url = "https://servicos.detrannet.es.gov.br/Dossie?idServico=$cookieFinal2";
    
    $ch = curl_init($url);

// Montagem dos Headers
$headers = [
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
    "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
    "Connection: keep-alive",
    "Host: servicos.detrannet.es.gov.br",
    "Referer: https://servicos.detrannet.es.gov.br/CentralVeiculo?Servico=EmitirDuaIpva",
    "sec-ch-ua: \"Chromium\";v=\"146\", \"Not-A.Brand\";v=\"24\", \"Google Chrome\";v=\"146\"",
    "sec-ch-ua-mobile: ?0",
    "sec-ch-ua-platform: \"Windows\"",
    "Sec-Fetch-Dest: document",
    "Sec-Fetch-Mode: navigate",
    "Sec-Fetch-Site: same-origin",
    "Sec-Fetch-User: ?1",
    "Upgrade-Insecure-Requests: 1",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36",
	    'Cookie: '.$cookie.' '.$cookieFinal.'',
];

// Configurações do cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Segue redirecionamentos
curl_setopt($ch, CURLOPT_ENCODING, ""); // Lida com compressão (gzip, deflate, br) automaticamente


$dossieHtml = curl_exec($ch);



$url = "https://servicos.detrannet.es.gov.br/Dossie/DossieDebitos?idServico=$cookieFinal2";

$ch = curl_init($url);

// Montagem dos Headers
$headers = [
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
    "Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
    "Connection: keep-alive",
    "Host: servicos.detrannet.es.gov.br",
    "Referer: https://servicos.detrannet.es.gov.br/Dossie?idServico=$cookieFinal2",
    "sec-ch-ua: \"Chromium\";v=\"146\", \"Not-A.Brand\";v=\"24\", \"Google Chrome\";v=\"146\"",
    "sec-ch-ua-mobile: ?0",
    "sec-ch-ua-platform: \"Windows\"",
    "Sec-Fetch-Dest: document",
    "Sec-Fetch-Mode: navigate",
    "Sec-Fetch-Site: same-origin",
    "Sec-Fetch-User: ?1",
    "Upgrade-Insecure-Requests: 1",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36",
	   'Cookie: '.$cookie.' '.$cookieFinal.'',
];

// Configurações do cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Segue redirecionamentos
curl_setopt($ch, CURLOPT_ENCODING, ""); // Lida com compressão (gzip, deflate, br) automaticamente


$dossieDebitosHtml = curl_exec($ch);

$gridWrapperHtml = getFirstNodeHtmlByClass($dossieHtml, 'div', ['grid-wrapper']);
$contentDebitosHtml = getFirstNodeHtmlByClass($dossieDebitosHtml, 'div', ['accordion-body'], true);
$corpoTabelaHtml = getFirstNodeHtmlByClass($dossieDebitosHtml, 'div', ['corpo-tabela', 'list-group', 'list-group-flush']);

$targets = array_values(array_filter([
    [
        'selector' => '.grid-wrapper',
        'html' => $gridWrapperHtml
    ],
    [
        'selector' => '#content-debitos',
        'html' => $contentDebitosHtml
    ],
    [
        'selector' => '.corpo-tabela.list-group.list-group-flush',
        'html' => $corpoTabelaHtml
    ]
], function ($target) {
    return isset($target['html']) && $target['html'] !== null && $target['html'] !== '';
}));

if (empty($gridWrapperHtml)) {
    echo json_encode([
        'success' => false,
        'message' => 'O Renavam informado não é igual ao Renavam cadastrado para o veículo.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Salvar no log de buscas
app_log_append('search_log.json', [
    'ts' => date('Y-m-d H:i:s'),
    'plate' => $placa,
    'renavam' => $renavam,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    'ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
], 200);

echo json_encode([
    'success' => true,
    'placa' => $placa,
    'renavam' => $renavam,
    'idServico' => $cookieFinal2,
    'targets' => $targets,
    'inputs' => [
        'hdId' => getInputValueById($dossieDebitosHtml, 'hdId'),
        'hdServico' => getInputValueById($dossieDebitosHtml, 'hdServico'),
        'hdDebitos' => getInputValueById($dossieDebitosHtml, 'hdDebitos'),
        '__RequestVerificationToken' => getInputValueByName($dossieDebitosHtml, '__RequestVerificationToken')
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);



?>
