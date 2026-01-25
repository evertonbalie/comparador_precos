<?php
require_once('banco.php');
$banco = new Banco();

$termoBusca = $_GET['busca'] ?? null;
$listaProdutos = $banco->listarTudo();
$melhorPreco = null;

// Variáveis para o gráfico (começam vazias)
$labelsGrafico = []; // Datas
$dadosGrafico = [];  // Preços

if ($termoBusca) {
    // 1. Pega o destaque (melhor preço)
    $melhorPreco = $banco->buscarMelhorPreco($termoBusca);

    // 2. Pega o histórico para o gráfico
    $historico = $banco->buscarHistoricoGrafico($termoBusca);

    // Prepara os dados para o Javascript ler
    foreach ($historico as $h) {
        // Formata a data para dia/mês (ex: 25/01)
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
    <title>Histórico de Compras - SEFA PA</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #444;
        }

        .search-box {
            text-align: center;
            margin-bottom: 30px;
        }

        input[type="text"] {
            padding: 12px;
            width: 60%;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            padding: 12px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background-color: #218838;
        }

        /* Box do Gráfico */
        .grafico-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .destaque {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .destaque h3 {
            margin: 0 0 10px 0;
            color: #155724;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #007bff;
            color: white;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        .preco {
            color: #d9534f;
            font-weight: bold;
        }

        .data {
            color: #888;
            font-size: 0.9em;
        }
    </style>
</head>

<body>

    <h1>🛒 Minhas Compras</h1>

    <div class="search-box">
        <form>
            <input type="text" name="busca" placeholder="Pesquisar histórico (ex: Arroz)"
                value="<?= htmlspecialchars($termoBusca) ?>">
            <button type="submit">Ver Evolução</button>
            <?php if ($termoBusca): ?>
                <a href="index.php" style="margin-left: 10px; text-decoration: none; color: red;">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($termoBusca && count($dadosGrafico) > 0): ?>
        <div class="grafico-container">
            <h3 style="text-align:center;">📈 Evolução do Preço: <?= htmlspecialchars($termoBusca) ?></h3>
            <canvas id="meuGrafico"></canvas>
        </div>

        <div class="destaque">
            <h3>🏆 Melhor Preço Já Pago</h3>
            <p style="font-size: 1.5em; font-weight: bold;">R$ <?= number_format($melhorPreco['preco'], 2, ',', '.') ?></p>
            <p><small>Em: <?= date('d/m/Y', strtotime($melhorPreco['data_importacao'])) ?></small></p>
        </div>
    <?php elseif ($termoBusca): ?>
        <p style="text-align:center; color:red">Nenhum dado encontrado para esse produto.</p>
    <?php endif; ?>

    <h3>📜 Últimas Importações</h3>
    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th>Valor Unit.</th>
                <th>Unid.</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($listaProdutos as $item): ?>
                <tr>
                    <td><?= $item['produto'] ?></td>
                    <td class="preco">R$ <?= number_format($item['preco'], 2, ',', '.') ?></td>
                    <td><?= $item['unidade'] ?></td>
                    <td class="data"><?= date('d/m/Y', strtotime($item['data_importacao'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        <?php if ($termoBusca && count($dadosGrafico) > 0): ?>
            const ctx = document.getElementById('meuGrafico').getContext('2d');
            new Chart(ctx, {
                type: 'line', // Tipo Linha (ideal para tempo)
                data: {
                    labels: <?= json_encode($labelsGrafico) ?>, // As datas vindo do PHP
                    datasets: [{
                        label: 'Preço (R$)',
                        data: <?= json_encode($dadosGrafico) ?>, // Os preços vindo do PHP
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1, // Deixa a linha levemente curva
                        pointRadius: 6,
                        pointBackgroundColor: 'red'
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: false, // Começa o gráfico perto do preço real, não do zero
                            title: { display: true, text: 'Valor em Reais' }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>

</body>

</html>