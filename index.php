<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Intelligence Dashboard</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- Header -->
        <header>
            <h1><i class="fas fa-chart-pie"></i> Business Intelligence</h1>
            <div class="controls">
                <button id="btn-mock" class="btn">
                    <i class="fas fa-magic"></i> Generate Mock Data
                </button>
                <button id="btn-upload" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload CSV
                </button>
                <button id="btn-reset" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Reset Database
                </button>
                <!-- Hidden file input -->
                <input type="file" id="csv-file" accept=".csv" class="hidden">
            </div>
        </header>

        <!-- Progress Area -->
        <div id="progress-container" class="progress-container hidden">
            <div class="progress-header">
                <span id="progress-text" style="font-weight: 600;">Processing...</span>
                <span id="progress-rows" style="color: var(--text-secondary);">0 / 0 rows</span>
            </div>
            <div class="progress-track">
                <div id="progress-fill" class="progress-fill"></div>
            </div>
        </div>

        <!-- Main Dashboard -->
        <div id="dashboard-content" class="hidden">
            
            <!-- KPIs -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-title">Total Revenue</div>
                    <div class="kpi-value" id="kpi-revenue">$0.00</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Total Profit</div>
                    <div class="kpi-value text-success" id="kpi-profit">$0.00</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Total Orders</div>
                    <div class="kpi-value" id="kpi-orders">0</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Profit Margin</div>
                    <div class="kpi-value" id="kpi-margin">0%</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <div class="chart-card full-width">
                    <div class="chart-title">Revenue Trend (Monthly)</div>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-title">Revenue by Category</div>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-title">Profitability by Region</div>
                    <div class="chart-container">
                        <canvas id="regionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="data-table-card">
                <div class="table-header">
                    <div class="chart-title" style="margin: 0;">Recent Transactions</div>
                    <div>
                        <input type="text" id="search-input" class="search-box" placeholder="Search customer, order ID...">
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer Name</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th class="text-right">Price</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Profit</th>
                            </tr>
                        </thead>
                        <tbody id="table-body">
                            <!-- Rows injected via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

    <!-- App JS -->
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
</body>
</html>
