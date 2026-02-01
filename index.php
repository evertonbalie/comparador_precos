<?php
require_once('banco.php');
$banco = new Banco();

$termoBusca = $_GET['busca'] ?? null;
$listaProdutos = $banco->listarTudo(); // Pega os últimos 100 itens gerais
$top5 = []; 
$estatisticas = null;

// Variáveis do Gráfico
$labelsGrafico = [];
$dadosGrafico = [];

if ($termoBusca) {
    // Se tiver busca, carrega os dados específicos
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
    <title>Gestor de Preços Inteligente</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f0f2f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        /* Input de Busca */
        .search-container { display: flex; gap: 10px; margin-bottom: 20px; }
        input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; transition: 0.2s; }
        button:hover { background: #0056b3; }
        
        /* Links e Botões de Ação */
        .link-produto { text-decoration: none; color: #333; font-weight: 500; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
        .link-produto:hover { color: #007bff; }
        
        /* Botões Pequenos (Copiar/Abrir) */
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
        .btn-sefa { background-color: #e7f1ff; color: #007bff; border: 1px solid #b6d4fe; }
        .btn-sefa:hover { background-color: #007bff; color: white; }
        
        .btn-copiar { background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
        .btn-copiar:hover { background-color: #6c757d; color: white; }

        /* BOXES DE ANÁLISE */
        .analise-grid { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
        .box-compra { flex: 1; background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 8px; min-width: 300px; }
        .box-venda { flex: 1; background: #cff4fc; border: 1px solid #b6effb; color: #055160; padding: 15px; border-radius: 8px; min-width: 300px; }
        .titulo-box { font-weight: bold; margin-bottom: 15px; display: block; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px; }
        .stats-row { display: flex; justify-content: space-between; text-align: center; gap: 10px; }
        .stats-item { flex: 1; border-right: 1px solid rgba(0,0,0,0.1); }
        .stats-item:last-child { border-right: none; }
        .label-pequeno { font-size: 0.8em; text-transform: uppercase; }
        .valor-grande { font-size: 1.3em; font-weight: bold; margin: 5px 0; }

        /* PODIO */
        .podio-container { display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px; }
        .card-ouro { background: #d1e7dd; border: 1px solid #badbcc; border-left: 6px solid #198754; padding: 15px; border-radius: 8px; }
        .preco-destaque { font-size: 1.8em; font-weight: bold; color: #198754; margin: 5px 0; }
        .card-prata { background: #fff; border: 1px solid #ddd; border-left: 4px solid #6c757d; padding: 10px 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
        .rank-numero { font-weight: bold; color: #666; margin-right: 10px; }

        /* Tabela */
        .table-responsive { overflow-x: auto; margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background-color: #343a40; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
    </style>
</head>
<body>

<div class="container">
    <h2 style="text-align:center"><i class="fas fa-chart-line"></i> Gestor de Preços</h2>

    <div class="search-container">
        <form style="display:flex; width:100%; gap:10px;">
            <input type="text" name="busca" placeholder="Pesquisar produto (Ex: Arroz, Feijão)..." value="<?= htmlspecialchars($termoBusca) ?>">
            <button type="submit">Analisar</button>
            <?php if($termoBusca): ?>
                <a href="index.php" style="padding: 10px; color: red; text-decoration: none; font-weight:bold; display:flex; align-items:center;">X</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="text-align:center; margin-bottom: 20px;">
        <a href="scanner.php" style="background-color: #6f42c1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
            <i class="fas fa-barcode"></i> Abrir Leitor de Código
        </a>
    </div>

    <?php if ($termoBusca && $estatisticas && $estatisticas['minimo']): ?>
        
        <?php 
            $custo = $estatisticas['media'];
            $vendaMinima = $custo * 1.30; 
            $vendaPadrao = $custo * 1.60; 
            $vendaPremium = $custo * 2.00;
        ?>

        <div class="analise-grid">
            <div class="box-compra">
                <span class="titulo-box"><i class="fas fa-shopping-cart"></i> Análise de Compra (Custo)</span>
                <div class="stats-row">
                    <div class="stats-item">
                        <div class="label-pequeno">Melhor Preço</div>
                        <div class="valor-grande" style="color:#198754">R$ <?= number_format($estatisticas['minimo'], 2, ',', '.') ?></div>
                    </div>
                    <div class="stats-item">
                        <div class="label-pequeno">Média</div>
                        <div class="valor-grande">R$ <?= number_format($estatisticas['media'], 2, ',', '.') ?></div>
                    </div>
                    <div class="stats-item">
                        <div class="label-pequeno">Mais Caro</div>
                        <div class="valor-grande" style="color:#dc3545">R$ <?= number_format($estatisticas['maximo'], 2, ',', '.') ?></div>
                    </div>
                </div>
            </div>

            <div class="box-venda">
                <span class="titulo-box"><i class="fas fa-tags"></i> Sugestão de Venda</span>
                <div class="stats-row">
                    <div class="stats-item">
                        <div class="label-pequeno">Promo (30%)</div>
                        <div class="valor-grande">R$ <?= number_format($vendaMinima, 2, ',', '.') ?></div>
                    </div>
                    <div class="stats-item">
                        <div class="label-pequeno">Padrão (60%)</div>
                        <div class="valor-grande" style="font-weight:900; color:#0d6efd">R$ <?= number_format($vendaPadrao, 2, ',', '.') ?></div>
                    </div>
                    <div class="stats-item">
                        <div class="label-pequeno">Premium (100%)</div>
                        <div class="valor-grande">R$ <?= number_format($vendaPremium, 2, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if(count($dadosGrafico) > 1): ?>
            <div style="margin-bottom: 30px; border: 1px solid #ddd; padding: 10px; border-radius: 8px;">
                <canvas id="meuGrafico" style="max-height: 200px;"></canvas>
            </div>
        <?php endif; ?>

        <?php if (!empty($top5)): ?>
            <h3 style="color:#555">🏆 Onde comprar mais barato?</h3>
            <div class="podio-container">
                
                <?php if (isset($top5[0])): ?>
                <div class="card-ouro">
                    <h3><i class="fas fa-trophy" style="color: gold;"></i> 1º Lugar - Campeão</h3>
                    <div class="preco-destaque">R$ <?= number_format($top5[0]['preco'], 2, ',', '.') ?></div>
                    
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <span style="font-size:1.1em; font-weight:bold;"><?= $top5[0]['produto'] ?></span>
                        
                        <?php if (!empty($top5[0]['chave'])): ?>
                            <button onclick="copiarChave('<?= $top5[0]['chave'] ?>')" class="btn-acao btn-copiar" title="Copiar Chave">
                                <i class="far fa-copy"></i> Copiar Chave
                            </button>
                            <a href="https://app.sefa.pa.gov.br/consulta-nfce/#/consulta?chave=<?= $top5[0]['chave'] ?>" target="_blank" class="btn-acao btn-sefa">
                                <i class="fas fa-external-link-alt"></i> SEFA
                            </a>
                        <?php endif; ?>
                    </div>

                    <small style="display:block; margin-top:5px;">📍 <?= $top5[0]['local'] ?> em <?= date('d/m/Y', strtotime($top5[0]['data_importacao'])) ?></small>
                </div>
                <?php endif; ?>

                <?php for($i = 1; $i < count($top5); $i++): ?>
                <div class="card-prata">
                    <div>
                        <span class="rank-numero">#<?= $i + 1 ?></span> 
                        <span style="font-weight:500"><?= $top5[$i]['produto'] ?></span>
                        
                        <?php if (!empty($top5[$i]['chave'])): ?>
                            <button onclick="copiarChave('<?= $top5[$i]['chave'] ?>')" class="btn-acao btn-copiar" title="Copiar">
                                <i class="far fa-copy"></i>
                            </button>
                        <?php endif; ?>

                        <div style="font-size:0.85em; color:#666; margin-top:2px;"><?= $top5[$i]['local'] ?></div>
                    </div>
                    <div style="font-weight: bold;">R$ <?= number_format($top5[$i]['preco'], 2, ',', '.') ?></div>
                </div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php elseif($termoBusca): ?>
        <p style="text-align:center; color:#666; padding: 20px;">Nenhum produto encontrado com este nome.</p>
    <?php endif; ?>

    <h3>📜 Histórico de Importações</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Local</th>
                    <th>Produto</th>
                    <th>Valor</th>
                    <th>Chave / Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaProdutos as $item): ?>
                <tr>
                    <td><?= date('d/m/y', strtotime($item['data_importacao'])) ?></td>
                    
                    <td style="font-size: 0.9em; color:#555;">
                        <?= mb_strimwidth($item['local'], 0, 20, "...") ?>
                    </td>

                    <td>
                        <a href="?busca=<?= urlencode($item['produto']) ?>" class="link-produto" title="Analisar este produto">
                            <?= mb_strimwidth($item['produto'], 0, 30, "...") ?> <i class="fas fa-search" style="font-size:0.8em"></i>
                        </a>
                        <div style="font-size: 0.8em; color: #888;">Nota: <?= $item['numero_nota'] ?></div>
                    </td>

                    <td style="color:#28a745; font-weight:bold;">R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                    
                    <td>
                        <?php if(!empty($item['chave'])): ?>
                            <div style="display: flex; gap: 5px; align-items: center;">
                                
                                <button onclick="copiarChave('<?= $item['chave'] ?>')" class="btn-acao btn-copiar" title="Copiar chave">
                                    <i class="far fa-copy"></i>
                                </button>

                                <a href="https://app.sefa.pa.gov.br/consulta-nfce/#/consulta?chave=<?= $item['chave'] ?>" 
                                   target="_blank" 
                                   class="btn-acao btn-sefa"
                                   title="Abrir Nota na SEFA">
                                    <i class="fas fa-external-link-alt"></i> Abrir
                                </a>

                            </div>
                            <small style="color:#aaa; font-size: 0.7em;">
                                <?= substr($item['chave'], 0, 4) ?>...<?= substr($item['chave'], -4) ?>
                            </small>
                        <?php else: ?>
                            <span style="color:#ccc; font-size:0.8em;">--</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // 1. Script do Gráfico
    <?php if ($termoBusca && count($dadosGrafico) > 0): ?>
    const ctx = document.getElementById('meuGrafico').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labelsGrafico) ?>,
            datasets: [{
                label: 'Histórico de Preço',
                data: <?= json_encode($dadosGrafico) ?>,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
    <?php endif; ?>

    // 2. Função Copiar Chave (Compatível com PC e Celular)
    function copiarChave(chave) {
        if (navigator.clipboard && window.isSecureContext) {
            // Método Moderno
            navigator.clipboard.writeText(chave).then(() => {
                alert('✅ Chave copiada!\n' + chave);
            }).catch(err => {
                prompt("Erro no automático. Copie manualmente:", chave);
            });
        } else {
            // Método Antigo (Fallback)
            let textArea = document.createElement("textarea");
            textArea.value = chave;
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                alert('✅ Chave copiada!\n' + chave);
            } catch (err) {
                prompt("Copie manualmente:", chave);
            }
            document.body.removeChild(textArea);
        }
    }
</script>

</body>
</html>