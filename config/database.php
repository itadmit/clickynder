<?php
// קובץ הגדרות התחברות למסד הנתונים

class Database {
    private $host = "localhost";
    private $db_name = "clickynder";
    private $username = "root";
    private $password = "root";
    private $conn;

    // התחברות למסד הנתונים
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                                 $this->username, 
                                 $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // יצירת טבלת subscriptions אם לא קיימת
            $query = "CREATE TABLE IF NOT EXISTS subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                max_staff_members INT NOT NULL,
                max_customers INT NOT NULL,
                max_services INT NOT NULL,
                features TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->conn->exec($query);

            // בדיקה אם העמודה max_services קיימת
            $stmt = $this->conn->query("SHOW COLUMNS FROM subscriptions LIKE 'max_services'");
            if ($stmt->rowCount() == 0) {
                // הוספת העמודה אם לא קיימת
                $this->conn->exec("ALTER TABLE subscriptions ADD COLUMN max_services INT NOT NULL DEFAULT 1");
            }

            // בדיקה אם הטבלה ריקה
            $stmt = $this->conn->query("SELECT COUNT(*) FROM subscriptions");
            if ($stmt->fetchColumn() == 0) {
                // הוספת מסלולים ברירת מחדל
                $query = "INSERT INTO subscriptions (name, price, max_staff_members, max_customers, max_services, features) VALUES 
                          ('רגיל', 0, 1, 10, 1, 'מסלול בסיסי'),
                          ('פרו', 99, 5, 250, 5, 'מסלול מתקדם'),
                          ('פלטינום', 199, 999, 999999, 10, 'מסלול פלטינום')";
                $this->conn->exec($query);
            } else {
                // עדכון המסלולים הקיימים
                $query = "UPDATE subscriptions SET 
                          max_services = CASE 
                            WHEN name = 'רגיל' THEN 1
                            WHEN name = 'פרו' THEN 5
                            WHEN name = 'פלטינום' THEN 10
                            ELSE max_services
                          END";
                $this->conn->exec($query);
            }
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }

        return $this->conn;
    }
}
?>