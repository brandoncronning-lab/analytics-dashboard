<?php
// Handles CSV reading, mock data generation, and chunk-based SQL imports
class CSVProcessor {
    private $pdo;

    public function __construct($dbConnection) {
        $this->pdo = $dbConnection;
    }

    // Count lines efficiently without reading whole file into memory
    public function countLines($filepath) {
        $file = new SplFileObject($filepath, 'r');
        // Go to the end of the file
        $file->seek(PHP_INT_MAX);
        // Return the line number
        return $file->key();
    }

    // Generate mock CSV data file for test imports
    public function generateMockCSV($filepath, $numRows) {
        $file = fopen($filepath, 'w');
        
        // Write the header row
        fputcsv($file, ['Order ID', 'Date', 'Customer Name', 'Product Name', 'Category', 'Price', 'Quantity', 'Region', 'Profit']);

        $categories = ['Electronics', 'Clothing', 'Home & Garden', 'Sports', 'Toys'];
        $regions = ['North America', 'Europe', 'Asia', 'South America', 'Africa'];
        $products = ['Widget A', 'Super Gizmo', 'Magic Tool', 'Smart Watch', 'Cozy Blanket', 'Running Shoes'];

        for ($i = 1; $i <= $numRows; $i++) {
            $price = rand(10, 500) + (rand(0, 99) / 100);
            $quantity = rand(1, 10);
            $profit = ($price * $quantity) * (rand(10, 40) / 100); // 10% to 40% profit margin
            
            // Random date in the past year
            $timestamp = time() - rand(0, 31536000); 
            
            fputcsv($file, [
                'ORD-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                date('Y-m-d', $timestamp),
                'Customer ' . rand(1, 5000),
                $products[array_rand($products)],
                $categories[array_rand($categories)],
                round($price, 2),
                $quantity,
                $regions[array_rand($regions)],
                round($profit, 2)
            ]);
        }
        
        fclose($file);
        return true;
    }

    // Read and import a specific chunk range from the CSV
    public function importChunk($filepath, $uploadId, $offset, $limit) {
        if (!file_exists($filepath)) {
            throw new Exception("File not found.");
        }

        $file = new SplFileObject($filepath, 'r');
        $file->setFlags(SplFileObject::READ_CSV);
        
        // Skip the header row if we are starting at the very beginning
        if ($offset == 0) {
            $offset = 1; 
        }

        // Jump directly to our starting line
        $file->seek($offset);

        // Use transaction to speed up bulk inserts
        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare("
            INSERT INTO sales_data (upload_id, order_id, order_date, customer_name, product_name, category, price, quantity, region, profit) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $rowsProcessed = 0;
        
        // Read lines up to the limit or EOF
        while (!$file->eof() && $rowsProcessed < $limit) {
            $row = $file->fgetcsv();
            
            // Validate basic column count
            if ($row && count($row) >= 9) {
                // Execute the insert
                $stmt->execute([
                    $uploadId,
                    $row[0], // Order ID
                    $row[1], // Date
                    $row[2], // Customer Name
                    $row[3], // Product Name
                    $row[4], // Category
                    (float)$row[5], // Price
                    (int)$row[6], // Quantity
                    $row[7], // Region
                    (float)$row[8] // Profit
                ]);
                $rowsProcessed++;
            }
        }

        // Commit inserts
        $this->pdo->commit();

        return $rowsProcessed;
    }
}
