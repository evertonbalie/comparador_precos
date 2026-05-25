<?php
// gestao_estoque.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('America/Sao_Paulo');

$erro_sync = null;

try {
    $pdo = new PDO("sqlite:meus_precos.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Tabela Principal de Produtos
    $pdo->exec("CREATE TABLE IF NOT EXISTS estoque_produtos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome_produto TEXT NOT NULL UNIQUE,
        preco_venda REAL DEFAULT 0.00,
        quantidade INTEGER DEFAULT 0
    )");

    try {
        $pdo->exec("ALTER TABLE estoque_produtos ADD COLUMN codigo_barras TEXT");
    } catch (Exception $e) {
    }
    try {
        $pdo->exec("ALTER TABLE estoque_produtos ADD COLUMN validade TEXT");
    } catch (Exception $e) {
    }

    // 2. NOVA TABELA: Controle de Lotes
    $pdo->exec("CREATE TABLE IF NOT EXISTS estoque_lotes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome_produto TEXT NOT NULL,
        validade TEXT NOT NULL,
        quantidade INTEGER DEFAULT 0,
        UNIQUE(nome_produto, validade)
    )");

    // 3. Tabela de Configurações
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (chave TEXT PRIMARY KEY, valor TEXT)");
    $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('alerta_dias', '30')");
    $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('telegram_token', '')");
    $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('telegram_chat_id', '')");

    $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('telegram_modo', 'agendado')");
    $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('telegram_intervalo', '60')"); // Em minutos
    $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('telegram_ultimo_envio', '0')"); // Timestamp

    try {
        $pdo->exec("INSERT OR IGNORE INTO estoque_lotes (nome_produto, validade, quantidade)
                    SELECT nome_produto, validade, quantidade FROM estoque_produtos 
                    WHERE validade IS NOT NULL AND validade != '' AND quantidade > 0");
    } catch (Exception $e) {
    }

    try {
        $pdo->exec("
            INSERT INTO estoque_produtos (nome_produto)
            SELECT DISTINCT produto FROM compras 
            WHERE produto NOT IN (SELECT nome_produto FROM estoque_produtos)
            AND produto IS NOT NULL AND produto != ''
        ");
    } catch (Exception $e) {
        $erro_sync = $e->getMessage();
    }

} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$stmtCfg = $pdo->query("SELECT chave, valor FROM configuracoes");
$config = [];
while ($row = $stmtCfg->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['chave']] = $row['valor'];
}

// ==========================================
// FUNÇÃO CENTRAL DE DISPARO DO TELEGRAM
// ==========================================
function processarEnvioTelegram($pdo, $config)
{
    $botToken = $config['telegram_token'] ?? '';
    $chatId = $config['telegram_chat_id'] ?? '';
    $diasAlerta = intval($config['alerta_dias'] ?? 30);
    $modo = $config['telegram_modo'] ?? 'agendado';

    if (empty($botToken) || empty($chatId)) {
        return ['status' => 'ignorado', 'msg' => 'Faltam configurações do Telegram.'];
    }

    $horarioParaDisparar = null;
    $historico = json_decode($config['telegram_historico'] ?? '{"data":"","enviados":[]}', true);
    $hoje = date('Y-m-d');
    $agora = date('H:i');

    if (!isset($historico['data']) || $historico['data'] !== $hoje) {
        $historico = ['data' => $hoje, 'enviados' => []];
    }

    // Lógica 1: Se for por Horários Agendados
    if ($modo === 'agendado') {
        $horarios_str = $config['telegram_horarios'] ?? '';
        $horarios = array_filter(array_map('trim', explode(',', $horarios_str)));

        foreach ($horarios as $h) {
            if ($agora >= $h && !in_array($h, $historico['enviados'])) {
                $horarioParaDisparar = $h;
                break;
            }
        }
    }
    // Lógica 2: Se for por Intervalo de Tempo (Minutos)
    else if ($modo === 'intervalo') {
        $intervaloMinutos = intval($config['telegram_intervalo'] ?? 60);
        $ultimoEnvioTs = intval($config['telegram_ultimo_envio'] ?? 0);
        $agoraTs = time();

        // Verifica se a diferença entre agora e o último envio é maior que o intervalo escolhido
        if (($agoraTs - $ultimoEnvioTs) >= ($intervaloMinutos * 60)) {
            $horarioParaDisparar = $agora; // Usa a hora atual para avisar que disparou
        }
    }

    if ($horarioParaDisparar) {
        $stmt = $pdo->query("SELECT nome_produto, validade, quantidade FROM estoque_lotes WHERE validade <= date('now', '+" . $diasAlerta . " days') AND quantidade > 0 ORDER BY validade ASC");
        $expirando = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($expirando) > 0) {
            $mensagem = "⚠️ *RELATÓRIO DE VALIDADES (LOTES)* ⚠️\n_Itens vencendo nos próximos {$diasAlerta} dias_\n_(Notificação das {$horarioParaDisparar})_\n\n";
            foreach ($expirando as $item) {
                $diasRestantes = floor((strtotime($item['validade']) - strtotime($hoje)) / 86400);
                $dataBr = date('d/m/Y', strtotime($item['validade']));
                $statusTxt = ($diasRestantes < 0) ? "🔴 *VENCIDO*" : (($diasRestantes <= 30) ? "🟠 1 Mês" : (($diasRestantes <= 60) ? "🟡 2 Meses" : "🟢 3 Meses"));
                $mensagem .= "- {$item['nome_produto']}\n  Lote: {$dataBr} | Qtd: {$item['quantidade']} | {$statusTxt}\n\n";
            }

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $data = ['chat_id' => $chatId, 'text' => $mensagem, 'parse_mode' => 'Markdown'];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
        }

        // Atualiza os registros do banco de dados dependendo do modo
        if ($modo === 'agendado') {
            $historico['enviados'][] = $horarioParaDisparar;
            $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'telegram_historico'")->execute([json_encode($historico)]);
        } else {
            $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'telegram_ultimo_envio'")->execute([time()]);
        }

        return ['status' => 'enviado', 'horario' => $horarioParaDisparar];
    }

    return ['status' => 'aguardando', 'msg' => 'Aguardando o próximo ciclo.'];
}

if (isset($_GET['cron']) || php_sapi_name() === 'cli') {
    header('Content-Type: application/json');
    $resultado = processarEnvioTelegram($pdo, $config);
    echo json_encode($resultado);
    exit;
}

// ==========================================
// API INTERNA AJAX 
// ==========================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $acao = $_GET['acao'] ?? '';

    if ($acao === 'auto_telegram') {
        echo json_encode(processarEnvioTelegram($pdo, $config));
        exit;
    }

    if ($acao === 'testar_telegram') {
        $botToken = $_POST['telegram_token'] ?? '';
        $chatId = $_POST['telegram_chat_id'] ?? '';

        if (empty($botToken) || empty($chatId)) {
            echo json_encode(['status' => 'erro', 'msg' => 'Preencha o Token e o Chat ID!']);
            exit;
        }

        $mensagem = "✅ *TESTE DE INTEGRAÇÃO*\nSeu sistema está conseguindo enviar mensagens para o Telegram com sucesso!";
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $data = ['chat_id' => $chatId, 'text' => $mensagem, 'parse_mode' => 'Markdown'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $erro_curl = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            echo json_encode(['status' => 'erro', 'msg' => 'Erro de conexão: ' . $erro_curl]);
        } else {
            $response = json_decode($result, true);
            if (isset($response['ok']) && $response['ok'] === true) {
                echo json_encode(['status' => 'sucesso', 'msg' => 'Mensagem de teste enviada para o seu Telegram!']);
            } else {
                echo json_encode(['status' => 'erro', 'msg' => 'Telegram recusou: ' . ($response['description'] ?? 'Erro desconhecido')]);
            }
        }
        exit;
    }

    if ($acao === 'buscar_codigo') {
        $codigo = $_GET['codigo'] ?? '';
        $stmt = $pdo->prepare("SELECT p.*, COALESCE((SELECT SUM(quantidade) FROM estoque_lotes WHERE nome_produto = p.nome_produto), 0) as estoque_total FROM estoque_produtos p WHERE p.codigo_barras = ?");
        $stmt->execute([$codigo]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($produto ? ['status' => 'encontrado', 'produto' => $produto] : ['status' => 'nao_encontrado']);
        exit;
    }

    if ($acao === 'vincular_codigo') {
        $nome = $_POST['nome_produto'] ?? '';
        $codigo = $_POST['codigo'] ?? '';

        $sucesso = $pdo->prepare("UPDATE estoque_produtos SET codigo_barras = ? WHERE nome_produto = ?")->execute([$codigo, $nome]);

        try {
            $pdo->prepare("UPDATE compras SET codigo_barras = ? WHERE produto = ?")->execute([$codigo, $nome]);
        } catch (Exception $e) {
        }

        echo json_encode(['status' => $sucesso ? 'sucesso' : 'erro']);
        exit;
    }

    if ($acao === 'gravar_scan_remoto') {
        $codigo = $_POST['codigo'] ?? '';
        if ($codigo) {
            file_put_contents('codigo_remoto.txt', $codigo);
            echo json_encode(['status' => 'sucesso']);
        } else {
            echo json_encode(['status' => 'erro']);
        }
        exit;
    }

    if ($acao === 'ler_scan_remoto') {
        if (file_exists('codigo_remoto.txt')) {
            $codigo = trim(file_get_contents('codigo_remoto.txt'));
            if (!empty($codigo)) {
                unlink('codigo_remoto.txt');
                echo json_encode(['status' => 'sucesso', 'codigo' => $codigo]);
                exit;
            }
        }
        echo json_encode(['status' => 'vazio']);
        exit;
    }

    if ($acao === 'salvar_codigo_barras') {
        $nome = $_POST['nome_produto'] ?? '';
        $codigo = $_POST['codigo'] ?? '';

        $sucesso = $pdo->prepare("UPDATE estoque_produtos SET codigo_barras = ? WHERE nome_produto = ?")->execute([$codigo, $nome]);
        try {
            $pdo->prepare("UPDATE compras SET codigo_barras = ? WHERE produto = ?")->execute([$codigo, $nome]);
        } catch (Exception $e) {
        }

        echo json_encode(['status' => $sucesso ? 'sucesso' : 'erro', 'msg' => 'Código de barras salvo com sucesso!']);
        exit;
    }

    if ($acao === 'salvar_config') {
        $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'alerta_dias'")->execute([intval($_POST['alerta_dias'] ?? 30)]);
        $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'telegram_token'")->execute([$_POST['telegram_token'] ?? '']);
        $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'telegram_chat_id'")->execute([$_POST['telegram_chat_id'] ?? '']);
        $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'telegram_horarios'")->execute([$_POST['telegram_horarios'] ?? '']);

        // NOVAS LINHAS AQUI:
        $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'telegram_modo'")->execute([$_POST['telegram_modo'] ?? 'agendado']);
        $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'telegram_intervalo'")->execute([intval($_POST['telegram_intervalo'] ?? 60)]);

        echo json_encode(['status' => 'sucesso']);
        exit;
    }

    if ($acao === 'movimentar') {
        $nome = $_POST['nome_produto'] ?? '';
        $preco = floatval($_POST['preco_venda'] ?? 0);
        $qtd_movimento = intval($_POST['quantidade'] ?? 0);
        $operacao = $_POST['operacao'] ?? 'ajuste';
        $validade = !empty($_POST['validade']) ? $_POST['validade'] : '';
        $codigo_barras = $_POST['codigo_barras'] ?? '';

        $pdo->prepare("UPDATE estoque_produtos SET preco_venda = ? WHERE nome_produto = ?")->execute([$preco, $nome]);

        if (!empty($codigo_barras)) {
            $pdo->prepare("UPDATE estoque_produtos SET codigo_barras = ? WHERE nome_produto = ?")->execute([$codigo_barras, $nome]);
            try {
                $pdo->prepare("UPDATE compras SET codigo_barras = ? WHERE produto = ?")->execute([$codigo_barras, $nome]);
            } catch (Exception $e) {
            }
        }

        if ($operacao === 'entrada' || $operacao === 'ajuste') {

            $stmtCheck = $pdo->prepare("SELECT id FROM estoque_lotes WHERE nome_produto = ? AND validade = ?");
            $stmtCheck->execute([$nome, $validade]);
            $existe = $stmtCheck->fetch();

            if ($operacao === 'entrada') {
                if ($existe) {
                    $pdo->prepare("UPDATE estoque_lotes SET quantidade = quantidade + ? WHERE nome_produto = ? AND validade = ?")->execute([$qtd_movimento, $nome, $validade]);
                } else {
                    $pdo->prepare("INSERT INTO estoque_lotes (nome_produto, validade, quantidade) VALUES (?, ?, ?)")->execute([$nome, $validade, $qtd_movimento]);
                }
                echo json_encode(['status' => 'sucesso', 'msg' => 'Entrada registrada no lote!']);
            } else {
                if ($existe) {
                    $pdo->prepare("UPDATE estoque_lotes SET quantidade = ? WHERE nome_produto = ? AND validade = ?")->execute([$qtd_movimento, $nome, $validade]);
                } else {
                    $pdo->prepare("INSERT INTO estoque_lotes (nome_produto, validade, quantidade) VALUES (?, ?, ?)")->execute([$nome, $validade, $qtd_movimento]);
                }
                echo json_encode(['status' => 'sucesso', 'msg' => 'Lote ajustado e preço atualizado!']);
            }
            exit;
        } elseif ($operacao === 'saida') {
            $stmtLotes = $pdo->prepare("SELECT id, validade, quantidade FROM estoque_lotes WHERE nome_produto = ? AND quantidade > 0 ORDER BY validade ASC");
            $stmtLotes->execute([$nome]);
            $lotes = $stmtLotes->fetchAll(PDO::FETCH_ASSOC);

            $restanteParaBaixar = $qtd_movimento;
            foreach ($lotes as $lote) {
                if ($restanteParaBaixar <= 0)
                    break;

                if ($lote['quantidade'] >= $restanteParaBaixar) {
                    $pdo->prepare("UPDATE estoque_lotes SET quantidade = quantidade - ? WHERE id = ?")->execute([$restanteParaBaixar, $lote['id']]);
                    $restanteParaBaixar = 0;
                } else {
                    $pdo->prepare("UPDATE estoque_lotes SET quantidade = 0 WHERE id = ?")->execute([$lote['id']]);
                    $restanteParaBaixar -= $lote['quantidade'];
                }
            }

            if ($restanteParaBaixar > 0) {
                echo json_encode(['status' => 'sucesso', 'msg' => "Saída registrada, mas o estoque zerou antes de completar (Faltaram {$restanteParaBaixar} un)."]);
            } else {
                echo json_encode(['status' => 'sucesso', 'msg' => 'Saída realizada! Baixada automaticamente do lote mais antigo.']);
            }
            exit;
        }
    }
}

$produtosEstoque = $pdo->query("
    SELECT p.nome_produto, p.codigo_barras, p.preco_venda, 
           l.validade, l.quantidade as qtd_lote,
           COALESCE((SELECT SUM(quantidade) FROM estoque_lotes WHERE nome_produto = p.nome_produto), 0) as estoque_total
    FROM estoque_produtos p
    LEFT JOIN estoque_lotes l ON p.nome_produto = l.nome_produto AND l.quantidade > 0
    ORDER BY p.nome_produto ASC, l.validade ASC
")->fetchAll(PDO::FETCH_ASSOC);

$hoje_timestamp = strtotime(date('Y-m-d'));
$contagem = ['vencidos' => 0, 'mes1' => 0, 'mes2' => 0, 'mes3' => 0];

$stmtLotesDash = $pdo->query("SELECT validade FROM estoque_lotes WHERE quantidade > 0 AND validade IS NOT NULL AND validade != ''");
while ($l = $stmtLotesDash->fetch(PDO::FETCH_ASSOC)) {
    $dias_diff = floor((strtotime($l['validade']) - $hoje_timestamp) / 86400);
    if ($dias_diff < 0)
        $contagem['vencidos']++;
    elseif ($dias_diff <= 30)
        $contagem['mes1']++;
    elseif ($dias_diff <= 60)
        $contagem['mes2']++;
    elseif ($dias_diff <= 90)
        $contagem['mes3']++;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Estoque por Lotes</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f0f2f5;
            padding: 20px;
            margin: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .layout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
        }

        .box {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background: #fff;
        }

        .box h3 {
            margin-top: 0;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            color: #333;
            font-size: 1.1em;
        }

        .dashboard-validades {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .card-val {
            padding: 15px;
            border-radius: 8px;
            color: white;
            text-align: center;
            font-weight: bold;
        }

        .card-val h4 {
            margin: 0;
            font-size: 1em;
            opacity: 0.9;
        }

        .card-val span {
            font-size: 2em;
            display: block;
            margin-top: 5px;
        }

        .bg-vencido {
            background: #dc3545;
        }

        .bg-1mes {
            background: #e67e22;
        }

        .bg-2meses {
            background: #f1c40f;
            color: #333;
        }

        .bg-3meses {
            background: #27ae60;
        }

        #reader {
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #007bff;
            display: none;
        }

        .btn-scan {
            width: 100%;
            padding: 15px;
            background: #6f42c1;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            cursor: pointer;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #555;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-action {
            flex: 1;
            padding: 12px;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.1em;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #343a40;
            color: white;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            color: white;
            font-weight: bold;
        }

        .btn-selecionar {
            background: #007bff;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.2s;
        }

        .search-bar {
            width: 100%;
            padding: 12px;
            border: 2px solid #007bff;
            border-radius: 5px;
            font-size: 16px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }

        .lote-aviso {
            font-size: 0.8em;
            color: #dc3545;
            font-weight: bold;
            display: block;
            margin-top: 5px;
        }
    </style>
</head>

<body>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h2 style="margin:0;"><i class="fas fa-layer-group"></i> Lotes e Validades</h2>
            <div>
                <a href="relatorio_produtos.php" target="_blank"
                    style="padding: 8px 15px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin-right: 10px;"><i
                        class="fas fa-file-pdf"></i> Imprimir Relatório</a>
                <a href="index.php"
                    style="padding: 8px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;"><i
                        class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <div class="dashboard-validades">
            <div class="card-val bg-vencido">
                <h4>Lotes Vencidos</h4><span><?= $contagem['vencidos'] ?></span>
            </div>
            <div class="card-val bg-1mes">
                <h4>Venc. 1 Mês</h4><span><?= $contagem['mes1'] ?></span>
            </div>
            <div class="card-val bg-2meses">
                <h4>Venc. 2 Meses</h4><span><?= $contagem['mes2'] ?></span>
            </div>
            <div class="card-val bg-3meses">
                <h4>Venc. 3 Meses</h4><span><?= $contagem['mes3'] ?></span>
            </div>
        </div>

        <details style="background: #e9ecef; padding: 15px; border-radius: 8px; margin-bottom: 20px; cursor: pointer;">
    <summary style="font-weight: bold; color: #333;"><i class="fas fa-cog"></i> Configurar Telegram e Prazos
    </summary>

    <div style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; cursor: default;">
        <div><label>Antecedência (Dias)</label><input type="number" id="cfgDias" value="<?= htmlspecialchars($config['alerta_dias'] ?? '30') ?>"></div>
        <div><label>Bot Token</label><input type="text" id="cfgToken" value="<?= htmlspecialchars($config['telegram_token'] ?? '') ?>"></div>
        <div><label>Seu Chat ID</label><input type="text" id="cfgChat" value="<?= htmlspecialchars($config['telegram_chat_id'] ?? '') ?>"></div>
        
        <div style="grid-column: span 3; display: grid; grid-template-columns: 1fr 2fr; gap: 15px; align-items: start;">
            <div style="background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                <label style="font-weight:bold; color:#333; display:block; margin-bottom:10px;"><i class="fas fa-sliders-h"></i> Modo de Envio</label>
                <select id="cfgModo" onchange="alternarModoEnvio()" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; margin-bottom: 15px;">
                    <option value="agendado" <?= ($config['telegram_modo'] ?? 'agendado') == 'agendado' ? 'selected' : '' ?>>⌚ Horários Específicos</option>
                    <option value="intervalo" <?= ($config['telegram_modo'] ?? '') == 'intervalo' ? 'selected' : '' ?>>⏳ A cada X Tempo</option>
                </select>

                <div id="painelIntervalo" style="display: <?= ($config['telegram_modo'] ?? '') == 'intervalo' ? 'block' : 'none' ?>;">
                    <label>A cada quantos minutos?</label>
                    <input type="number" id="cfgIntervalo" value="<?= htmlspecialchars($config['telegram_intervalo'] ?? '60') ?>" min="1" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
                    <small style="color: #666;">Ex: 10 = Dez minutos, 120 = Duas Horas</small>
                </div>
            </div>

            <div id="painelHorarios" style="background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #ddd; display: <?= ($config['telegram_modo'] ?? 'agendado') == 'agendado' ? 'block' : 'none' ?>;">
                <label style="font-weight:bold; color:#333; display:block; margin-bottom:10px;"><i class="fas fa-clock"></i> Horários Agendados (Disparos Diários)</label>
                <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                    <input type="time" id="novoHorario" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: auto; font-size: 16px;">
                    <button onclick="adicionarHorario()" type="button" style="padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;"><i class="fas fa-plus"></i> Adicionar Hora</button>
                </div>
                <div id="listaHorarios" style="display:flex; flex-wrap: wrap; gap: 8px; min-height: 32px; align-items: center; padding: 10px; background: #f8f9fa; border-radius: 4px; border: 1px dashed #ccc;">
                </div>
                <input type="hidden" id="cfgHorarios" value="<?= htmlspecialchars($config['telegram_horarios'] ?? '08:00') ?>">
            </div>
        </div>

        <div style="grid-column: span 3; text-align: right; display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
            <button onclick="testarTelegram(this)" style="padding: 10px 20px; background: #17a2b8; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;"><i class="fas fa-paper-plane"></i> Testar Envio Agora</button>
            <button onclick="salvarConfiguracoes()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;"><i class="fas fa-save"></i> Salvar Tudo</button>
        </div>
    </div>
</details>

        <div class="layout-grid">
            <div class="box">
                <h3><i class="fas fa-barcode"></i> Buscar / Escanear</h3>
                <div style="display:flex; gap:10px; margin-bottom: 15px;">
                    <button class="btn-scan" id="btnStartScan" onclick="iniciarScanner()" style="margin-bottom:0;"><i class="fas fa-camera"></i> Câmera</button>
                    <a href="scan_celular.php" target="_blank" style="flex:1; padding: 15px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; text-align: center; font-weight: bold; font-size: 1.1em;"><i class="fas fa-mobile-alt"></i> Celular</a>
                </div>
                <div id="reader"></div>
                <div style="margin-top: 15px; display:flex; gap:10px;">
                    <input type="text" id="codigoManual" placeholder="EAN ou Código"
                        style="flex:1; padding:10px; border:1px solid #ccc; border-radius:5px;">
                    <button onclick="processarCodigo(document.getElementById('codigoManual').value)"
                        style="padding:10px 15px; background:#007bff; color:white; border:none; border-radius:5px; cursor:pointer;"><i
                            class="fas fa-search"></i></button>
                </div>

                <div id="divVincular"
                    style="display:none; margin-top:20px; padding:15px; background:#fff3cd; border:1px solid #ffeeba; border-radius:5px;">
                    <h4 style="margin-top:0; color:#856404;">Código Desconhecido</h4>
                    <p>O código <b id="lblNovoCodigo"></b> pertence a qual produto?</p>
                    <select id="selectVincularProduto" style="width:100%; padding:8px; margin-bottom:10px;">
                        <option value="">-- Selecione --</option>
                        <?php
                        $nomesDistintos = $pdo->query("SELECT nome_produto FROM estoque_produtos ORDER BY nome_produto")->fetchAll();
                        foreach ($nomesDistintos as $p): ?>
                                <option value="<?= htmlspecialchars($p['nome_produto']) ?>">
                                    <?= htmlspecialchars($p['nome_produto']) ?>
                                </option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="vincularCodigo()"
                        style="width:100%; padding:10px; background:#28a745; color:white; border:none; border-radius:5px; font-weight:bold;">Vincular</button>
                </div>
            </div>

            <div class="box" id="boxAcoes" style="opacity: 0.5; pointer-events: none;">
                <h3><i class="fas fa-edit"></i> Movimentar Lotes</h3>
                <div class="form-group">
                    <input type="text" id="prodNome" readonly
                        style="background:#e9ecef; font-weight:bold; width:100%; padding:10px; border:none; outline:none;">
                </div>

                <div class="form-group">
                    <label>Código de Barras</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="prodCodigoBarras" placeholder="Escanear ou digitar EAN" style="border: 2px solid #007bff; flex:1;">
                        <button onclick="salvarApenasCodigo()" style="background:#007bff; color:white; border:none; padding:10px; border-radius:5px; cursor:pointer;" title="Salvar apenas o código"><i class="fas fa-save"></i></button>
                    </div>
                </div>
                </div>

                <div class="layout-grid" style="margin-top:0; gap:10px; grid-template-columns: 1fr 1fr;">
                    <div class="form-group"><label>Preço Venda</label><input type="number" id="prodPreco" step="0.01">
                    </div>
                    <div class="form-group"><label>Validade do Lote</label><input type="date" id="prodValidade"
                            style="border: 2px solid #e67e22;">
                    </div>
                </div>

                <div class="layout-grid" style="margin-top:0; gap:10px; grid-template-columns: 1fr 1fr;">
                    <div class="form-group"><label>Estoque (Todos os lotes)</label><input type="number" id="prodEstoque"
                            readonly style="background:#e9ecef; text-align:center; font-weight:bold;"></div>
                    <div class="form-group"><label>Qtd a Movimentar</label><input type="number" id="prodQtdMovimento"
                            value="1" min="1" style="text-align:center; font-weight:bold;"></div>
                </div>

                <div class="action-buttons">
                    <button class="btn-action" style="background:#28a745;" onclick="movimentarEstoque('entrada')"><i
                            class="fas fa-plus"></i> ENTRADA</button>
                    <button class="btn-action" style="background:#dc3545;" onclick="movimentarEstoque('saida')"><i
                            class="fas fa-minus"></i> BAIXAR (Venda)</button>
                </div>
                <span class="lote-aviso">* A BAIXA desconta sempre do lote MAIS ANTIGO automaticamente. Você não precisa
                    selecionar a data.</span>

                <button onclick="movimentarEstoque('ajuste')"
                    style="width:100%; margin-top:15px; padding:10px; background:#ffc107; border:none; border-radius:5px; cursor:pointer; font-weight:bold;"><i
                        class="fas fa-save"></i> Ajustar Preço / Qtd Específica do Lote</button>
            </div>
        </div>

        <div style="margin-top: 40px; background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
            <input type="text" id="filtroNome" class="search-bar" onkeyup="salvarEFiltrar()"
                placeholder="🔍 Pesquisar produto...">
            <div style="overflow-x: auto;">
                <table id="tabelaEstoque">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Validade do Lote</th>
                            <th>Qtd. Lote</th>
                            <th>Qtd. Total</th>
                            <th>Preço</th>
                            <th style="text-align:center;">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $produtoAnterior = "";
                        foreach ($produtosEstoque as $p):
                            $badgeVal = "<span class='badge' style='background: #ccc; color:#333'>Sem Lotes</span>";
                            if ($p['qtd_lote'] !== null && empty($p['validade'])) {
                                $badgeVal = "<span class='badge' style='background: #718096; color:white'>Sem Validade</span>";
                            } elseif (!empty($p['validade'])) {
                                $dias = floor((strtotime($p['validade']) - $hoje_timestamp) / 86400);
                                $dataBr = date('d/m/Y', strtotime($p['validade']));
                                if ($dias < 0)
                                    $badgeVal = "<span class='badge bg-vencido'>$dataBr (Vencido)</span>";
                                elseif ($dias <= 30)
                                    $badgeVal = "<span class='badge bg-1mes'>$dataBr (1 Mês)</span>";
                                elseif ($dias <= 60)
                                    $badgeVal = "<span class='badge bg-2meses'>$dataBr (2 Meses)</span>";
                                elseif ($dias <= 90)
                                    $badgeVal = "<span class='badge bg-3meses'>$dataBr (3 Meses)</span>";
                                else
                                    $badgeVal = "<span class='badge' style='background:#17a2b8;'>$dataBr</span>";
                            }

                            $isNovoProduto = ($produtoAnterior !== $p['nome_produto']);
                            $produtoAnterior = $p['nome_produto'];

                            $qtdLoteShow = $p['qtd_lote'] !== null ? $p['qtd_lote'] : '-';
                            ?>
                                <tr style="<?= $isNovoProduto ? 'border-top: 3px solid #ccc;' : '' ?>">
                                    <td class="td-nome"
                                        style="font-weight: 500; color: <?= $isNovoProduto ? '#000' : '#888' ?>;">
                                        <?= $isNovoProduto ? htmlspecialchars($p['nome_produto']) : '↳ <i>Outro Lote</i>' ?>
                                    </td>
                                    <td><?= $badgeVal ?></td>
                                    <td style="font-weight:bold; color:#007bff;"><?= $qtdLoteShow ?></td>
                                    <td><?= $isNovoProduto ? "<b style='font-size:1.1em;'>" . $p['estoque_total'] . "</b>" : "" ?>
                                    </td>
                                    <td><?= $isNovoProduto ? "R$ " . number_format($p['preco_venda'], 2, ',', '.') : "" ?></td>
                                    <td style="text-align:center;">
                                        <button class="btn-selecionar" onclick="selecionarParaLote(
                                '<?= addslashes($p['nome_produto']) ?>', 
                                '<?= $p['preco_venda'] ?>', 
                                '<?= $p['validade'] ?>', 
                                '<?= $p['estoque_total'] ?>',
                                '<?= addslashes($p['codigo_barras'] ?? '') ?>'
                            )"><i class="fas fa-edit"></i></button>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <audio id="beepSound" src="https://www.soundjay.com/buttons/sounds/beep-07a.mp3" preload="auto"></audio>

    <script>
        function renderizarHorarios() {
            var container = document.getElementById('listaHorarios');
            var input = document.getElementById('cfgHorarios');
            container.innerHTML = '';
            var horarios = input.value.split(',').map(function (h) { return h.trim(); }).filter(function (h) { return h !== ''; });

            if (horarios.length === 0) {
                container.innerHTML = '<span style="color:#888; font-size:14px;">Nenhum horário agendado.</span>';
                return;
            }

            horarios.forEach(function (h, index) {
                var badge = document.createElement('span');
                badge.className = 'badge';
                badge.style.background = '#6f42c1';
                badge.style.fontSize = '14px';
                badge.style.display = 'inline-flex';
                badge.style.alignItems = 'center';
                badge.style.gap = '8px';
                badge.style.padding = '8px 12px';
                badge.style.margin = '2px';
                badge.innerHTML = '<i class="far fa-clock"></i> ' + h + ' <i class="fas fa-times" style="cursor:pointer; color:#ffb3b3;" onclick="removerHorario(' + index + ')" title="Remover Horário"></i>';
                container.appendChild(badge);
            });
        }

        function adicionarHorario() {
            let val = document.getElementById('novoHorario').value;
            if (!val) { alert('Selecione uma hora primeiro no campo!'); return; }
            let input = document.getElementById('cfgHorarios');
            let horarios = input.value.split(',').map(h => h.trim()).filter(h => h);

            if (!horarios.includes(val)) {
                horarios.push(val);
                horarios.sort();
                input.value = horarios.join(',');
                renderizarHorarios();
                document.getElementById('novoHorario').value = '';
            } else {
                alert('Este horário já está na lista!');
            }
        }

        function removerHorario(index) {
            let input = document.getElementById('cfgHorarios');
            let horarios = input.value.split(',').map(h => h.trim()).filter(h => h);
            horarios.splice(index, 1);
            input.value = horarios.join(',');
            renderizarHorarios();
        }

        window.addEventListener('load', function () {
            renderizarHorarios();
            fetch('gestao_estoque.php?ajax=1&acao=auto_telegram').catch(e => console.error(e));
        });

       // NOVA FUNÇÃO para trocar o que aparece na tela
function alternarModoEnvio() {
    var modo = document.getElementById('cfgModo').value;
    if (modo === 'agendado') {
        document.getElementById('painelHorarios').style.display = 'block';
        document.getElementById('painelIntervalo').style.display = 'none';
    } else {
        document.getElementById('painelHorarios').style.display = 'none';
        document.getElementById('painelIntervalo').style.display = 'block';
    }
}

// ATUALIZE sua função salvarConfiguracoes por esta:
function salvarConfiguracoes() {
    const fd = new FormData();
    fd.append('alerta_dias', document.getElementById('cfgDias').value);
    fd.append('telegram_token', document.getElementById('cfgToken').value);
    fd.append('telegram_chat_id', document.getElementById('cfgChat').value);
    fd.append('telegram_horarios', document.getElementById('cfgHorarios').value);
    
    // Novas variáveis
    fd.append('telegram_modo', document.getElementById('cfgModo').value);
    fd.append('telegram_intervalo', document.getElementById('cfgIntervalo').value);

    fetch('gestao_estoque.php?ajax=1&acao=salvar_config', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { 
            if (d.status === 'sucesso') { 
                alert('Configurações salvas!'); 
                location.reload(); 
            } 
        });
}

        function filtrarTabela() {
            let filter = document.getElementById("filtroNome").value.toUpperCase();
            let tr = document.getElementById("tabelaEstoque").getElementsByTagName("tr");
            let lastShownName = "";
            for (let i = 1; i < tr.length; i++) {
                let td = tr[i].getElementsByClassName("td-nome")[0];
                if (td) {
                    let text = td.innerText.toUpperCase();
                    if (!text.includes("OUTRO LOTE")) {
                        if (text.indexOf(filter) > -1) {
                            tr[i].style.display = "";
                            lastShownName = text;
                        } else {
                            tr[i].style.display = "none";
                            lastShownName = "";
                        }
                    } else {
                        tr[i].style.display = lastShownName !== "" ? "" : "none";
                    }
                }
            }
        }

        function selecionarParaLote(nome, preco, validade, estoque_total, codigo_barras) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            document.getElementById('boxAcoes').style.opacity = '1';
            document.getElementById('boxAcoes').style.pointerEvents = 'auto';
            document.getElementById('prodNome').value = nome;
            document.getElementById('prodPreco').value = preco;
            document.getElementById('prodEstoque').value = estoque_total;
            document.getElementById('prodValidade').value = validade || '';
            document.getElementById('prodQtdMovimento').value = 1;
            document.getElementById('prodCodigoBarras').value = codigo_barras || '';
        }

        function movimentarEstoque(operacao) {
            const nome = document.getElementById('prodNome').value;
            if (!nome) return;

            const fd = new FormData();
            fd.append('nome_produto', nome);
            fd.append('preco_venda', document.getElementById('prodPreco').value);
            fd.append('quantidade', document.getElementById('prodQtdMovimento').value);
            fd.append('validade', document.getElementById('prodValidade').value);
            fd.append('codigo_barras', document.getElementById('prodCodigoBarras').value);
            fd.append('operacao', operacao);

            fetch('gestao_estoque.php?ajax=1&acao=movimentar', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'sucesso') {
                        alert(data.msg);
                        window.location.reload();
                    } else alert(data.msg);
                });
        }

        function salvarApenasCodigo() {
            const nome = document.getElementById('prodNome').value;
            const codigo = document.getElementById('prodCodigoBarras').value;
            if (!nome) return;

            const fd = new FormData();
            fd.append('nome_produto', nome);
            fd.append('codigo', codigo);

            fetch('gestao_estoque.php?ajax=1&acao=salvar_codigo_barras', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    alert(data.msg);
                    if (data.status === 'sucesso') {
                        window.location.reload();
                    }
                });
        }

        let html5QrcodeScanner = null;
        function iniciarScanner() {
            document.getElementById('reader').style.display = 'block';
            document.getElementById('btnStartScan').style.display = 'none';
            html5QrcodeScanner = new Html5Qrcode("reader");
            html5QrcodeScanner.start(
                { facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 100 } },
                (decodedText) => {
                    document.getElementById('beepSound').play();
                    html5QrcodeScanner.stop().then(() => {
                        document.getElementById('reader').style.display = 'none';
                        document.getElementById('btnStartScan').style.display = 'block';
                        processarCodigo(decodedText);
                    });
                }, (error) => { }
            );
        }

        function processarCodigo(codigo) {
            if (!codigo) return;
            document.getElementById('codigoManual').value = codigo;
            fetch(`gestao_estoque.php?ajax=1&acao=buscar_codigo&codigo=${codigo}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'encontrado') {
                        document.getElementById('divVincular').style.display = 'none';
                        selecionarParaLote(data.produto.nome_produto, data.produto.preco_venda, '', data.produto.estoque_total);
                    } else {
                        document.getElementById('boxAcoes').style.opacity = '0.5';
                        document.getElementById('boxAcoes').style.pointerEvents = 'none';
                        document.getElementById('lblNovoCodigo').innerText = codigo;
                        document.getElementById('divVincular').style.display = 'block';
                    }
                });
        }

        function vincularCodigo() {
            const codigo = document.getElementById('lblNovoCodigo').innerText;
            const nomeProduto = document.getElementById('selectVincularProduto').value;
            if (!nomeProduto) { alert('Selecione um produto!'); return; }

            const fd = new FormData();
            fd.append('nome_produto', nomeProduto); fd.append('codigo', codigo);
            fetch('gestao_estoque.php?ajax=1&acao=vincular_codigo', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'sucesso') {
                        alert('Código vinculado com sucesso!');
                        document.getElementById('divVincular').style.display = 'none';
                        processarCodigo(codigo);
                    }
                });
        }

        function testarTelegram(btn) {
            const token = document.getElementById('cfgToken').value.trim();
            const chat = document.getElementById('cfgChat').value.trim();

            if (!token || !chat) {
                alert("⚠️ Preencha o Token e o Chat ID primeiro antes de testar!");
                return;
            }

            let textoOriginal = "Testar Envio Agora";
            if (btn) {
                textoOriginal = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                btn.disabled = true;
            }

            const fd = new FormData();
            fd.append('telegram_token', token);
            fd.append('telegram_chat_id', chat);

            fetch('gestao_estoque.php?ajax=1&acao=testar_telegram', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (btn) { btn.innerHTML = textoOriginal; btn.disabled = false; }
                    if (data.status === 'sucesso') alert('✅ ' + data.msg);
                    else alert('❌ ERRO: ' + data.msg);
                })
                .catch(e => {
                    if (btn) { btn.innerHTML = textoOriginal; btn.disabled = false; }
                    alert('❌ Erro inesperado na requisição.');
                });
        }

        setInterval(function() {
        // Gera um número único baseado no tempo atual para evitar que o navegador use o Cache
        let antiCache = new Date().getTime();
        
        fetch('gestao_estoque.php?ajax=1&acao=auto_telegram&_=' + antiCache)
            .then(res => res.json())
            .then(data => {
                if(data.status === 'enviado') {
                    console.log("Notificação automática enviada às: " + data.horario);
                }
            })
            .catch(e => console.error(e));
        }, 60000);

        setInterval(function() {
            let antiCache = new Date().getTime();
            fetch('gestao_estoque.php?ajax=1&acao=ler_scan_remoto&_=' + antiCache)
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'sucesso' && data.codigo) {
                        document.getElementById('beepSound').play();
                        processarCodigo(data.codigo);
                    }
                })
                .catch(e => {});
        }, 1500);

       
    // 1. Função que salva o texto digitado e depois chama a sua função original
    function salvarEFiltrar() {
        // Pega o valor do input
        const valorDigitado = document.getElementById('filtroNome').value;
        
        // Salva o valor na memória do navegador
        localStorage.setItem('filtroProdutoMemoria', valorDigitado);
        
        // Chama a sua função original que filtra a tabela
        filtrarTabela(); 
    }

    // 2. Quando a página carregar, verifica se tem algo salvo
    window.addEventListener('DOMContentLoaded', (event) => {
        const inputFiltro = document.getElementById('filtroNome');
        const valorSalvo = localStorage.getItem('filtroProdutoMemoria');

        // Se existir um valor salvo, coloca ele de volta no input
        if (valorSalvo) {
            inputFiltro.value = valorSalvo;
            
            // Opcional: Já chama a função para a tabela iniciar filtrada
            filtrarTabela(); 
        }
    });

    // Sua função original permanece a mesma (exemplo):
    // function filtrarTabela() { ... }

    </script>
</body>

</html>