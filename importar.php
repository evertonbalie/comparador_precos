<?php
// importar.php
error_reporting(E_ALL & ~E_DEPRECATED);
require_once('vendor/autoload.php');
require_once('banco.php');

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;

// Configuração do Chrome
$host = 'http://localhost:4444';
$options = new ChromeOptions();
$options->addArguments(['--start-maximized', '--disable-gpu', '--no-sandbox']);
$capabilities = DesiredCapabilities::chrome();
$capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

try {
    echo "🔵 Conectando ao ChromeDriver...\n";
    $driver = RemoteWebDriver::create($host, $capabilities, 60000, 60000);

    echo "🟢 Navegador aberto! Acessando SEFA PA...\n";
    $driver->get('https://app.sefa.pa.gov.br/consulta-nfce/#/consulta');

    echo "\n👉 AÇÃO MANUAL NECESSÁRIA:\n";
    echo "1. Digite a Chave, resolva o Captcha e clique em Consultar.\n";
    echo "2. ESPERE a nota aparecer completa na tela.\n";
    echo "3. Volte aqui e aperte ENTER.\n";
    
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);

    echo "⏳ Analisando a estrutura da página...\n";

    // ---------------------------------------------------------
    // 1. RESOLUÇÃO DO PROBLEMA DE DADOS VAZIOS
    // ---------------------------------------------------------
    // A nota fiscal geralmente fica dentro de um iFrame.
    // Precisamos entrar nele ANTES de tentar ler qualquer texto.
    
    $iframes = $driver->findElements(WebDriverBy::tagName('iframe'));
    if (count($iframes) > 0) {
        echo "🔄 Detectado iFrame. Entrando no contexto da nota...\n";
        $driver->switchTo()->frame($iframes[0]);
        sleep(1); // Pequena pausa para garantir o foco
    }

    // Pega todo o texto visível da nota para usar Regex (Mais seguro)
    $textoPagina = $driver->findElement(WebDriverBy::tagName('body'))->getText();
    // Pega o HTML oculto caso o texto visível falhe
    $htmlPagina = $driver->getPageSource(); 

    // --- Extração dos Dados Gerais (Cabeçalho) ---
    $nomeLocal = "Estabelecimento Desconhecido";
    $chaveAcesso = "";
    $numeroNota = "000";

    // A. NOME DO LOCAL (Nova Lógica Baseada na sua Descoberta)
    $achouNome = false;

    // TENTATIVA 1: Busca direta pelo ID 'u20' (Onde está o Freitas Gomes)
    if (!$achouNome) {
        try {
            $elementoNome = $driver->findElement(WebDriverBy::id('u20'));
            $nomeLocal = trim($elementoNome->getText());
            $achouNome = true;
        } catch (Exception $e) { /* Não achou ID u20, tenta o próximo */ }
    }

    // TENTATIVA 2: Busca pela classe 'txtTopo' (Comum na SEFA)
    if (!$achouNome) {
        try {
            $elementoNome = $driver->findElement(WebDriverBy::className('txtTopo'));
            $nomeLocal = trim($elementoNome->getText());
            $achouNome = true;
        } catch (Exception $e) { /* Não achou classe txtTopo */ }
    }

    // TENTATIVA 3: Busca por texto perto do CNPJ (Fallback antigo)
    if (!$achouNome) {
        $linhasTexto = explode("\n", $textoPagina);
        for ($i = 0; $i < count($linhasTexto); $i++) {
            if (stripos($linhasTexto[$i], 'CNPJ') !== false) {
                // Pega linha anterior
                if (isset($linhasTexto[$i - 1])) {
                    $candidato = trim($linhasTexto[$i - 1]);
                    if (strlen($candidato) > 2 && stripos($candidato, 'DOCUMENTO') === false) {
                        $nomeLocal = $candidato;
                        $achouNome = true;
                    }
                }
                break;
            }
        }
    }

    // B. Busca Chave de Acesso (44 dígitos numéricos)
    // Removemos espaços para a regex funcionar (ex: "1234 5678" vira "12345678")
    $textoSemEspaco = str_replace([' ', '.', '-'], '', $textoPagina);
    
    if (preg_match('/[0-9]{44}/', $textoSemEspaco, $matches)) {
        $chaveAcesso = $matches[0];
    } elseif (preg_match('/[0-9]{44}/', $htmlPagina, $matches)) {
        // Tenta buscar no HTML se não achou no texto
        $chaveAcesso = $matches[0];
    }

    // C. Busca Número da Nota
    // Procura por "Número: 123" ou "Nº 123"
    if (preg_match('/(?:N[úu]mero|Nº)[:\s]*([0-9]+)/iu', $textoPagina, $matchesNum)) {
        $numeroNota = $matchesNum[1];
    }

    echo "\n📊 DADOS EXTRAÍDOS DO CABEÇALHO:\n";
    echo "🏢 Local: $nomeLocal\n";
    echo "🔑 Chave: $chaveAcesso\n";
    echo "📄 Nota:  $numeroNota\n";
    echo "---------------------------------\n";

    // ---------------------------------------------------------
    // 2. EXTRAÇÃO DOS PRODUTOS
    // ---------------------------------------------------------
    
    // Procura as linhas da tabela (tr)
    $linhas = $driver->findElements(WebDriverBy::cssSelector("tr[id^='Item']"));
    
    // Fallback: se não achar pelo ID, pega todas as linhas de tabela
    if(count($linhas) == 0) {
        $linhas = $driver->findElements(WebDriverBy::cssSelector("table tbody tr"));
    }

    $banco = new Banco();
    $contador = 0;

    foreach ($linhas as $linha) {
        try {
            $textoCompleto = $linha->getText(); 

            // Nome do Produto
            try {
                $nome = $linha->findElement(WebDriverBy::className('txtTit'))->getText();
            } catch(Exception $ex) {
                // Se não tiver classe específica, pega a primeira linha
                $partes = explode("\n", $textoCompleto);
                $nome = $partes[0];
            }

            // Preço Unitário
            $preco = 0;
            // Tenta achar "Vl. Unit." via Regex
            if (preg_match('/Vl\.?\s*Unit\.?[:\s]*R?\$?\s*([\d.,]+)/i', $textoCompleto, $matches)) {
                $valorTexto = $matches[1];
            } else {
                try {
                    // Tenta pela classe CSS comum
                    $valorTexto = $linha->findElement(WebDriverBy::className('RvlUnit'))->getText();
                } catch (Exception $e) {
                    // Tenta pegar qualquer valor monetário na linha
                    if (preg_match('/(\d+,\d{2})/', $textoCompleto, $matches)) {
                         $valorTexto = $matches[1];
                    } else {
                        continue; // Se não achou preço, pula
                    }
                }
            }

            // Limpeza do valor (Ex: "3,50" -> 3.50)
            $precoLimpo = str_replace(['R$', ' ', '.'], '', $valorTexto); 
            $precoLimpo = str_replace(',', '.', $precoLimpo);
            $preco = floatval($precoLimpo);

            // Unidade (UN, KG, CX)
            $unidade = "UN";
            if (preg_match('/(?:UN|Unidade)[:\s]*([A-Z]+)/i', $textoCompleto, $matchesUnid)) {
                $unidade = $matchesUnid[1];
            }

            // Validação e Salvamento
            if($preco > 0 && !empty($nome)) {
                // Insere no banco passando a Chave e Nota que pegamos lá em cima
                $inseriu = $banco->inserir($nome, $preco, $unidade, $chaveAcesso, $numeroNota, $nomeLocal);
                
                if ($inseriu) {
                    echo "✅ $nome | R$ $preco\n";
                    $contador++;
                } else {
                    echo "⚠️ $nome (Duplicado/Ignorado)\n";
                }
            }

        } catch (Exception $e) {
            // Erro em uma linha específica não para o script
            echo "Erro ao ler linha: " . $e->getMessage() . "\n";
        }
    }

    echo "\n🎉 Processo finalizado! $contador itens salvos.\n";

} catch (Exception $e) {
    echo "🔴 ERRO GERAL: " . $e->getMessage() . "\n";
}
?>