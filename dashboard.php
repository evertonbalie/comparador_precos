<?php
// 1. Conexão com o Banco de Dados
$host = 'localhost';
$db   = 'minhas_financas';
$user = 'root'; // Seu usuário do MySQL
$pass = '';     // Sua senha do MySQL

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Falha na conexão: " . $mysqli->connect_error);
}

// 2. Consulta para o Gráfico (Total gasto por Estabelecimento)
$queryGrafico = "SELECT estabelecimento, SUM(preco) as total FROM itens_compra GROUP BY estabelecimento";
$resultGrafico = $mysqli->query($queryGrafico);

$labels = [];
$data = [];

while($row = $resultGrafico->fetch_assoc()) {
    $labels[] = $row['estabelecimento'];
    $data[] = $row['total'];
}

// 3. Consulta para a Tabela (Últimos lançamentos)
$queryTabela = "SELECT * FROM itens_compra ORDER BY data_compra DESC LIMIT 10";
$resultTabela = $mysqli->query($queryTabela);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Finanças - Web</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card { margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: none; }
        .header-site { background-color: #2c3e50; color: white; padding: 20px 0; margin-bottom: 30px; }
    </style>
</head>
<body>

    <div class="header-site text-center">
        <h1>💰 Controle Financeiro Web</h1>
        <p>Visão geral dos dados importados pelo App</p>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Gastos por Local</h5>
                        <canvas id="meuGrafico"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Total Gasto</h5>
                                <p class="card-text fs-3">
                                    R$ <?php echo number_format(array_sum($data), 2, ',', '.'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Itens Registrados</h5>
                                <p class="card-text fs-3">
                                    <?php echo $resultTabela->num_rows; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <strong>Últimas Compras Importadas</strong>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Produto</th>
                                    <th>Local</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($item = $resultTabela->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($item['data_compra'])); ?></td>
                                    <td><?php echo $item['nome_produto']; ?></td>
                                    <td><?php echo $item['estabelecimento']; ?></td>
                                    <td class="text-danger fw-bold">
                                        R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('meuGrafico').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut', // Tipo do gráfico (pode ser 'bar', 'line', 'pie')
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Valor Gasto (R$)',
                    data: <?php echo json_encode($data); ?>,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>

</body>
</html>