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
        
        <!-- Insecure Context Warning -->
        <div id="avisoSeguranca" style="display: none; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 15px; border-radius: 8px; text-align: left; margin-bottom: 15px; font-size: 0.9em; line-height: 1.4;">
            <strong style="color: #664d03; font-size: 1.05em; display: block; margin-bottom: 5px;"><i class="fas fa-exclamation-triangle"></i> Conexão HTTP Sem Criptografia</strong>
            O navegador bloqueia a câmera ao vivo em sites sem HTTPS (conexão segura).
            <div style="margin-top: 10px;">
                <b>Você pode:</b>
                <ul style="margin: 5px 0; padding-left: 15px; list-style-type: disc;">
                    <li>Usar o botão <b>Tirar Foto (Sem HTTPS)</b> abaixo para fotografar o código.</li>
                    <li>Liberar temporariamente no Chrome do celular acessando <code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px; font-size: 0.9em; word-break: break-all;">chrome://flags</code>, buscando por <i>"unsafely-treat-insecure-origin-as-secure"</i>, ativando-a e adicionando o endereço <code id="lblIPServidor" style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px; font-size: 0.9em; font-weight: bold;"></code> e reiniciando o Chrome.</li>
                </ul>
            </div>
        </div>

        <div id="reader"></div>
        <div id="statusCamera" style="color: red; display: none; margin-bottom: 10px;">Erro ao carregar câmera</div>

        <button class="btn btn-cam" id="btnStart" onclick="ligarCamera()">📸 Câmera ao Vivo</button>
        
        <!-- Native Camera Fallback Button -->
        <button class="btn btn-cam" id="btnFallback" style="background: #e67e22; display: none;" onclick="document.getElementById('fileFallback').click()">📷 Tirar Foto (Sem HTTPS)</button>
        <input type="file" id="fileFallback" accept="image/*" capture="environment" style="display: none;" onchange="processarFoto(this)">

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

        // Detecta conexão HTTP insegura e exibe alertas explicativos
        window.addEventListener('load', () => {
            const isLocal = ['localhost', '127.0.0.1'].includes(window.location.hostname);
            const isSecure = window.isSecureContext || window.location.protocol === 'https:';
            
            if (!isSecure && !isLocal) {
                document.getElementById('avisoSeguranca').style.display = 'block';
                document.getElementById('btnFallback').style.display = 'inline-block';
                document.getElementById('btnStart').style.backgroundColor = '#6c757d'; // cor neutra para sinalizar fallback
                
                const cleanUrl = window.location.origin;
                document.getElementById('lblIPServidor').innerText = cleanUrl;
            }
        });

        function ligarCamera() {
            // Verifica se a biblioteca carregou
            if (typeof Html5Qrcode === 'undefined') {
                alert("Erro: A biblioteca de câmera não carregou. Verifique sua internet.");
                return;
            }

            document.getElementById('statusCamera').style.display = 'none';
            document.getElementById('btnStart').style.display = 'none';
            document.getElementById('btnFallback').style.display = 'none';
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
                alert("Não foi possível abrir a câmera!\n\nSeu celular pode ter bloqueado por falta de HTTPS. Tente usar o botão 'Tirar Foto' ou configure o Chrome.");
                pararCamera();
            });
        }

        // Processa foto tirada pela câmera nativa do celular (fallback offline/HTTP)
        function processarFoto(input) {
            if (!input.files || input.files.length === 0) return;
            const file = input.files[0];
            
            document.getElementById('reader').style.display = 'block';
            
            if (typeof Html5Qrcode === 'undefined') {
                alert("Erro: Biblioteca de câmera não carregou.");
                return;
            }
            
            if (!html5QrcodeScanner) {
                html5QrcodeScanner = new Html5Qrcode("reader");
            }
            
            html5QrcodeScanner.scanFile(file, true)
                .then(decodedText => {
                    document.getElementById('inputCodigo').value = decodedText;
                    document.forms[0].submit(); // Envia o formulário
                })
                .catch(err => {
                    console.error(err);
                    alert("Não foi possível ler o código nesta foto. Aproxime mais a câmera e tire a foto focando no código de barras.");
                });
        }

        function pararCamera() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.stop().then(() => {
                    document.getElementById('reader').innerHTML = "";
                    document.getElementById('reader').style.display = 'none';
                }).catch(err => {});
            }
            document.getElementById('btnStart').style.display = 'inline-block';
            
            const isLocal = ['localhost', '127.0.0.1'].includes(window.location.hostname);
            const isSecure = window.isSecureContext || window.location.protocol === 'https:';
            if (!isSecure && !isLocal) {
                document.getElementById('btnFallback').style.display = 'inline-block';
            }
            
            document.getElementById('btnStop').style.display = 'none';
        }
    </script>
</body>
</html>