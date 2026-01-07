<?php
require_once __DIR__ . '/Database.php';

class Laptop
{
    public $id;
    public $name;
    public $model_code;
    public $description;
    public $weight_kg;
    public $price_eur;
    public $specs = [];

    public function __construct($id, $name, $model_code = null, $description = null, $weight_kg = null, $price_eur = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->model_code = $model_code;
        $this->description = $description;
        $this->weight_kg = $weight_kg;
        $this->price_eur = $price_eur;
    }

    public static function all()
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->query('SELECT id, name, model_code, description, weight_kg, price_eur FROM laptops WHERE is_active = 1');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $laptop = new self(
                $r['id'], 
                $r['name'], 
                $r['model_code'], 
                $r['description'], 
                $r['weight_kg'], 
                $r['price_eur']
            );
            $laptop->specs = $laptop->getSpecs();
            $out[] = $laptop;
        }
        return $out;
    }

    public function getSpecs()
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare('SELECT spec_key, spec_value FROM laptop_specs WHERE laptop_id = :lid ORDER BY display_order');
        $stmt->execute([':lid' => $this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}