<?php
require_once('banco.php');
$banco = new Banco();

$termoBusca = $_GET['busca'] ?? null;
$sort = $_GET['sort'] ?? 'data_emissao';
$dir = $_GET['dir'] ?? 'DESC';
$listaProdutos = $banco->listarTudo($sort, $dir);
$top5 = [];
$estatisticas = null;

function buildSortLink($column, $currentSort, $currentDir, $termoBusca)
{
    $newDir = ($currentSort === $column && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $url = "?sort=$column&dir=$newDir";
    if ($termoBusca) {
        $url .= "&busca=" . urlencode($termoBusca);
    }
    $icon = "";
    if ($currentSort === $column) {
        $icon = $currentDir === 'ASC' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
    } else {
        $icon = ' <i class="fas fa-sort" style="color:#ccc;"></i>';
    }
    return ['url' => $url, 'icon' => $icon];
}

// Variáveis do Gráfico
$labelsGrafico = [];
$dadosGrafico = [];

if ($termoBusca) {
    $top5 = $banco->buscarTop5($termoBusca);
    $estatisticas = $banco->buscarEstatisticas($termoBusca);
    $historico = $banco->buscarHistoricoGrafico($termoBusca);

    foreach ($historico as $h) {
        $labelsGrafico[] = date('d/m', strtotime($h['data_importacao']));
        $dadosGrafico[] = $h['preco'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Preços</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f0f2f5;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Input de Busca */
        .search-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.2s;
        }

        button:hover {
            background: #0056b3;
        }

        /* Botões */
        .btn-acao {
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: 0.2s;
            margin-left: 5px;
        }

        .btn-sefa {
            background-color: #e7f1ff;
            color: #007bff;
            border: 1px solid #b6d4fe;
        }

        .btn-sefa:hover {
            background-color: #007bff;
            color: white;
        }

        .btn-copiar {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }

        .btn-copiar:hover {
            background-color: #6c757d;
            color: white;
        }

        /* BOXES DE ESTATÍSTICA */
        .analise-grid {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .box-compra {
            flex: 1;
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
        }

        .box-venda {
            flex: 1;
            background: #cff4fc;
            border: 1px solid #b6effb;
            color: #055160;
            padding: 15px;
            border-radius: 8px;
        }

        .titulo-box {
            font-weight: bold;
            margin-bottom: 15px;
            display: block;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding-bottom: 5px;
        }

        .stats-row {
            display: flex;
            justify-content: space-between;
            text-align: center;
        }

        .valor-grande {
            font-size: 1.3em;
            font-weight: bold;
            margin: 5px 0;
        }

        /* ================= DESTAQUES (TOP 5) ================= */
        .podio-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        /* 1º Lugar (Ouro) */
        .card-ouro {
            background: #fff3cd;
            border: 2px solid #ffecb5;
            border-left: 6px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            color: #664d03;
        }

        .card-ouro h3 {
            margin-top: 0;
            color: #ffc107;
            text-shadow: 1px 1px 0px #997404;
        }

        .preco-campeao {
            font-size: 2em;
            font-weight: bold;
            color: #198754;
            margin: 5px 0;
        }

        /* 2º ao 5º Lugar */
        .card-lista {
            background: #fff;
            border: 1px solid #ddd;
            border-left: 4px solid #6c757d;
            padding: 10px 15px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .rank-badge {
            background: #6c757d;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            margin-right: 8px;
        }

        /* Tabela */
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
            border-top: 2px solid #eee;
            padding-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background-color: #343a40;
            color: white;
            padding: 10px;
            text-align: left;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .link-produto {
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }

        .link-produto:hover {
            color: #007bff;
        }

        /* Janela de Log Estilo Terminal */
        #modalLog {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
        }

        .log-content {
            background-color: #1e1e1e;
            color: #00ff00;
            margin: 5% auto;
            padding: 20px;
            width: 80%;
            max-width: 800px;
            height: 400px;
            border-radius: 8px;
            font-family: 'Courier New', Courier, monospace;
            overflow-y: auto;
            border: 1px solid #444;
        }

        .btn-importar {
            background-color: #28a745;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-importar:hover {
            background-color: #218838;
        }

        /* Modal de Estoque */
        #modalEstoque {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
        }

        .estoque-content {
            background: white;
            max-width: 400px;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }

        log .form-group input {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .btn-grid {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-est {
            flex: 1;
            padding: 10px;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div style="text-align:center; margin-bottom: 20px;">
        <button onclick="iniciarImportacao()" class="btn-importar">
            <i class="fas fa-cloud-download-alt"></i> IMPORTAR DA SEFA (SELENIUM)
        </button>
        <a href="gestao_estoque.php">
            <button class="btn-importar">
                <i class="fas fa-cloud-download-alt"></i>GESTÃO DE ESTOQUE
            </button>
        </a>
    </div>

    <div id="modalLog">
        <div id="modalEstoque">
            <div class="estoque-content">
                <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">📦 Gerenciar Produto</h3>
                <p id="est_nome_produto" style="font-size: 0.9em; color: #555; font-weight: bold; margin-bottom: 15px;">
                </p>

                <div class="form-group">
                    <label>Preço de Venda (R$)</label>
                    <input type="number" id="est_preco" step="0.01" placeholder="Ex: 15.50">
                </div>

                <div class="form-group">
                    <label>Estoque Atual</label>
                    <input type="number" id="est_qtd_atual" readonly
                        style="background-color: #e9ecef; font-weight: bold; font-size: 1.2em; text-align: center;">
                </div>

                <div class="form-group">
                    <label>Movimentar (Entrada/Saída/Ajuste)</label>
                    <input type="number" id="est_movimento" placeholder="Digite a quantidade...">
                </div>

                <div class="btn-grid">
                    <button class="btn-est" style="background: #28a745;" onclick="salvarEstoque('entrada')"><i
                            class="fas fa-plus"></i> Entrada</button>
                    <button class="btn-est" style="background: #dc3545;" onclick="salvarEstoque('saida')"><i
                            class="fas fa-minus"></i> Saída</button>
                    <button class="btn-est" style="background: #ffc107; color: #000;"
                        onclick="salvarEstoque('ajuste')"><i class="fas fa-pen"></i> Fixar</button>
                </div>

                <button onclick="fecharEstoque()"
                    style="width: 100%; margin-top: 15px; padding: 10px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Cancelar</button>
            </div>
        </div>
        <div class="log-content">
            <div
                style="display:flex; justify-content: space-between; border-bottom: 1px solid #333; margin-bottom: 10px; padding-bottom: 5px;">
                <span><i class="fas fa-terminal"></i> Console de Importação</span>
                <button onclick="fecharLog()"
                    style="background:none; color:red; border:none; cursor:pointer; font-weight:bold;">X FECHAR</button>
            </div>
            <div id="consoleSaida"></div>
        </div>
    </div>
    <div id="modalEstoque">
        <div class="estoque-content">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">📦 Gerenciar Produto</h3>
            <p id="est_nome_produto" style="font-size: 0.9em; color: #555; font-weight: bold; margin-bottom: 15px;"></p>

            <div class="form-group">
                <label>Preço de Venda (R$)</label>
                <input type="number" id="est_preco" step="0.01" placeholder="Ex: 15.50">
            </div>

            <div class="form-group">
                <label>Estoque Atual</label>
                <input type="number" id="est_qtd_atual" readonly
                    style="background-color: #e9ecef; font-weight: bold; font-size: 1.2em; text-align: center;">
            </div>

            <div class="form-group">
                <label>Movimentar (Entrada/Saída/Ajuste)</label>
                <input type="number" id="est_movimento" placeholder="Digite a quantidade...">
            </div>

            <div class="btn-grid">
                <button class="btn-est" style="background: #28a745;" onclick="salvarEstoque('entrada')"><i
                        class="fas fa-plus"></i> Entrada</button>
                <button class="btn-est" style="background: #dc3545;" onclick="salvarEstoque('saida')"><i
                        class="fas fa-minus"></i> Saída</button>
                <button class="btn-est" style="background: #ffc107; color: #000;" onclick="salvarEstoque('ajuste')"><i
                        class="fas fa-pen"></i> Fixar</button>
            </div>

            <button onclick="fecharEstoque()"
                style="width: 100%; margin-top: 15px; padding: 10px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Cancelar</button>
        </div>
    </div>

    <div class="container">
        <h2 style="text-align:center"><i class="fas fa-chart-line"></i> Gestor de Preços</h2>

        <div class="search-container">
            <form style="display:flex; width:100%; gap:10px;">
                <input type="text" name="busca" placeholder="Pesquisar produto..." value="<?= ($termoBusca) ?>">
                <button type="submit">Analisar</button>
                <?php if ($termoBusca): ?>
                    <a href="index.php"
                        style="padding: 10px; color: red; text-decoration: none; font-weight:bold; display:flex; align-items:center;">X</a>
                <?php endif; ?>
            </form>
        </div>

        <div style="text-align:center; margin-bottom: 20px;">
            <a href="scanner.php"
                style="background-color: #6f42c1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                <i class="fas fa-barcode"></i> Abrir Leitor
            </a>
        </div>

        <?php if ($termoBusca && $estatisticas && $estatisticas['minimo']): ?>

            <div class="analise-grid">
                <div class="box-compra">
                    <span class="titulo-box">📊 Análise de Custo</span>
                    <div class="stats-row">
                        <div><small>Mínimo</small>
                            <div class="valor-grande" style="color:#198754">R$
                                <?= number_format($estatisticas['minimo'], 2, ',', '.') ?>
                            </div>
                        </div>
                        <div><small>Média</small>
                            <div class="valor-grande">R$ <?= number_format($estatisticas['media'], 2, ',', '.') ?></div>
                        </div>
                        <div><small>Máximo</small>
                            <div class="valor-grande" style="color:#dc3545">R$
                                <?= number_format($estatisticas['maximo'], 2, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box-venda">
                    <span class="titulo-box">💰 Sugestão Venda</span>
                    <div class="stats-row">
                        <div>
                            <small>Promo <strong>(30%)</strong></small>
                            <div class="valor-grande">R$ <?= number_format($estatisticas['media'] * 1.3, 2, ',', '.') ?>
                            </div>
                        </div>
                        <div>
                            <small>Padrão <strong>(60%)</strong></small>
                            <div class="valor-grande" style="color:#0d6efd">R$
                                <?= number_format($estatisticas['media'] * 1.6, 2, ',', '.') ?>
                            </div>
                        </div>
                        <div>
                            <small>Premium <strong>(100%)</strong></small>
                            <div class="valor-grande">R$ <?= number_format($estatisticas['media'] * 2.0, 2, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($dadosGrafico) > 1): ?>
                <div style="margin-bottom: 30px; height: 200px;">
                    <canvas id="meuGrafico"></canvas>
                </div>
            <?php endif; ?>

            <?php if (!empty($top5)): ?>
                <h3 style="color:#555;">🏆 Melhores Preços Encontrados</h3>
                <div class="podio-container">

                    <?php if (isset($top5[0])): ?>
                        <div class="card-ouro">
                            <h3><i class="fas fa-crown"></i> CAMPEÃO: MELHOR PREÇO</h3>
                            <div class="preco-campeao">R$ <?= number_format($top5[0]['preco'], 2, ',', '.') ?></div>
                            <div style="font-size:1.1em; font-weight:bold; margin-bottom:5px;"><?= $top5[0]['produto'] ?></div>

                            <div style="margin-bottom:5px; color:#555;">
                                <i class="fas fa-map-marker-alt"></i> <?= $top5[0]['local'] ?>
                                <span style="font-size:0.8em;">(<?= date('d/m/y', strtotime($top5[0]['data_importacao'])) ?>)</span>
                            </div>

                            <?php if (!empty($top5[0]['chave'])): ?>
                                <div style="margin-top:10px;">
                                    <button onclick="copiarChave('<?= $top5[0]['chave'] ?>')" class="btn-acao btn-copiar">
                                        <i class="far fa-copy"></i> Copiar Chave
                                    </button>
                                    <a href="https://app.sefa.pa.gov.br/consulta-nfce/#/consulta?chave=<?= $top5[0]['chave'] ?>"
                                        target="_blank" class="btn-acao btn-sefa">
                                        <i class="fas fa-external-link-alt"></i> Ver Nota
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php for ($i = 1; $i < count($top5); $i++): ?>
                        <div class="card-lista">
                            <div style="flex:1">
                                <span class="rank-badge"><?= $i + 1 ?></span>
                                <b><?= $top5[$i]['local'] ?></b>
                                <br><small
                                    style="color:#666; margin-left:30px;"><?= mb_strimwidth($top5[$i]['produto'], 0, 40, "...") ?></small>
                            </div>
                            <div style="text-align:center; margin-right: 150px;">
                                <label for="">Data Emissão: </label>
                                <b><?= date('d/m/y', strtotime($top5[$i]['data_emissao'])) ?></b>
                            </div>
                            <div style="text-align:right">
                                <div style="font-weight:bold; font-size:1.1em; color:#333;">R$
                                    <?= number_format($top5[$i]['preco'], 2, ',', '.') ?>
                                </div>
                                <?php if (!empty($top5[$i]['chave'])): ?>
                                    <button onclick="copiarChave('<?= $top5[$i]['chave'] ?>')" class="btn-acao btn-copiar"
                                        style="margin-top:5px;">
                                        <i class="far fa-copy"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>

                </div>
            <?php endif; ?>
        <?php elseif ($termoBusca): ?>
            <p style="text-align:center; padding:20px; color:#666">Nenhum produto encontrado.</p>
        <?php endif; ?>

        <h3>📜 Histórico Geral</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <?php
                        $linkEmissao = buildSortLink('data_emissao', $sort, $dir, $termoBusca);
                        $linkLocal = buildSortLink('local', $sort, $dir, $termoBusca);
                        $linkProduto = buildSortLink('produto', $sort, $dir, $termoBusca);
                        $linkPreco = buildSortLink('preco', $sort, $dir, $termoBusca);
                        ?>
                        <th style="width: 120px; text-align: center;"><a href="<?= $linkEmissao['url'] ?>"
                                style="color:white; text-decoration:none;">Data Emissão <?= $linkEmissao['icon'] ?></a>
                        </th>
                        <th><a href="<?= $linkLocal['url'] ?>" style="color:white; text-decoration:none;">Local
                                <?= $linkLocal['icon'] ?></a></th>
                        <th><a href="<?= $linkProduto['url'] ?>" style="color:white; text-decoration:none;">Produto
                                <?= $linkProduto['icon'] ?></a></th>
                        <th><a href="<?= $linkPreco['url'] ?>" style="color:white; text-decoration:none;">Valor
                                Compra<?= $linkPreco['icon'] ?></a></th>
                        <th><a href="<?= $linkPreco['url'] ?>" style="color:white; text-decoration:none;">Valor
                                Venda<?= $linkPreco['icon'] ?></a></th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listaProdutos as $item): ?>
                        <tr>
                            
                            <td style="text-align: center;"><?= date('d/m/y', strtotime($item['data_emissao'])) ?></td>
                            <td style="font-size: 0.9em;"><?= mb_strimwidth($item['local'], 0, 20, "...") ?></td>
                            <td>
                                <a href="?busca=<?= urlencode($item['produto']) ?>" class="link-produto">
                                    <?= mb_strimwidth($item['produto'], 0, 30, "...") ?> <i class="fas fa-search"
                                        style="font-size:0.8em"></i>
                                </a>
                            </td>
                            <td style="color:#28a747; font-weight:bold;">R$
                                <?= number_format($item['preco'], 2, ',', '.') ?>
                            </td>
                            <td style="color:#28a747; font-weight:bold;">R$
                                <?= number_format($item['preco_venda'], 2, ',', '.') ?>
                            </td>
                            <td>
                                <?php if (!empty($item['chave'])): ?>
                                    <button onclick="copiarChave('<?= $item['chave'] ?>')" class="btn-acao btn-copiar"><i
                                            class="far fa-copy"></i></button>
                                    <a href="https://app.sefa.pa.gov.br/consulta-nfce/#/consulta?chave=<?= $item['chave'] ?>"
                                        target="_blank" class="btn-acao btn-sefa"><i class="fas fa-external-link-alt"></i></a>
                                <?php else: ?>
                                    <span style="color:#ccc;">--</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!--  <button onclick="abrirEstoque('<?= htmlspecialchars(addslashes($item['produto'])) ?>')"
                                    class="btn-acao"
                                    style="background-color: #17a2b8; color: white; border: 1px solid #117a8b;"
                                    title="Gerenciar Estoque">
                                    <i class="fas fa-box"></i>
                                </button> -->

                                <?php if (!empty($item['chave'])): ?>
                                    <!-- <button onclick="copiarChave('<?= $item['chave'] ?>')" class="btn-acao btn-copiar"><i
                                            class="far fa-copy"></i></button>
                                    <a href="https://app.sefa.pa.gov.br/consulta-nfce/#/consulta?chave=<?= $item['chave'] ?>"
                                        target="_blank" class="btn-acao btn-sefa"><i class="fas fa-external-link-alt"></i></a> -->
                                <?php else: ?>
                                    <span style="color:#ccc;">--</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        <?php if ($termoBusca && count($dadosGrafico) > 0): ?>
            const ctx = document.getElementById('meuGrafico').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($labelsGrafico) ?>,
                    datasets: [{ label: 'Preço', data: <?= json_encode($dadosGrafico) ?>, borderColor: '#007bff', backgroundColor: 'rgba(0,123,255,0.1)', fill: true }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        <?php endif; ?>

        function copiarChave(chave) {
            navigator.clipboard.writeText(chave).then(() => { alert('✅ Chave copiada!'); }).catch(err => { prompt("Copie:", chave); });
        }


        function iniciarImportacao() {
            const modal = document.getElementById('modalLog');
            const saida = document.getElementById('consoleSaida');

            modal.style.display = 'block';
            saida.innerHTML = '🔵 Iniciando script Selenium... Aguarde o navegador abrir.<br>';

            // Abre conexão com o PHP que executa o comando
            const evtSource = new EventSource("executar_importacao.php");

            evtSource.onmessage = function (event) {
                if (event.data === "END") {
                    evtSource.close();
                    saida.innerHTML += "<br><b><span style='color:white'>🎉 PROCESSO CONCLUÍDO!</span></b> Reatualizando em 3s...";
                    setTimeout(() => { window.location.reload(); }, 3000);
                    return;
                }

                // Adiciona a linha no console e faz scroll para o final
                saida.innerHTML += event.data + "<br>";
                const logWindow = document.querySelector('.log-content');
                logWindow.scrollTop = logWindow.scrollHeight;
            };

            evtSource.onerror = function () {
                saida.innerHTML += "<br><span style='color:red'>🔴 Erro de conexão com o servidor.</span>";
                evtSource.close();
            };
        }

        function fecharLog() {
            document.getElementById('modalLog').style.display = 'none';
        }

        let produtoAtualEstoque = "";

        // Abre o modal e busca os dados atuais do banco
        function abrirEstoque(nomeProduto) {
            produtoAtualEstoque = nomeProduto;
            document.getElementById('est_nome_produto').innerText = nomeProduto;
            document.getElementById('modalEstoque').style.display = 'block';

            // Limpa os campos enquanto carrega
            document.getElementById('est_preco').value = '';
            document.getElementById('est_qtd_atual').value = 'Carregando...';
            document.getElementById('est_movimento').value = '';

            fetch('api_estoque.php?acao=buscar&produto=' + encodeURIComponent(nomeProduto))
                .then(res => res.json())
                .then(data => {
                    document.getElementById('est_preco').value = data.preco_venda || '';
                    document.getElementById('est_qtd_atual').value = data.quantidade || '0';
                });
        }

        function fecharEstoque() {
            document.getElementById('modalEstoque').style.display = 'none';
        }

        // Salva as alterações
        function salvarEstoque(tipoOperacao) {
            const precoVenda = document.getElementById('est_preco').value;
            const qtdMovimento = document.getElementById('est_movimento').value;

            const dados = new FormData();
            dados.append('acao', 'salvar');
            dados.append('produto', produtoAtualEstoque);
            dados.append('preco_venda', precoVenda);
            dados.append('quantidade', qtdMovimento);
            dados.append('operacao', tipoOperacao);

            fetch('api_estoque.php', { method: 'POST', body: dados })
                .then(res => res.text())
                .then(resposta => {
                    alert('✅ Estoque atualizado com sucesso!');
                    abrirEstoque(produtoAtualEstoque); // Recarrega os dados na tela
                    document.getElementById('est_movimento').value = ''; // Limpa o input
                })
                .catch(err => alert('Erro ao salvar: ' + err));
        }
    </script>
</body>

</html>