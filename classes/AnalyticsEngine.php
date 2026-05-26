<?php
// Handles dashboard KPI computations and chart queries
class AnalyticsEngine {
    private $pdo;

    public function __construct($dbConnection) {
        $this->pdo = $dbConnection;
    }

    // Calculate aggregate KPI metrics
    public function getKPIs() {
        // SQL aggregate calculations on the database level
        $query = "
            SELECT 
                COUNT(*) as total_orders,
                SUM(price * quantity) as total_revenue,
                SUM(profit) as total_profit,
                AVG(price * quantity) as avg_order_value
            FROM sales_data
        ";
        
        $stmt = $this->pdo->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate Profit Margin: (Profit / Revenue) * 100
        $margin = 0;
        if ($result['total_revenue'] > 0) {
            $margin = ($result['total_profit'] / $result['total_revenue']) * 100;
        }
        $result['profit_margin'] = round($margin, 2);
        
        return $result;
    }

    // Get sales trend grouped by month
    public function getSalesTrend() {
        // Format date to year-month groupings
        $query = "
            SELECT 
                DATE_FORMAT(order_date, '%Y-%m') as month,
                SUM(price * quantity) as revenue
            FROM sales_data
            GROUP BY month
            ORDER BY month ASC
            LIMIT 12
        ";
        
        $stmt = $this->pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get sales volume grouped by category
    public function getSalesByCategory() {
        $query = "
            SELECT 
                category,
                SUM(price * quantity) as revenue
            FROM sales_data
            GROUP BY category
            ORDER BY revenue DESC
        ";
        
        $stmt = $this->pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get profits grouped by region
    public function getProfitByRegion() {
        $query = "
            SELECT 
                region,
                SUM(profit) as profit
            FROM sales_data
            GROUP BY region
            ORDER BY profit DESC
        ";
        
        $stmt = $this->pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Fetch paginated and searchable rows for data table
    public function getDataTable($offset = 0, $limit = 50, $search = '') {
        // The base query
        $query = "SELECT * FROM sales_data ";
        $params = [];
        
        // Apply optional search filter
        if (!empty($search)) {
            $query .= "WHERE customer_name LIKE ? OR order_id LIKE ? OR product_name LIKE ? ";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        // Pagination sorting and limits
        $query .= "ORDER BY order_date DESC LIMIT ? OFFSET ?";
        
        // Bind pagination params
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination calculations
        $countQuery = "SELECT COUNT(*) FROM sales_data ";
        if (!empty($search)) {
            $countQuery .= "WHERE customer_name LIKE ? OR order_id LIKE ? OR product_name LIKE ? ";
            $countStmt = $this->pdo->prepare($countQuery);
            $countStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        } else {
            $countStmt = $this->pdo->query($countQuery);
        }
        $totalRecords = $countStmt->fetchColumn();
        
        return [
            'rows' => $rows,
            'total' => $totalRecords
        ];
    }
    
    // Truncate tables to reset system state
    public function resetDatabase() {
        // Disable FK checks to allow truncate operations
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->pdo->exec("TRUNCATE TABLE sales_data");
        $this->pdo->exec("TRUNCATE TABLE uploads");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
}
