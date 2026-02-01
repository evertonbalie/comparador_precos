<?php
require_once('banco.php');
$banco = new Banco();

$codigoLido = $_POST['codigo'] ?? null;
$produtoEncontrado = null;
$erro = null;

if ($codigoLido) {
    $produtoEncontrado = $banco->buscarPorCodigoBarras($codigoLido);
    if (!$produtoEncontrado) {
        $erro = "Produto não encontrado: $codigoLido";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>

    <style>
        body { font-family: sans-serif; background-color: #2c3e50; color: white; display: flex; flex-direction: column; align-items: center; padding: 20px; }
        .box { background: white; color: #333; padding: 20px; border-radius: 10px; width: 100%; max-width: 500px; text-align: center; }
        #reader { width: 100%; min-height: 250px; background: #000; margin-bottom: 20px; }
        input { width: 100%; padding: 10px; font-size: 18px; margin-bottom: 10px; box-sizing: border-box; }
        .btn { padding: 10px 20px; border-radius: 5px; cursor: pointer; border: none; font-size: 16px; margin: 5px; }
        .btn-cam { background: #e67e22; color: white; }
        .btn-stop { background: #c0392b; color: white; display: none; }
        .resultado { background: #dff0d8; color: #3c763d; padding: 15px; margin-top: 15px; border-radius: 5px; }
        .erro { background: #f2dede; color: #a94442; padding: 15px; margin-top: 15px; border-radius: 5px; }
    </style>
</head>
<body>

    <div class="box">
        <h3><i class="fas fa-barcode"></i> Leitor de Preço</h3>
        
        <div id="reader"></div>
        <div id="statusCamera" style="color: red; display: none; margin-bottom: 10px;">Erro ao carregar câmera</div>

        <button class="btn btn-cam" id="btnStart" onclick="ligarCamera()">📸 Abrir Câmera</button>
        <button class="btn btn-stop" id="btnStop" onclick="pararCamera()">🛑 Parar</button>

        <p>Ou digite o código:</p>
        <form method="POST">
            <input type="text" name="codigo" id="inputCodigo" placeholder="Código de barras..." autofocus>
        </form>

        <?php if ($produtoEncontrado): ?>
            <div class="resultado">
                <h2>R$ <?= number_format($produtoEncontrado['preco'], 2, ',', '.') ?></h2>
                <strong><?= $produtoEncontrado['produto'] ?></strong><br>
                <small><?= $produtoEncontrado['local'] ?></small>
            </div>
        <?php elseif ($erro): ?>
            <div class="erro">❌ <?= $erro ?></div>
        <?php endif; ?>
    </div>

    <script>
        let html5QrcodeScanner = null;

        function ligarCamera() {
            // Verifica se a biblioteca carregou
            if (typeof Html5Qrcode === 'undefined') {
                alert("Erro: A biblioteca de câmera não carregou. Verifique sua internet.");
                return;
            }

            document.getElementById('statusCamera').style.display = 'none';
            document.getElementById('btnStart').style.display = 'none';
            document.getElementById('btnStop').style.display = 'inline-block';

            html5QrcodeScanner = new Html5Qrcode("reader");

            // Tenta abrir a câmera
            html5QrcodeScanner.start(
                { facingMode: "environment" }, 
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    // SUCESSO AO LER
                    html5QrcodeScanner.stop();
                    document.getElementById('inputCodigo').value = decodedText;
                    document.forms[0].submit(); // Envia o formulário
                },
                (errorMessage) => {
                    // Erro de leitura (normal enquanto procura)
                }
            ).catch(err => {
                // ERRO CRÍTICO (Falta de HTTPS ou permissão)
                console.log(err);
                alert("Não foi possível abrir a câmera!\n\nMOTIVOS PROVÁVEIS:\n1. Site sem HTTPS (Use o Ngrok)\n2. Permissão negada no navegador.");
                pararCamera();
            });
        }

        function pararCamera() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.stop().then(() => {
                    document.getElementById('reader').innerHTML = "";
                });
            }
            document.getElementById('btnStart').style.display = 'inline-block';
            document.getElementById('btnStop').style.display = 'none';
        }
    </script>
</body>
</html>