<?php
require_once('vendor/autoload.php'); // Carrega o Composer
require_once('banco.php');
error_reporting(E_ALL & ~E_DEPRECATED); // Esconde avisos de depreciação
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;

// Configuração para conectar no Selenium que está rodando no terminal java
$host = 'http://localhost:4444'; // Note que não tem /wd/hub no final
$capabilities = DesiredCapabilities::chrome();
$driver = RemoteWebDriver::create($host, $capabilities);

echo "🔵 Navegador aberto! Acesse a SEFA PA.\n";

// 1. Acessa o site
$driver->get('https://app.sefa.pa.gov.br/consulta-nfce/#/consulta');

echo "👉 Digite a CHAVE, resolva o CAPTCHA e clique em CONSULTAR.\n";
echo "👉 Quando a lista de produtos aparecer, volte aqui e aperte ENTER no terminal...";

// Espera o usuário dar Enter no terminal
$handle = fopen("php://stdin", "r");
$line = fgets($handle);

echo "⏳ Extraindo dados...\n";

try {
    // 2. Entra no iFrame (igual fizemos no Python)
    // Procura o primeiro iframe da página
    $iframes = $driver->findElements(WebDriverBy::tagName('iframe'));
    if (count($iframes) > 0) {
        $driver->switchTo()->frame($iframes[0]);
    }

    // 3. Busca as linhas da tabela
    // Ajuste do seletor CSS para pegar as linhas que começam com id 'Item'
    $linhas = $driver->findElements(WebDriverBy::cssSelector("tr[id^='Item']"));

    // ... (o começo do código continua igual) ...

    $banco = new Banco();
    $contador = 0;

    foreach ($linhas as $linha) {
        try {
            // 1. Pega todo o texto da linha para procurar as informações escondidas
            $textoCompleto = $linha->getText();
            // Exemplo do textoCompleto: "FEIJAO 1KG Qtde.: 2 UN: UN Vl. Unit.: 8,50 Vl. Total 17,00"

            // 2. Extrai o Nome (geralmente é a primeira parte ou classe txtTit)
            try {
                $nome = $linha->findElement(\Facebook\WebDriver\WebDriverBy::className('txtTit'))->getText();
            } catch (Exception $ex) {
                // Se falhar, pega a primeira linha do texto
                $partes = explode("\n", $textoCompleto);
                $nome = $partes[0];
            }

            // 3. ESTRATÉGIA NOVA: Caçar o "Vl. Unit" usando Regex
            $preco = 0;

            // Procura por "Vl. Unit.:" seguido de um número
            if (preg_match('/Vl\.?\s*Unit\.?[:\s]*([\d.,]+)/i', $textoCompleto, $matches)) {
                // $matches[1] terá o valor unitário (ex: "3,49")
                $valorTexto = $matches[1];
            } else {
                // Se não achar "Vl. Unit", tenta pegar pela classe CSS específica (.RvlUnit)
                try {
                    $valorTexto = $linha->findElement(\Facebook\WebDriver\WebDriverBy::className('RvlUnit'))->getText();
                } catch (Exception $e) {
                    // Se tudo falhar, pega o valor total mesmo (.valor)
                    $valorTexto = $linha->findElement(\Facebook\WebDriver\WebDriverBy::className('valor'))->getText();
                }
            }

            // 4. Limpeza de valor (Transforma "3,49" em 3.49)
            $precoLimpo = str_replace(['R$', ' ', '.'], '', $valorTexto); // Tira R$ e ponto de milhar
            $precoLimpo = str_replace(',', '.', $precoLimpo); // Troca vírgula por ponto
            $preco = floatval($precoLimpo);

            // 5. Extrai a Unidade (KG, UN, LT)
            $unidade = "UN";
            if (preg_match('/UN[:\s]*([A-Z]+)/i', $textoCompleto, $matchesUnid)) {
                $unidade = $matchesUnid[1];
            }

            // Só salva se o preço for válido e tiver nome
            if ($preco > 0 && !empty($nome)) {
                // Tenta inserir (a função do banco já evita duplicados do dia)
                $inseriu = $banco->inserir($nome, $preco, $unidade);

                if ($inseriu) {
                    echo "✅ Item: $nome \t| Unitário: R$ $preco ($unidade)\n";
                    $contador++;
                } else {
                    echo "⚠️ Item duplicado (já salvo hoje): $nome\n";
                }
            }

        } catch (Exception $e) {
            // Ignora linhas que não são produtos
        }
    }

    // ... (o final do código continua igual) ...


    echo "\n🎉 $contador produtos importados com sucesso!\n";

} catch (Exception $e) {
    echo "Erro fatal: " . $e->getMessage();
}

// Fecha o navegador
$driver->quit();
?>