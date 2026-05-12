<?php
// executar_importacao.php
session_write_close(); // Libera a sessão para não travar o navegador
set_time_limit(0); // Remove limite de tempo de execução do PHP
ini_set('max_execution_time', 0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive'); // Avisa o navegador para não fechar a tela
header('X-Accel-Buffering: no'); // Evita que o Nginx/Apache segure o texto

// Opcional: desliga o buffer de saída do PHP
if (ob_get_level()) ob_end_clean();

$comando = "php importar.php 2>&1";
$proc = popen($comando, 'r');

while (!feof($proc)) {
    $linha = fgets($proc);
    if ($linha !== false) {
        echo "data: " . $linha . "\n\n";
        flush(); // Força o envio imediato para a tela
    }
}
pclose($proc);
echo "data: END\n\n";
flush();
?>