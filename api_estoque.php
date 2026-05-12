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
    <title>Gestor de Preços e Estoque</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f0f2f5; padding: 20px; margin: 0; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        /* Painel de Ações do Topo */
        .top-actions { display: flex; justify-content: center; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .btn-main { padding: 12px 25px; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; border: none; cursor: pointer; font-size: 15px; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-import { background-color: #28a745; }
        .btn-import:hover { background-color: #218838; }
        .btn-scanner { background-color: #6f42c1; }
        .btn-scanner:hover { background-color: #5a32a3; }
        
        /* Input de Busca */
        .search-container { display: flex; gap: 10px; margin-bottom: 25px; }
        .search-container input { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
        .search-container button { padding: 12px 25px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; transition: 0.2s; font-weight: bold; }
        .search-container button:hover { background: #0056b3; }
        
        /* Botões da Tabela */
        .btn-acao { border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 0.9em; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s; margin-right: 4px; }
        .btn-estoque { background-color: #17a2b8; color: white; border: 1px solid #117a8b; }
        .btn-estoque:hover { background-color: #138496; }
        .btn-sefa { background-color: #e7f1ff; color: #007bff; border: 1px solid #b6d4fe; }
        .btn-sefa:hover { background-color: #007bff; color: white; }
        .btn-copiar { background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
        .btn-copiar:hover { background-color: #6c757d; color: white; }

        /* Boxes de Estatística */
        .analise-grid { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; }
        .box-compra { flex: 1; background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 8px; }
        .box-venda { flex: 1; background: #cff4fc; border: 1px solid #b6effb; color: #055160; padding: 15px; border-radius: 8px; }
        .titulo-box { font-weight: bold; margin-bottom: 15px; display: block; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px; }
        .stats-row { display: flex; justify-content: space-between; text-align: center; }
        .valor-grande { font-size: 1.3em; font-weight: bold; margin: 5px 0; }

        /* Top 5 e Tabela */
        .podio-container { display: flex; flex-direction: column; gap: 10px; margin-bottom: 30px; border-top: 1px solid #eee; padding-top: 20px; }
        .card-ouro { background: #fff3cd; border: 2px solid #ffecb5; border-left: 6px solid #ffc107; padding: 15px; border-radius: 8px; color: #664d03; }
        .card-ouro h3 { margin-top: 0; color: #ffc107; text-shadow: 1px 1px 0px #997404; }
        .preco-campeao { font-size: 2em; font-weight: bold; color: #198754; margin: 5px 0; }
        .card-lista { background: #fff; border: 1px solid #ddd; border-left: 4px solid #6c757d; padding: 10px 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
        .rank-badge { background: #6c757d; color: white; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8em; margin-right: 8px; }
        
        .table-responsive { overflow-x: auto; margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background-color: #343a40; color: white; padding: 12px 10px; text-align: left; }
        td { padding: 12px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .link-produto { text-decoration: none; color: #333; font-weight: 500; }
        .link-produto:hover { color: #007bff; }

        /* Modais */
        .modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); }
        
        /* Modal Log Selenium */
        .log-content { background-color: #1e1e1e; color: #00ff00; margin: 5% auto; padding: 20px; width: 80%; max-width: 800px; height: 400px; border-radius: 8px; font-family: monospace; overflow-y: auto; border: 1px solid #444; }
        
        /* Modal Estoque */
        .estoque-content { background: white; max-width: 400px; margin: 8% auto; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
        .form-group input { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        .btn-grid { display: flex; gap: 10px; margin-top: 20px; }
        .btn-est { flex: 1; padding: 10px; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

<div id="modalLog" class="modal-overlay">
    <div class="log-content">
        <div style="display:flex; justify-content: space-between; border-bottom: 1px solid #333; margin-bottom: 10px; padding-bottom: 5px;">
            <span><i class="fas fa-terminal"></i> Console de Importação</span>
            <button onclick="fecharLog()" style="background:none; color:red; border:none; cursor:pointer; font-weight:bold;">X FECHAR</button>
        </div>
        <div id="consoleSaida"></div>
    </div>
</div>

<div id="modalEstoque" class="modal-overlay">
    <div class="estoque-content">
        <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">📦 Gerenciar Estoque</h3>
        <p id="est_nome_produto" style="font-size: 0.9em; color: #555; font-weight: bold; margin-bottom: 15px;"></p>
        
        <div class="form-group">
            <label>Preço de Venda (R$)</label>
            <input type="number" id="est_preco" step="0.01" placeholder="Ex: 15.50">
        </div>
        
        <div class="form-group">
            <label>Estoque Atual</label>
            <input type="number" id="est_qtd_atual" readonly style="background-color: #e9ecef; font-weight: bold; font-size: 1.2em; text-align: center;">
        </div>

        <div class="form-group">
            <label>Quantidade a Movimentar</label>
            <input type="number" id="est_movimento" placeholder="Digite a quantidade...">
        </div>

        <div class="btn-grid">
            <button class="btn-est" style="background: #28a745;" onclick="salvarEstoque('entrada')"><i class="fas fa-plus"></i> Entrada</button>
            <button class="btn-est" style="background: #dc3545;" onclick="salvarEstoque('saida')"><i class="fas fa-minus"></i> Saída</button>
            <button class="btn-est" style="background: #ffc107; color: #000;" onclick="salvarEstoque('ajuste')"><i class="fas fa-pen"></i> Fixar</button>
        </div>
        
        <button onclick="fecharEstoque()" style="width: 100%; margin-top: 15px; padding: 10px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">Cancelar</button>
    </div>
</div>

<div class="container">
    <h2 style="text-align:center; margin-bottom: 30px;"><i class="fas fa-chart-line"></i> Gestor de Preços e Estoque</h2>

    <div class="top-actions">
        <button onclick="iniciarImportacao()" class="btn-main btn-import">
            <i class="fas fa-cloud-download-alt"></i> Importar da SEFA
        </button>
        <a href="scanner.php" class="btn-main btn-scanner">
            <i class="fas fa-barcode"></i> Abrir Leitor
        </a>
    </div>

    <div class="search-container">
        <form style="display:flex; width:100%; gap:10px;" method="GET">
            <input type="text" name="busca" placeholder="Pesquisar produto..." value="<?= htmlspecialchars($termoBusca) ?>">
            <button type="submit"><i class="fas fa-search"></i> Buscar</button>
            <?php if($termoBusca): ?>
                <a href="index.php" style="padding: 12px 15px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-weight:bold;"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
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
                <span class="titulo-box">💰 Sugestão Venda Base</span>
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
                    <div style="font-size:1.1em; font-weight:bold; margin-bottom:5px;"><?= htmlspecialchars($top5[0]['produto']) ?></div>
                    
                    <div style="margin-bottom:5px; color:#555;">
                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($top5[0]['local']) ?> 
                        <span style="font-size:0.8em;">(<?= date('d/m', strtotime($top5[0]['data_importacao'])) ?>)</span>
                    </div>

                  
                </div>
                <?php endif; ?>

                <?php for($i = 1; $i < count($top5); $i++): ?>
                
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php elseif($termoBusca): ?>
        <p style="text-align:center; padding:20px; color:#666">Nenhum produto encontrado.</p>
    <?php endif; ?>

    <h3 style="margin-top:40px;">📜 Histórico Geral de Produtos</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Local</th>
                    <th>Produto</th>
                    <th>Valor Custo</th>
                    <th style="min-width: 140px; text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaProdutos as $item): ?>
                <tr>
                    <td><?= date('d/m/y', strtotime($item['data_importacao'])) ?></td>
                    <td style="font-size: 0.9em;"><?= mb_strimwidth(htmlspecialchars($item['local']), 0, 20, "...") ?></td>
                    <td>
                        <a href="?busca=<?= urlencode($item['produto']) ?>" class="link-produto">
                            <?= mb_strimwidth(htmlspecialchars($item['produto']), 0, 30, "...") ?> <i class="fas fa-search" style="font-size:0.8em; color:#aaa;"></i>
                        </a>
                    </td>
                    <td style="color:#28a745; font-weight:bold;">R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                    <td style="text-align: center;">
                        <button class="btn-acao btn-estoque" title="Gerenciar Estoque" data-produto="<?= htmlspecialchars($item['produto']) ?>" onclick="abrirEstoque(this.getAttribute('data-produto'))">
                            <i class="fas fa-box"></i>
                        </button>
                        
                        <?php if(!empty($item['chave'])): ?>
                            <button onclick="copiarChave('<?= $item['chave'] ?>')" class="btn-acao btn-copiar" title="Copiar Chave"><i class="far fa-copy"></i></button>
                            <a href="https://app.sefa.pa.gov.br/consulta-nfce/#/consulta?chave=<?= $item['chave'] ?>" target="_blank" class="btn-acao btn-sefa" title="Ver Nota Original"><i class="fas fa-external-link-alt"></i></a>
                        <?php else: ?>
                            <span style="color:#ccc; padding-left:10px;">--</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Gráfico Chart.js
    <?php if ($termoBusca && count($dadosGrafico) > 0): ?>
    const ctx = document.getElementById('meuGrafico').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labelsGrafico) ?>,
            datasets: [{ label: 'Preço de Custo', data: <?= json_encode($dadosGrafico) ?>, borderColor: '#007bff', backgroundColor: 'rgba(0,123,255,0.1)', fill: true }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
    <?php endif; ?>

    function copiarChave(chave) {
        navigator.clipboard.writeText(chave).then(() => { alert('✅ Chave copiada!'); }).catch(err => { prompt("Copie:", chave); });
    }

    // ==========================================
    // LÓGICA DE IMPORTAÇÃO (SELENIUM)
    // ==========================================
    function iniciarImportacao() {
        const modal = document.getElementById('modalLog');
        const saida = document.getElementById('consoleSaida');
        
        modal.style.display = 'block';
        saida.innerHTML = '🔵 Iniciando conexão com o navegador...<br>';
        
        const evtSource = new EventSource("executar_importacao.php");

        evtSource.onmessage = function(event) {
            if (event.data === "END") {
                evtSource.close();
                saida.innerHTML += "<br><b><span style='color:#28a745'>🎉 PROCESSO CONCLUÍDO!</span></b> Atualizando a página...";
                setTimeout(() => { window.location.href = 'index.php'; }, 2000);
                return;
            }
            
            saida.innerHTML += event.data + "<br>";
            const logWindow = document.querySelector('.log-content');
            logWindow.scrollTop = logWindow.scrollHeight;
        };

        evtSource.onerror = function() {
            saida.innerHTML += "<br><span style='color:red'>🔴 A conexão foi interrompida ou finalizada.</span>";
            evtSource.close();
        };
    }

    function fecharLog() {
        document.getElementById('modalLog').style.display = 'none';
    }

    // ==========================================
    // LÓGICA DE GERENCIAMENTO DE ESTOQUE
    // ==========================================
    let produtoAtualEstoque = "";

    function abrirEstoque(nomeProduto) {
        produtoAtualEstoque = nomeProduto;
        document.getElementById('est_nome_produto').innerText = nomeProduto;
        document.getElementById('modalEstoque').style.display = 'block';
        
        document.getElementById('est_preco').value = '';
        document.getElementById('est_qtd_atual').value = 'Carregando...';
        document.getElementById('est_movimento').value = '';

        fetch('api_estoque.php?acao=buscar&produto=' + encodeURIComponent(nomeProduto))
            .then(res => res.json())
            .then(data => {
                document.getElementById('est_preco').value = data.preco_venda || '';
                document.getElementById('est_qtd_atual').value = data.quantidade || '0';
            })
            .catch(err => {
                console.error(err);
                document.getElementById('est_qtd_atual').value = 'Erro ao carregar';
            });
    }

    function fecharEstoque() {
        document.getElementById('modalEstoque').style.display = 'none';
    }

    function salvarEstoque(tipoOperacao) {
        const precoVenda = document.getElementById('est_preco').value;
        const qtdMovimento = document.getElementById('est_movimento').value;

        if (tipoOperacao !== 'ajuste' && (!qtdMovimento || qtdMovimento <= 0)) {
            alert('Por favor, digite uma quantidade válida para movimentar.');
            return;
        }

        const dados = new FormData();
        dados.append('acao', 'salvar');
        dados.append('produto', produtoAtualEstoque);
        dados.append('preco_venda', precoVenda);
        dados.append('quantidade', qtdMovimento);
        dados.append('operacao', tipoOperacao);

        fetch('api_estoque.php', { method: 'POST', body: dados })
            .then(res => res.text())
            .then(resposta => {
                if(resposta.trim() === "Sucesso") {
                    alert('✅ Estoque atualizado com sucesso!');
                    abrirEstoque(produtoAtualEstoque); // Recarrega para mostrar o novo valor
                    document.getElementById('est_movimento').value = ''; 
                } else {
                    alert('Erro retornado: ' + resposta);
                }
            })
            .catch(err => alert('Erro na comunicação com o servidor: ' + err));
    }
</script>
</body>
</html>