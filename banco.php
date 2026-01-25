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

    public function buscarMelhorPreco($termo) {
        $stmt = $this->pdo->prepare("SELECT * FROM compras WHERE produto LIKE :termo ORDER BY preco ASC LIMIT 1");
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
}
?>