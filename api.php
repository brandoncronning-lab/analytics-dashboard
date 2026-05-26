<?php
/**
 * Internal API / Router
 * 
 * This file is NEVER seen by the user directly. 
 * Our JavaScript (app.js) sends requests here in the background,
 * and this file responds with JSON (a data format JavaScript loves).
 */

// We tell the browser: "Hey, the output of this file is JSON, not HTML!"
header('Content-Type: application/json');

// Include our classes
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/CSVProcessor.php';
require_once __DIR__ . '/classes/AnalyticsEngine.php';

// Initialize our Database and Classes
$db = new Database();
$pdo = $db->getConnection();
$csvProcessor = new CSVProcessor($pdo);
$analytics = new AnalyticsEngine($pdo);

// What does JavaScript want us to do? (Defaults to empty string)
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'generate_mock':
        // Generate a test file so we can test large processing
        $rows = isset($_GET['rows']) ? (int) $_GET['rows'] : 50000;
        $filename = 'mock_data_' . uniqid() . '.csv';
        $filepath = __DIR__ . '/uploads/' . $filename;

        try {
            $csvProcessor->generateMockCSV($filepath, $rows);

            // Also create an upload record so we can track it in the database
            $stmt = $pdo->prepare("INSERT INTO uploads (filename, total_rows) VALUES (?, ?)");
            $stmt->execute(['mock_data.csv', $rows]);
            $uploadId = $pdo->lastInsertId();

            // Reply to JavaScript with the info it needs to start chunking
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
        // 1. Check if a file was actually uploaded
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
            exit;
        }

        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // 2. Validate it's a CSV
        if ($fileExtension != 'csv') {
            echo json_encode(['success' => false, 'error' => 'Only CSV files are allowed.']);
            exit;
        }

        // 3. Move it to our secure uploads folder
        $newFilePath = __DIR__ . '/uploads/' . uniqid() . '.csv';
        if (move_uploaded_file($fileTmpPath, $newFilePath)) {

            // 4. Count total lines so our progress bar knows when it's done!
            $totalLines = $csvProcessor->countLines($newFilePath);

            // Subtract 1 because the first line is the header (column names)
            $totalRowsToProcess = $totalLines > 0 ? $totalLines - 1 : 0;

            // 5. Save a record of this upload in our database
            $stmt = $pdo->prepare("INSERT INTO uploads (filename, total_rows) VALUES (?, ?)");
            $stmt->execute([$fileName, $totalRowsToProcess]);
            $uploadId = $pdo->lastInsertId();

            // Reply to JavaScript with the file info so it can start chunking!
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
        // This runs multiple times until the file is done!
        // JavaScript sends us the offset (where we left off)
        $filepath = __DIR__ . '/uploads/' . $_GET['filepath'];
        $uploadId = (int) $_GET['upload_id'];
        $offset = (int) $_GET['offset'];
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 2000; // Process 2000 rows at a time

        try {
            // Process this chunk and get the number of rows actually inserted
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
        // Fetch all chart data and KPIs for the dashboard
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
        // Fetch paginated rows for the Data Table
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
        // Clear everything
        try {
            $analytics->resetDatabase();

            // Delete files in uploads folder
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
