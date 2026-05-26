<?php
// API router for handling dashboard actions (AJAX requests)
header('Content-Type: application/json');

// Include dependencies
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/CSVProcessor.php';
require_once __DIR__ . '/classes/AnalyticsEngine.php';

// Initialize services
$db = new Database();
$pdo = $db->getConnection();
$csvProcessor = new CSVProcessor($pdo);
$analytics = new AnalyticsEngine($pdo);

// Route request action
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'generate_mock':
        // Generate mock CSV data
        $rows = isset($_GET['rows']) ? (int) $_GET['rows'] : 50000;
        $filename = 'mock_data_' . uniqid() . '.csv';
        $filepath = __DIR__ . '/uploads/' . $filename;

        try {
            $csvProcessor->generateMockCSV($filepath, $rows);

            // Track upload in database
            $stmt = $pdo->prepare("INSERT INTO uploads (filename, total_rows) VALUES (?, ?)");
            $stmt->execute(['mock_data.csv', $rows]);
            $uploadId = $pdo->lastInsertId();

            // Return details to start chunk processing
            echo json_encode([
                'success' => true,
                'filepath' => $filename,
                'upload_id' => $uploadId,
                'total_rows' => $rows
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'upload':
        // Validate upload
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
            exit;
        }

        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate file extension
        if ($fileExtension != 'csv') {
            echo json_encode(['success' => false, 'error' => 'Only CSV files are allowed.']);
            exit;
        }

        // Move to uploads directory
        $newFilePath = __DIR__ . '/uploads/' . uniqid() . '.csv';
        if (move_uploaded_file($fileTmpPath, $newFilePath)) {

            // Get total line count for progress tracking
            $totalLines = $csvProcessor->countLines($newFilePath);

            // Exclude header row
            $totalRowsToProcess = $totalLines > 0 ? $totalLines - 1 : 0;

            // Store upload metadata
            $stmt = $pdo->prepare("INSERT INTO uploads (filename, total_rows) VALUES (?, ?)");
            $stmt->execute([$fileName, $totalRowsToProcess]);
            $uploadId = $pdo->lastInsertId();

            // Return file details to client
            echo json_encode([
                'success' => true,
                'upload_id' => $uploadId,
                'filepath' => basename($newFilePath), // Only send the filename back for security
                'total_rows' => $totalRowsToProcess
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file.']);
        }
        break;

    case 'import_chunk':
        // Process a single chunk of the CSV file
        $filepath = __DIR__ . '/uploads/' . $_GET['filepath'];
        $uploadId = (int) $_GET['upload_id'];
        $offset = (int) $_GET['offset'];
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 2000;

        try {
            // Import chunk
            $processed = $csvProcessor->importChunk($filepath, $uploadId, $offset, $limit);

            echo json_encode([
                'success' => true,
                'processed' => $processed,
                'next_offset' => $offset + $processed
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_analytics':
        // Fetch KPI and chart metrics
        try {
            $kpis = $analytics->getKPIs();
            $trend = $analytics->getSalesTrend();
            $category = $analytics->getSalesByCategory();
            $region = $analytics->getProfitByRegion();

            echo json_encode([
                'success' => true,
                'kpis' => $kpis,
                'charts' => [
                    'trend' => $trend,
                    'category' => $category,
                    'region' => $region
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_data':
        // Fetch data table rows (paginated)
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $search = isset($_GET['search']) ? $_GET['search'] : '';

        try {
            $data = $analytics->getDataTable($offset, $limit, $search);
            echo json_encode([
                'success' => true,
                'data' => $data['rows'],
                'total' => $data['total']
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'reset':
        // Reset database and clear uploads
        try {
            $analytics->resetDatabase();

            // Delete files in uploads directory
            $files = glob(__DIR__ . '/uploads/*.csv');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
