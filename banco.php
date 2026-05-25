<?php
class Banco
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite:meus_precos.db');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->criarTabela();
    }

    private function criarTabela()
    {
        // Nova estrutura com chave, nota e local
        $sql = "CREATE TABLE IF NOT EXISTS compras (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            produto TEXT,
            preco REAL,
            unidade TEXT,
            chave TEXT,
            numero_nota TEXT,
            local TEXT,
            data_importacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            quantidade REAL,
            data_emissao DATETIME
        )";
        $this->pdo->exec($sql);

        // Adiciona a coluna codigo_barras caso ainda não exista
        try {
            $this->pdo->exec("ALTER TABLE compras ADD COLUMN codigo_barras TEXT");
        } catch (Exception $e) {
            // Ignora se a coluna já existir
        }
    }

    public function inserir($produto, $preco, $unidade, $chave, $numero, $local, $quantidade, $dataEmissao)
    {
        // 1. Força o fuso horário correto do Brasil
        date_default_timezone_set('America/Sao_Paulo');

        // 2. Captura a data e hora exata do momento da execução
        $dataImportacaoLocal = date('Y-m-d H:i:s');

        // Evita duplicar o mesmo produto da mesma nota
        $check = $this->pdo->prepare("SELECT id FROM compras WHERE produto = :prod AND chave = :chave");
        $check->execute([':prod' => $produto, ':chave' => $chave]);

        if (!$check->fetch()) {
            // 3. Adicionamos a coluna data_importacao manualmente aqui no INSERT
            $stmt = $this->pdo->prepare("INSERT INTO compras (produto, preco, unidade, chave, numero_nota, local,data_importacao, quantidade,data_emissao) 
                                         VALUES (:prod, :preco, :unid, :chave, :num, :local,:importacao,:quantidade, :emissao)");
            $stmt->execute([
                ':prod' => $produto,
                ':preco' => $preco,
                ':unid' => $unidade,
                ':chave' => $chave,
                ':num' => $numero,
                ':local' => $local,
                ':importacao' => $dataImportacaoLocal, // 4. Envia a variável que criamos
                ':quantidade' => $quantidade,
                ':emissao' => $dataEmissao

            ]);
            return true;
        }
        return false;
    }
    /*     public function buscarMelhorPreco($termo) {
            $stmt = $this->pdo->prepare("SELECT * FROM compras WHERE produto LIKE :termo ORDER BY preco ASC LIMIT 1");
            $stmt->execute([':termo' => "%$termo%"]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } */

    // --- ALTERADO: Busca os 3 melhores preços (menores) ---
    /*    public function buscarTop5($termo) {
           // LIMIT 3 garante que pegamos o Ouro, Prata e Bronze
           $stmt = $this->pdo->prepare("SELECT * FROM compras WHERE produto LIKE :termo ORDER BY preco ASC LIMIT 5");
           $stmt->execute([':termo' => "%$termo%"]);
           return $stmt->fetchAll(PDO::FETCH_ASSOC); // fetchAll pega todos, não só um
       } */


    // --- ATUALIZADO: Busca os 5 melhores preços ---
    public function buscarTop5($termo)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM compras WHERE produto LIKE :termo ORDER BY preco ASC LIMIT 10");
        // Forma mais segura de concatenar o %
        $stmt->execute([':termo' => "%" . $termo . "%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // --- NOVO: Calcula estatísticas para sugestão ---
    public function buscarEstatisticas($termo)
    {
        // MIN = Menor preço já visto
        // AVG = Média de todos os preços
        // COUNT = Quantas vezes apareceu na lista
        $sql = "SELECT MIN(preco) as minimo, AVG(preco) as media, MAX(preco) as maximo, COUNT(*) as qtd 
                FROM compras WHERE produto LIKE :termo";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':termo' => "%$termo%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function buscarHistoricoGrafico($termo)
    {
        $stmt = $this->pdo->prepare("SELECT preco, data_importacao FROM compras WHERE produto LIKE :termo ORDER BY data_importacao ASC");
        $stmt->execute([':termo' => "%$termo%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTudo($orderBy = 'data_emissao', $orderDir = 'DESC')
    {
        $allowedColumns = ['data_emissao', 'local', 'produto', 'preco'];
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'data_emissao';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT c.*, p.preco_venda FROM compras c LEFT JOIN estoque_produtos p 
        ON p.nome_produto = c.produto ORDER BY $orderBy $orderDir LIMIT 1000";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPrecoVenda($id)
    {
        $sql = "SELECT preco_venda FROM compras WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // --- NOVO: Busca produto pelo Código de Barras (EAN) ---
    public function buscarPorCodigoBarras($codigo)
    {
        // Limpa o código para garantir que são só números
        $codigo = preg_replace('/[^0-9]/', '', $codigo);

        // Procura na coluna codigo_barras ou dentro do texto do produto
        $sql = "SELECT * FROM compras 
                WHERE codigo_barras = :codigo OR produto LIKE :codigo_like 
                ORDER BY data_importacao DESC LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':codigo' => $codigo, ':codigo_like' => "%$codigo%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --- NOVO: Vincula um código de barras a um produto existente na tabela compras ---
    public function vincularCodigoBarras($idCompra, $codigoBarras)
    {
        $codigoBarras = preg_replace('/[^0-9]/', '', $codigoBarras);
        $stmt = $this->pdo->prepare("UPDATE compras SET codigo_barras = :codigo WHERE id = :id");
        return $stmt->execute([':codigo' => $codigoBarras, ':id' => $idCompra]);
    }
}
?>