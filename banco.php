<?php
class Banco {
    private $pdo;

    public function __construct() {
        $this->pdo = new PDO('sqlite:meus_precos.db');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->criarTabela();
    }

    private function criarTabela() {
        // Nova estrutura com chave, nota e local
        $sql = "CREATE TABLE IF NOT EXISTS compras (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            produto TEXT,
            preco REAL,
            unidade TEXT,
            chave TEXT,
            numero_nota TEXT,
            local TEXT,
            data_importacao DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }

    public function inserir($produto, $preco, $unidade, $chave, $numero, $local) {
        // Evita duplicar o mesmo produto da mesma nota
        $check = $this->pdo->prepare("SELECT id FROM compras WHERE produto = :prod AND chave = :chave");
        $check->execute([':prod' => $produto, ':chave' => $chave]);
        
        if (!$check->fetch()) {
            $stmt = $this->pdo->prepare("INSERT INTO compras (produto, preco, unidade, chave, numero_nota, local) VALUES (:prod, :preco, :unid, :chave, :num, :local)");
            $stmt->execute([
                ':prod' => $produto, 
                ':preco' => $preco, 
                ':unid' => $unidade,
                ':chave' => $chave,
                ':num' => $numero,
                ':local' => $local
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
public function buscarTop5($termo) {
        $stmt = $this->pdo->prepare("SELECT * FROM compras WHERE produto LIKE :termo ORDER BY preco ASC LIMIT 5");
        // Forma mais segura de concatenar o %
        $stmt->execute([':termo' => "%" . $termo . "%"]); 
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // --- NOVO: Calcula estatísticas para sugestão ---
    public function buscarEstatisticas($termo) {
        // MIN = Menor preço já visto
        // AVG = Média de todos os preços
        // COUNT = Quantas vezes apareceu na lista
        $sql = "SELECT MIN(preco) as minimo, AVG(preco) as media, MAX(preco) as maximo, COUNT(*) as qtd 
                FROM compras WHERE produto LIKE :termo";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':termo' => "%$termo%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function buscarHistoricoGrafico($termo) {
        $stmt = $this->pdo->prepare("SELECT preco, data_importacao FROM compras WHERE produto LIKE :termo ORDER BY data_importacao ASC");
        $stmt->execute([':termo' => "%$termo%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTudo() {
        $sql = "SELECT * FROM compras ORDER BY data_importacao DESC LIMIT 100";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- NOVO: Busca produto pelo Código de Barras (EAN) ---
    public function buscarPorCodigoBarras($codigo) {
        // Limpa o código para garantir que são só números
        $codigo = preg_replace('/[^0-9]/', '', $codigo);
        
        // Estratégia Dupla:
        // 1. Procura se existe uma coluna 'codigo_barra' (se você criar no futuro)
        // 2. O MAIS IMPORTANTE HOJE: Procura esse número dentro do texto 'produto'
        //    (Pois seus logs mostram "REFRIG... 7894900031515")
        
        $sql = "SELECT * FROM compras 
                WHERE produto LIKE :codigo 
                ORDER BY data_importacao DESC LIMIT 1";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':codigo' => "%$codigo%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>