<?php
// importar.php
error_reporting(E_ALL & ~E_DEPRECATED);
require_once('vendor/autoload.php');
require_once('banco.php');

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;

// Configuração do Chrome e Inicialização Automática do ChromeDriver
$host = '127.0.0.1:4444';
$port = 4444;
$chromeDriverProcess = null;

// Verifica se já existe um serviço ouvindo na porta 4444
$connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
if (!is_resource($connection)) {
    echo "🔵 ChromeDriver não detectado na porta $port. Inicializando automaticamente...\n";
    
    $chromedriverPath = __DIR__ . DIRECTORY_SEPARATOR . 'chromedriver.exe';
    
    if (!file_exists($chromedriverPath)) {
        echo "⚠️ Arquivo chromedriver.exe não encontrado em: $chromedriverPath\n";
    } else {
        // Redireciona stdin, stdout e stderr para NUL no Windows para evitar bloqueio por buffer cheio
        $descriptorspec = [
            0 => ['file', 'NUL', 'r'],
            1 => ['file', 'NUL', 'w'],
            2 => ['file', 'NUL', 'w']
        ];
        
        $cmd = '"' . $chromedriverPath . '" --port=' . $port . ' --allowed-ips=';
        
        // bypass_shell garante compatibilidade com aspas no Windows
        $chromeDriverProcess = proc_open($cmd, $descriptorspec, $pipes, null, null, ['bypass_shell' => true]);
        
        if (is_resource($chromeDriverProcess)) {
            echo "⏳ Aguardando inicialização do ChromeDriver...\n";
            // Aguarda 1.5 segundos para o ChromeDriver inicializar
            usleep(1500000);
            
            // Registra função para desligar o processo no encerramento do script
            register_shutdown_function(function() use ($chromeDriverProcess) {
                if (is_resource($chromeDriverProcess)) {
                    echo "\n🔴 Encerrando instância automática do ChromeDriver...\n";
                    proc_terminate($chromeDriverProcess);
                    proc_close($chromeDriverProcess);
                }
            });
        } else {
            echo "❌ Falha ao iniciar o processo do ChromeDriver.\n";
        }
    }
} else {
    fclose($connection);
    echo "🟢 ChromeDriver já está ativo na porta $port. Conectando à instância existente...\n";
}

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
 echo "1. Digite a Chave e resolva o Captcha no navegador que abriu.\n";
    echo "⏳ Aguardando você carregar a nota...\n";
    
    $notaCarregou = false;
    $tentativas = 0;
    $limiteTentativas = 90; // 90 tentativas de 2s = 3 minutos
    
    while (!$notaCarregou && $tentativas < $limiteTentativas) {
        try {
            // Garante que o Selenium olhe para a página principal antes de buscar
            $driver->switchTo()->defaultContent();
            $iframes = $driver->findElements(WebDriverBy::tagName('iframe'));
            
            foreach ($iframes as $iframe) {
                // Entra no iFrame atual para ler o texto lá dentro
                $driver->switchTo()->frame($iframe);
                $textoIframe = $driver->findElement(WebDriverBy::tagName('body'))->getText();
                
                // Verifica se é realmente a Nota Fiscal buscando palavras-chave comuns
                if (stripos($textoIframe, 'CNPJ') !== false && stripos($textoIframe, 'Total') !== false) {
                    $notaCarregou = true;
                    echo "\n✅ Nota detectada com sucesso! Iniciando leitura...\n";
                    break; // Achou a nota, para o loop e MANTÉM o foco neste iFrame
                }
                
                // Se era só o Captcha, volta pra página principal e olha o próximo iFrame
                $driver->switchTo()->defaultContent();
            }
        } catch (Exception $e) {
            // Se o Selenium for bloqueado de ler o iFrame do Captcha, apenas ignora
            $driver->switchTo()->defaultContent();
        }

        if (!$notaCarregou) {
            echo "."; 
            flush();
            sleep(2);
            $tentativas++;
        }
    }

    if (!$notaCarregou) {
        echo "\n🔴 Tempo limite esgotado. Nenhuma nota foi encontrada.\n";
        $driver->quit();
        exit;
    }

    // --- A PARTIR DAQUI VEM O SEU CÓDIGO ORIGINAL DE EXTRAÇÃO ---
    // (Lembre-se de remover aquele $driver->switchTo()->frame($iframes[0]); antigo, pois o loop acima já deixa o driver dentro da nota corretamente)

    echo "⏳ Analisando a estrutura da página...\n";

    // Pega todo o texto visível da nota para usar Regex
    $textoPagina = $driver->findElement(WebDriverBy::tagName('body'))->getText();
    $htmlPagina = $driver->getPageSource();

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

    // D. Busca Data de Emissão
    $dataEmissaoDB = null; // Fica nulo se não achar
    // Procura o padrão comum de emissão: "Emissão: 01/05/2026 14:30:00"
    if (preg_match('/(?:Emissão|Data de Emissão)[:\s]*(\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}:\d{2})/iu', $textoPagina, $matchesData)) {
        $dataExtraida = $matchesData[1];
        // Converte do padrão Brasileiro (d/m/Y H:i:s) para o padrão de Banco de Dados (Y-m-d H:i:s)
        $dataEmissaoDB = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $dataExtraida)));
        
    }

 // E: CAPTURA DA QUANTIDADE ---
            $quantidade = 1; // Começa com 1 como padrão de segurança
            
            // Busca por variações de quantidade (Qtd, Qtde, Quantidade)
            if (preg_match('/(?:Qtde?|Quantidade|Qtd)\.?[:\s]*([\d.,]+)/iu', $textoCompleto, $matchesQtd)) {
                $qtdTexto = $matchesQtd[1]; // Ex: "2,000" ou "1.5"
                
                // Limpeza: remove pontos de milhar e troca vírgula por ponto decimal
                $qtdTexto = str_replace('.', '', $qtdTexto);
                $qtdTexto = str_replace(',', '.', $qtdTexto);
                
                // Converte para número (float, pois pode ser 1.5 KG)
                $quantidade = floatval($qtdTexto);
            }
            // ------------------------------------------

    echo "\n📊 DADOS EXTRAÍDOS DO CABEÇALHO:\n";
    echo "🏢 Local: $nomeLocal\n";
    echo "🔑 Chave: $chaveAcesso\n";
    echo "📄 Nota:  $numeroNota\n";
    echo "Quantidade: $quantidade\n";
    echo "📅 Emissão: " . ($dataEmissaoDB ?? 'Não encontrada') . "\n";
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

            // --- CAPTURA DA QUANTIDADE (ESTRITA) ---
            $quantidade = null; // Começa nulo. Se não achar, vai barrar a inserção.
            
            if (preg_match('/(?:Qtde?|Quantidade|Qtd)\.?[:\s]*([\d.,]+)/iu', $textoCompleto, $matchesQtd)) {
                $qtdTexto = $matchesQtd[1]; 
                $qtdTexto = str_replace('.', '', $qtdTexto); // Remove ponto de milhar
                $qtdTexto = str_replace(',', '.', $qtdTexto); // Troca vírgula por ponto
                $quantidade = floatval($qtdTexto);
            }

            // --- VALIDAÇÃO E SALVAMENTO ---
            // 1. Verifica se achou Nome e Preço
            if (empty($nome) || $preco <= 0) {
                continue; // Pula linhas vazias ou sem preço
            }

            // 2. A TRAVA DE ESTOQUE QUE VOCÊ PEDIU
            if ($quantidade === null || $quantidade <= 0) {
                echo "🛑 DIVERGÊNCIA: Quantidade não encontrada para o produto '$nome'. Item ignorado!\n";
                continue; // Interrompe este item e vai para o próximo da lista
            }

            // 3. Se passou por todas as travas, insere no banco
            $inseriu = $banco->inserir($nome, $preco, $unidade, $chaveAcesso, $numeroNota, $nomeLocal,$quantidade, $dataEmissaoDB);
            
            if ($inseriu) {
                echo "✅ $nome | Qtd: $quantidade | R$ $preco\n";
                $contador++;
            } else {
                echo "⚠️ $nome (Duplicado - Chave e Qtd já existem)\n";
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