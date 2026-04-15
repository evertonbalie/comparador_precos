<?php
require_once('banco.php');
$banco = new Banco();

$termoBusca = $_GET['busca'] ?? null;
$listaProdutos = $banco->listarTudo(); 
$top5 = []; 
$estatisticas = null;

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
        body { font-family: 'Segoe UI', sans-serif; background-color: #f0f2f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        /* Input de Busca */
        .search-container { display: flex; gap: 10px; margin-bottom: 20px; }
        input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; transition: 0.2s; }
        button:hover { background: #0056b3; }
        
        /* Botões */
        .btn-acao {
            border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.85em;
            text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; margin-left: 5px;
        }
        .btn-sefa { background-color: #e7f1ff; color: #007bff; border: 1px solid #b6d4fe; }
        .btn-sefa:hover { background-color: #007bff; color: white; }
        .btn-copiar { background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
        .btn-copiar:hover { background-color: #6c757d; color: white; }

        /* BOXES DE ESTATÍSTICA */
        .analise-grid { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
        .box-compra { flex: 1; background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 8px; }
        .box-venda { flex: 1; background: #cff4fc; border: 1px solid #b6effb; color: #055160; padding: 15px; border-radius: 8px; }
        .titulo-box { font-weight: bold; margin-bottom: 15px; display: block; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px; }
        .stats-row { display: flex; justify-content: space-between; text-align: center; }
        .valor-grande { font-size: 1.3em; font-weight: bold; margin: 5px 0; }

        /* ================= DESTAQUES (TOP 5) ================= */
        .podio-container { display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        
        /* 1º Lugar (Ouro) */
        .card-ouro { 
            background: #fff3cd; 
            border: 2px solid #ffecb5; 
            border-left: 6px solid #ffc107; 
            padding: 15px; 
            border-radius: 8px;
            color: #664d03;
        }
        .card-ouro h3 { margin-top: 0; color: #ffc107; text-shadow: 1px 1px 0px #997404; }
        .preco-campeao { font-size: 2em; font-weight: bold; color: #198754; margin: 5px 0; }

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
        .rank-badge { background: #6c757d; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8em; margin-right: 8px; }

        /* Tabela */
        .table-responsive { overflow-x: auto; margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background-color: #343a40; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .link-produto { text-decoration: none; color: #333; font-weight: 500; }
        .link-produto:hover { color: #007bff; }
    </style>
</head>
<body>

<div class="container">
    <h2 style="text-align:center"><i class="fas fa-chart-line"></i> Gestor de Preços</h2>

    <div class="search-container">
        <form style="display:flex; width:100%; gap:10px;">
            <input type="text" name="busca" placeholder="Pesquisar produto..." value="<?= htmlspecialchars($termoBusca) ?>">
            <button type="submit">Analisar</button>
            <?php if($termoBusca): ?>
                <a href="index.php" style="padding: 10px; color: red; text-decoration: none; font-weight:bold; display:flex; align-items:center;">X</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="text-align:center; margin-bottom: 20px;">
        <a href="scanner.php" style="background-color: #6f42c1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">
            <i class="fas fa-barcode"></i> Abrir Leitor
        </a>
    </div>

    <?php if ($termoBusca && $estatisticas && $estatisticas['minimo']): ?>
        
        <div class="analise-grid">
            <div class="box-compra">
                <span class="titulo-box">📊 Análise de Custo</span>
                <div class="stats-row">
                    <div><small>Mínimo</small><div class="valor-grande" style="color:#198754">R$ <?= number_format($estatisticas['minimo'], 2, ',', '.') ?></div></div>
                    <div><small>Média</small><div class="valor-grande">R$ <?= number_format($estatisticas['media'], 2, ',', '.') ?></div></div>
                    <div><small>Máximo</small><div class="valor-grande" style="color:#dc3545">R$ <?= number_format($estatisticas['maximo'], 2, ',', '.') ?></div></div>
                </div>
            </div>
            
            <div class="box-venda">
                <span class="titulo-box">💰 Sugestão Venda</span>
                <div class="stats-row">
                    <div>
                        <small>Promo <strong>(30%)</strong></small>
                        <div class="valor-grande">R$ <?= number_format($estatisticas['media'] * 1.3, 2, ',', '.') ?></div>
                    </div>
                    <div>
                        <small>Padrão <strong>(60%)</strong></small>
                        <div class="valor-grande" style="color:#0d6efd">R$ <?= number_format($estatisticas['media'] * 1.6, 2, ',', '.') ?></div>
                    </div>
                    <div>
                        <small>Premium <strong>(100%)</strong></small>
                        <div class="valor-grande">R$ <?= number_format($estatisticas['media'] * 2.0, 2, ',', '.') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if(count($dadosGrafico) > 1): ?>
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
                        <span style="font-size:0.8em;">(<?= date('d/m', strtotime($top5[0]['data_importacao'])) ?>)</span>
                    </div>

                    <?php if (!empty($top5[0]['chave'])): ?>
                        <div style="margin-top:10px;">
                            <button onclick="copiarChave('<?= $top5[0]['chave'] ?>')" class="btn-acao btn-copiar">
                                <i class="far fa-copy"></i> Copiar Chave
                            </button>
                            <a href="https://app.sefa.pa.gov.br/consulta-nfce/#/consulta?chave=<?= $top5[0]['chave'] ?>" target="_blank" class="btn-acao btn-sefa">
                                <i class="fas fa-external-link-alt"></i> Ver Nota
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php for($i = 1; $i < count($top5); $i++): ?>
                <div class="card-lista">
                    <div style="flex:1">
                        <span class="rank-badge"><?= $i + 1 ?></span>
                        <b><?= $top5[$i]['local'] ?></b> 
                        <br><small style="color:#666; margin-left:30px;"><?= mb_strimwidth($top5[$i]['produto'], 0, 40, "...") ?></small>
                    </div>
                    <div style="text-align:right">
                        <div style="font-weight:bold; font-size:1.1em; color:#333;">R$ <?= number_format($top5[$i]['preco'], 2, ',', '.') ?></div>
                        <?php if (!empty($top5[$i]['chave'])): ?>
                            <button onclick="copiarChave('<?= $top5[$i]['chave'] ?>')" class="btn-acao btn-copiar" style="margin-top:5px;">
                                <i class="far fa-copy"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endfor; ?>

            </div>
        <?php endif; ?>
        <?php elseif($termoBusca): ?>
        <p style="text-align:center; padding:20px; color:#666">Nenhum produto encontrado.</p>
    <?php endif; ?>

    <h3>📜 Histórico Geral</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Local</th>
                    <th>Produto</th>
                    <th>Valor</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaProdutos as $item): ?>
                <tr>
                    <td><?= date('d/m/y', strtotime($item['data_importacao'])) ?></td>
                    <td style="font-size: 0.9em;"><?= mb_strimwidth($item['local'], 0, 20, "...") ?></td>
                    <td>
                        <a href="?busca=<?= urlencode($item['produto']) ?>" class="link-produto">
                            <?= mb_strimwidth($item['produto'], 0, 30, "...") ?> <i class="fas fa-search" style="font-size:0.8em"></i>
                        </a>
                    </td>
                    <td style="color:#28a745; font-weight:bold;">R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                    <td>
                        <?php if(!empty($item['chave'])): ?>
                            <button onclick="copiarChave('<?= $item['chave'] ?>')" class="btn-acao btn-copiar"><i class="far fa-copy"></i></button>
                            <a href="https://app.sefa.pa.gov.br/consulta-nfce/#/consulta?chave=<?= $item['chave'] ?>" target="_blank" class="btn-acao btn-sefa"><i class="fas fa-external-link-alt"></i></a>
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
</script>
</body>
</html>